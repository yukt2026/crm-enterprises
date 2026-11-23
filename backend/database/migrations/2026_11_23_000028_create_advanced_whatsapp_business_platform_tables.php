<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Advanced WhatsApp Business Platform CRM (Prompt 34).
     */
    public function up(): void
    {
        // 1. Extend whatsapp_accounts table with token expiry, testing & logging
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_accounts', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('webhook_verify_token');
            }
            if (!Schema::hasColumn('whatsapp_accounts', 'last_tested_at')) {
                $table->timestamp('last_tested_at')->nullable()->after('token_expires_at');
            }
            if (!Schema::hasColumn('whatsapp_accounts', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_tested_at');
            }
        });

        // 2. Extend whatsapp_phone_numbers table with branch, department, limits & working hours
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete()->after('default_assigned_user_id');
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'department_id')) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('branch_id');
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'messaging_limit_tier')) {
                $table->string('messaging_limit_tier', 30)->default('TIER_250')->after('quality_rating'); // TIER_250, TIER_1K, TIER_10K, TIER_100K, TIER_UNLIMITED
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'channel_status')) {
                $table->string('channel_status', 30)->default('connected')->after('status'); // connected, flagged, restricted, disconnected
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'working_hours')) {
                $table->json('working_hours')->nullable()->after('channel_status');
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'default_reply_rules')) {
                $table->json('default_reply_rules')->nullable()->after('working_hours');
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'assigned_team_ids')) {
                $table->json('assigned_team_ids')->nullable()->after('default_reply_rules');
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'messages_sent_today')) {
                $table->unsignedInteger('messages_sent_today')->default(0)->after('assigned_team_ids');
            }
            if (!Schema::hasColumn('whatsapp_phone_numbers', 'conversations_today')) {
                $table->unsignedInteger('conversations_today')->default(0)->after('messages_sent_today');
            }
        });

        // 3. Extend whatsapp_templates table with rejection details, header content & usage metrics
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_templates', 'header_content')) {
                $table->string('header_content', 500)->nullable()->after('header_type');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('meta_template_id');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'sent_count')) {
                $table->unsignedInteger('sent_count')->default(0)->after('rejected_reason');
            }
            if (!Schema::hasColumn('whatsapp_templates', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('sent_count');
            }
        });

        // 4. WhatsApp Integration & Provider Error Logs
        if (!Schema::hasTable('whatsapp_integration_logs')) {
            Schema::create('whatsapp_integration_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->nullOnDelete();
                $table->foreignId('whatsapp_phone_number_id')->nullable()->constrained('whatsapp_phone_numbers')->nullOnDelete();
                $table->string('event_type', 100); // connection_test, webhook_verify, token_refresh, template_sync, message_send, campaign_dispatch
                $table->string('status', 30)->default('success'); // success, warning, error
                $table->json('payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'event_type']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 5. WhatsApp Bulk Campaigns
        if (!Schema::hasTable('whatsapp_campaigns')) {
            Schema::create('whatsapp_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 200);
                $table->foreignId('whatsapp_phone_number_id')->constrained('whatsapp_phone_numbers')->cascadeOnDelete();
                $table->foreignId('whatsapp_template_id')->constrained('whatsapp_templates')->cascadeOnDelete();
                $table->string('audience_type', 30)->default('segment'); // segment, contacts, csv
                $table->foreignId('segment_id')->nullable()->constrained('marketing_segments')->nullOnDelete();
                $table->string('csv_file_path', 500)->nullable();
                $table->unsignedInteger('total_recipients')->default(0);
                $table->unsignedInteger('queued_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('delivered_count')->default(0);
                $table->unsignedInteger('read_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('replied_count')->default(0);
                $table->unsignedInteger('opted_out_count')->default(0);
                $table->decimal('estimated_cost', 10, 4)->default(0.0000);
                $table->json('variable_mappings')->nullable(); // e.g. {"1": "first_name", "2": "company"}
                $table->timestamp('scheduled_at')->nullable();
                $table->string('status', 30)->default('draft'); // draft, pending_approval, approved, queued, processing, paused, completed, cancelled
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'whatsapp_phone_number_id']);
                $table->index(['tenant_id', 'scheduled_at']);
            });
        }

        // 6. WhatsApp Campaign Recipients
        if (!Schema::hasTable('whatsapp_campaign_recipients')) {
            Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('campaign_id')->constrained('whatsapp_campaigns')->cascadeOnDelete();
                $table->string('phone_number', 50);
                $table->string('recipient_name', 150)->nullable();
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->json('variables')->nullable();
                $table->string('status', 30)->default('queued'); // queued, sent, delivered, read, failed, skipped_opt_out
                $table->foreignId('whatsapp_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'campaign_id', 'status']);
                $table->index(['tenant_id', 'phone_number']);
            });
        }

        // 7. WhatsApp Automated Customer Journeys
        if (!Schema::hasTable('whatsapp_automations')) {
            Schema::create('whatsapp_automations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 200);
                $table->string('journey_type', 60); // new_enquiry_welcome, lead_qualification, appointment_confirmation, payment_reminder, invoice_reminder, renewal_reminder, customer_onboarding, service_update, sales_followup, abandoned_enquiry
                $table->string('trigger_event', 60); // lead_created, deal_stage_changed, invoice_created, invoice_due, payment_received, appointment_booked, abandoned_cart, ticket_created
                $table->unsignedInteger('delay_minutes')->default(0);
                $table->json('conditions')->nullable(); // e.g. {"lead_score_gt": 50, "status": "new"}
                $table->foreignId('whatsapp_phone_number_id')->nullable()->constrained('whatsapp_phone_numbers')->nullOnDelete();
                $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
                $table->json('message_payload')->nullable();
                $table->string('stop_condition', 50)->default('customer_replied'); // customer_replied, payment_received, invoice_paid, status_changed, none
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('execution_count')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'journey_type']);
                $table->index(['tenant_id', 'trigger_event', 'is_active']);
            });
        }

        // 8. WhatsApp Automation Logs
        if (!Schema::hasTable('whatsapp_automation_logs')) {
            Schema::create('whatsapp_automation_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('automation_id')->constrained('whatsapp_automations')->cascadeOnDelete();
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->string('phone_number', 50);
                $table->json('trigger_context')->nullable();
                $table->string('status', 30)->default('triggered'); // triggered, delayed, sent, stopped, failed
                $table->string('stopped_reason', 255)->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'automation_id', 'status']);
            });
        }

        // 9. WhatsApp Billing & Usage Records
        if (!Schema::hasTable('whatsapp_usage_records')) {
            Schema::create('whatsapp_usage_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('whatsapp_phone_number_id')->nullable()->constrained('whatsapp_phone_numbers')->nullOnDelete();
                $table->foreignId('campaign_id')->nullable()->constrained('whatsapp_campaigns')->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
                $table->string('conversation_category', 30)->default('utility'); // marketing, utility, authentication, service
                $table->decimal('provider_reported_charge', 10, 4)->default(0.0000);
                $table->string('rate_currency', 10)->default('USD');
                $table->unsignedInteger('billable_conversations_count')->default(1);
                $table->date('recorded_date');
                $table->timestamps();

                $table->index(['tenant_id', 'recorded_date']);
                $table->index(['tenant_id', 'whatsapp_phone_number_id']);
                $table->index(['tenant_id', 'conversation_category']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_usage_records');
        Schema::dropIfExists('whatsapp_automation_logs');
        Schema::dropIfExists('whatsapp_automations');
        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_integration_logs');

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn(['header_content', 'rejected_reason', 'sent_count', 'last_used_at']);
        });

        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn([
                'branch_id', 'department_id', 'messaging_limit_tier',
                'channel_status', 'working_hours', 'default_reply_rules',
                'assigned_team_ids', 'messages_sent_today', 'conversations_today'
            ]);
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn(['token_expires_at', 'last_tested_at', 'last_error']);
        });
    }
};
