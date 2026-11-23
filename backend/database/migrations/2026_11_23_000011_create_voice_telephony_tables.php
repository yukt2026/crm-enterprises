<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Telephony Providers (Exotel, Twilio, Generic SIP)
        Schema::create('telephony_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->enum('provider', ['exotel', 'twilio', 'sip', 'custom'])->default('exotel');
            $table->string('name', 128);
            $table->string('account_sid', 191)->nullable(); // Exotel Account SID or Twilio Account SID
            $table->text('auth_token'); // Encrypted API token/secret
            $table->string('api_key', 191)->nullable(); // Exotel API key or Twilio API Key
            $table->string('subdomain', 128)->nullable(); // e.g. api.exotel.com or custom SIP host
            $table->string('caller_id', 32)->nullable(); // Default outbound caller ID
            $table->string('dlt_entity_id', 64)->nullable(); // TRAI DLT Entity ID for India compliance
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_recordings')->default(true);
            $table->boolean('supports_realtime_streams')->default(false);
            $table->json('settings')->nullable(); // regional routing, retry limits, etc.
            $table->timestamps();

            $table->index(['tenant_id', 'provider']);
        });

        // 2. Telephony Phone Numbers (Inbound Virtual Numbers & DIDs)
        Schema::create('telephony_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('telephony_provider_id')->constrained('telephony_providers')->onDelete('cascade');
            $table->string('phone_number', 32); // E.164 formatted
            $table->string('display_name', 128)->nullable();
            $table->string('country_code', 8)->default('IN');
            $table->boolean('is_default')->default(false);
            $table->enum('routing_type', ['ai_agent', 'user', 'queue', 'ivr'])->default('ai_agent');
            $table->unsignedBigInteger('default_agent_id')->nullable(); // Target Voice Agent
            $table->foreignId('default_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'phone_number']);
        });

        // 3. Voice AI Agents
        Schema::create('voice_ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('telephony_phone_number_id')->nullable()->constrained('telephony_phone_numbers')->onDelete('set null');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'paused', 'draft'])->default('active');
            $table->string('language', 32)->default('multi'); // 'en', 'hi', 'hinglish', 'multi'
            $table->string('voice_engine', 64)->default('elevenlabs'); // 'elevenlabs', 'openai', 'google', 'cartesia'
            $table->string('voice_id', 128)->default('alloy'); // Provider voice ID (e.g. 'rachel', 'alloy', 'hi-IN-Wavenet-A')
            $table->enum('tone', ['consultative', 'professional', 'friendly', 'persuasive'])->default('consultative');
            $table->longText('system_instructions');
            $table->text('call_opening'); // Opening greeting and mandatory recording disclosure
            $table->json('qualification_script')->nullable(); // structured questions list
            $table->unsignedInteger('max_call_duration_seconds')->default(600); // hard cap (e.g. 10 mins)
            $table->string('human_transfer_number', 32)->nullable(); // fallback transfer target
            $table->json('allowed_crm_actions')->nullable(); // allowed tools: update_lead, schedule_callback, etc.
            $table->unsignedBigInteger('monthly_call_minutes_budget')->default(1000);
            $table->unsignedBigInteger('used_call_minutes_this_month')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        // 4. Outbound Calling Campaigns
        Schema::create('voice_calling_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('voice_agent_id')->constrained('voice_ai_agents')->onDelete('cascade');
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'running', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->json('target_filters')->nullable(); // filters for CRM leads (status, stage, source, tags)
            $table->unsignedInteger('max_retries')->default(2);
            $table->unsignedInteger('retry_interval_hours')->default(4);
            $table->unsignedInteger('calls_per_minute_limit')->default(5);
            $table->unsignedInteger('total_contacts_queued')->default(0);
            $table->unsignedInteger('total_contacts_called')->default(0);
            $table->unsignedInteger('total_contacts_answered')->default(0);
            $table->unsignedInteger('total_contacts_qualified')->default(0);
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 5. Core Voice Call Records Ledger
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('voice_agent_id')->nullable()->constrained('voice_ai_agents')->onDelete('set null');
            $table->foreignId('campaign_id')->nullable()->constrained('voice_calling_campaigns')->onDelete('set null');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('set null');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('provider', 32)->default('exotel'); // 'exotel', 'twilio', 'sip'
            $table->string('provider_call_id', 191)->nullable()->index(); // Provider Call SID / UUID
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->string('from_number', 32);
            $table->string('to_number', 32);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->enum('status', [
                'queued',
                'ringing',
                'in-progress',
                'completed',
                'busy',
                'no-answer',
                'failed',
                'cancelled'
            ])->default('queued');
            $table->enum('disposition', [
                'interested',
                'not_interested',
                'callback_requested',
                'wrong_number',
                'converted',
                'dnd_requested',
                'unreached',
                'completed'
            ])->nullable();
            $table->string('recording_url', 255)->nullable();
            $table->unsignedInteger('recording_duration_seconds')->default(0);
            $table->longText('transcript')->nullable(); // Dialogue turn-by-turn
            $table->text('ai_summary')->nullable();
            $table->json('extracted_data')->nullable(); // budget, need, timeline, objections
            $table->enum('qualification_result', ['qualified', 'unqualified', 'follow_up_needed'])->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->decimal('cost_amount', 8, 4)->default(0.0000);
            $table->string('cost_currency', 8)->default('INR');
            $table->boolean('human_transferred')->default(false);
            $table->string('transfer_target', 64)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'direction']);
            $table->index(['tenant_id', 'to_number']);
        });

        // 6. Telephony Do-Not-Call (DND) Suppression Registry
        Schema::create('telephony_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('phone_number', 32);
            $table->enum('opt_out_type', ['customer_verbal', 'trai_dnd', 'manual_agent', 'gdpr'])->default('customer_verbal');
            $table->string('reason', 255)->nullable();
            $table->timestamp('opted_out_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telephony_opt_outs');
        Schema::dropIfExists('voice_calls');
        Schema::dropIfExists('voice_calling_campaigns');
        Schema::dropIfExists('voice_ai_agents');
        Schema::dropIfExists('telephony_phone_numbers');
        Schema::dropIfExists('telephony_providers');
    }
};
