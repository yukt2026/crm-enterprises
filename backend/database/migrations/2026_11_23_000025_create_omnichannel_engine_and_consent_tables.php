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
        // 1. Extend whatsapp_conversations for SLA and Omnichannel metrics
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('last_message_direction');
                $table->unsignedInteger('first_response_time_seconds')->nullable()->after('first_response_at');
                $table->timestamp('resolved_at')->nullable()->after('first_response_time_seconds');
                $table->unsignedInteger('resolution_time_seconds')->nullable()->after('resolved_at');
                $table->string('last_actor_type', 30)->default('customer')->after('resolution_time_seconds'); // customer, human, ai, automation, system
            }
        });

        // 2. Conversation Assignment & Transfer History Log
        if (!Schema::hasTable('conversation_assignment_logs')) {
            Schema::create('conversation_assignment_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
                $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_to_team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
                $table->string('routing_method', 50)->default('manual'); // manual, round_robin, workload_aware, rule_based, handover
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'conversation_id']);
                $table->index(['tenant_id', 'assigned_to_user_id']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        // 3. Omnichannel Quick Replies / Canned Responses
        if (!Schema::hasTable('omnichannel_quick_replies')) {
            Schema::create('omnichannel_quick_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('channel', 30)->default('all'); // all, whatsapp, facebook, instagram, email, sms, webchat
                $table->string('shortcut', 50); // e.g., /pricing, /hours, /support
                $table->string('title', 150);
                $table->text('content');
                $table->json('tags')->nullable();
                $table->boolean('is_shared')->default(true);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'shortcut']);
                $table->index(['tenant_id', 'channel']);
            });
        }

        // 4. Customer Communication Preferences & Consent Tracking
        if (!Schema::hasTable('customer_communication_consents')) {
            Schema::create('customer_communication_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->string('channel', 30); // whatsapp, facebook, instagram, email, sms, call
                $table->string('destination', 191); // email, phone number, psid, igsid
                $table->string('consent_status', 30)->default('opted_in'); // opted_in, opted_out, suppressed
                $table->string('consent_source', 60)->default('inbound_message'); // form, inbound_message, verbal, checkout, manual
                $table->timestamp('consent_timestamp')->nullable();
                $table->timestamp('opt_out_timestamp')->nullable();
                $table->boolean('is_marketing_allowed')->default(true);
                $table->boolean('is_transactional_allowed')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'channel', 'destination'], 'consent_tenant_channel_dest_unique');
                $table->index(['tenant_id', 'destination']);
                $table->index(['tenant_id', 'consent_status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_communication_consents');
        Schema::dropIfExists('omnichannel_quick_replies');
        Schema::dropIfExists('conversation_assignment_logs');

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'first_response_at')) {
                $table->dropColumn([
                    'first_response_at',
                    'first_response_time_seconds',
                    'resolved_at',
                    'resolution_time_seconds',
                    'last_actor_type'
                ]);
            }
        });
    }
};
