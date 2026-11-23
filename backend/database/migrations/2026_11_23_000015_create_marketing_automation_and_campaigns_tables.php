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
        // 1. Marketing Segments (Dynamic & Static Audiences)
        Schema::create('marketing_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('dynamic'); // dynamic, static
            $table->string('target_entity')->default('lead'); // lead, contact, customer
            $table->json('rules')->nullable(); // criteria array, logical_operator
            $table->json('inclusion_ids')->nullable(); // manual include
            $table->json('exclusion_ids')->nullable(); // manual exclude
            $table->unsignedBigInteger('cached_count')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'target_entity']);
        });

        // 2. Marketing Broadcast Campaigns
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('channel')->default('whatsapp'); // whatsapp, email, sms
            $table->string('type')->default('broadcast'); // broadcast, drip, triggered
            $table->string('status')->default('draft')->index(); // draft, scheduled, running, paused, completed, cancelled
            $table->foreignId('segment_id')->nullable()->constrained('marketing_segments')->nullOnDelete();
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->foreignId('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->nullOnDelete();
            $table->foreignId('email_account_id')->nullable()->constrained('email_accounts')->nullOnDelete();
            $table->foreignId('sms_config_id')->nullable()->constrained('sms_configurations')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->json('variable_mappings')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->integer('rate_limit_per_minute')->default(60);
            $table->unsignedBigInteger('total_recipients_count')->default(0);
            $table->unsignedBigInteger('sent_count')->default(0);
            $table->unsignedBigInteger('delivered_count')->default(0);
            $table->unsignedBigInteger('read_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->unsignedBigInteger('replied_count')->default(0);
            $table->unsignedBigInteger('unsubscribed_count')->default(0);
            $table->decimal('attributed_revenue', 12, 2)->default(0.00);
            $table->decimal('spend_amount', 10, 2)->default(0.00);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'channel', 'status'], 'mkt_camp_tenant_chn_idx');
        });

        // 3. Campaign Recipients Log
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->string('entity_type')->default('lead'); // lead, contact, customer
            $table->unsignedBigInteger('entity_id');
            $table->string('recipient_address'); // email, phone
            $table->string('status')->default('queued')->index(); // queued, sent, delivered, read, failed, skipped
            $table->string('skip_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'campaign_id', 'status'], 'cr_tenant_camp_stat_idx');
            $table->index(['campaign_id', 'entity_type', 'entity_id'], 'cr_camp_entity_idx');
        });

        // 4. Drip Sequences
        Schema::create('drip_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_event')->default('lead.created'); // lead.created, deal.won, manual
            $table->string('status')->default('draft')->index(); // draft, active, paused
            $table->boolean('exit_on_conversion')->default(true);
            $table->boolean('exit_on_reply')->default(true);
            $table->boolean('exit_on_unsubscribe')->default(true);
            $table->boolean('respect_quiet_hours')->default(true);
            $table->string('quiet_hours_start')->default('21:00');
            $table->string('quiet_hours_end')->default('09:00');
            $table->integer('max_messages_per_week')->default(3);
            $table->unsignedBigInteger('active_enrollments_count')->default(0);
            $table->unsignedBigInteger('completed_enrollments_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Drip Sequence Steps
        Schema::create('drip_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('drip_sequence_id')->constrained('drip_sequences')->cascadeOnDelete();
            $table->integer('step_order');
            $table->string('channel')->default('whatsapp'); // whatsapp, email, sms
            $table->integer('delay_interval')->default(0);
            $table->string('delay_unit')->default('hours'); // minutes, hours, days
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->json('variable_mappings')->nullable();
            $table->json('skip_conditions')->nullable();
            $table->timestamps();

            $table->unique(['drip_sequence_id', 'step_order']);
        });

        // 6. Drip Sequence Enrollments
        Schema::create('drip_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('drip_sequence_id')->constrained('drip_sequences')->cascadeOnDelete();
            $table->string('entity_type')->default('lead'); // lead, contact, customer
            $table->unsignedBigInteger('entity_id');
            $table->string('status')->default('active')->index(); // active, paused, completed, exited_conversion, exited_reply, exited_unsubscribed
            $table->foreignId('current_step_id')->nullable()->constrained('drip_steps')->nullOnDelete();
            $table->timestamp('next_execution_at')->nullable()->index();
            $table->timestamp('last_message_sent_at')->nullable();
            $table->string('exit_reason')->nullable();
            $table->json('context_data')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'next_execution_at'], 'de_tenant_stat_exec_idx');
            $table->unique(['drip_sequence_id', 'entity_type', 'entity_id'], 'de_seq_entity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drip_enrollments');
        Schema::dropIfExists('drip_steps');
        Schema::dropIfExists('drip_sequences');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_segments');
    }
};
