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
        // 1. Extend `marketing_campaigns` with Objectives, Budgets, Multi-tier Approvals & Attribution
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_campaigns', 'objective')) {
                $table->string('objective', 64)->default('lead_generation')->after('type');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'start_date')) {
                $table->timestamp('start_date')->nullable()->after('scheduled_at');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'end_date')) {
                $table->timestamp('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'budget')) {
                $table->decimal('budget', 12, 2)->default(0.00)->after('spend_amount');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'assigned_owner_id')) {
                $table->foreignId('assigned_owner_id')->nullable()->after('created_by_user_id')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'approval_status')) {
                $table->string('approval_status', 32)->default('not_submitted')->after('status')->index(); // not_submitted, pending_approval, approved, rejected
            }
            if (!Schema::hasColumn('marketing_campaigns', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('approval_status')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id')->index();
            }
            if (!Schema::hasColumn('marketing_campaigns', 'business_hours_only')) {
                $table->boolean('business_hours_only')->default(true)->after('timezone');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'frequency_cap_days')) {
                $table->unsignedInteger('frequency_cap_days')->default(7)->after('business_hours_only');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'clicks_count')) {
                $table->unsignedBigInteger('clicks_count')->default(0)->after('read_count');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'opens_count')) {
                $table->unsignedBigInteger('opens_count')->default(0)->after('clicks_count');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'conversions_count')) {
                $table->unsignedBigInteger('conversions_count')->default(0)->after('replied_count');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'cost_per_lead')) {
                $table->decimal('cost_per_lead', 10, 2)->default(0.00)->after('conversions_count');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'cost_per_acquisition')) {
                $table->decimal('cost_per_acquisition', 10, 2)->default(0.00)->after('cost_per_lead');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'campaign_notes')) {
                $table->text('campaign_notes')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('marketing_campaigns', 'ai_drafted')) {
                $table->boolean('ai_drafted')->default(false)->after('content');
            }
        });

        // 2. Extend `drip_sequences` with Journey Types & Audience Targets
        Schema::table('drip_sequences', function (Blueprint $table) {
            if (!Schema::hasColumn('drip_sequences', 'journey_type')) {
                $table->string('journey_type', 64)->default('lead_nurturing')->after('name');
                // lead_nurturing, welcome_sequence, customer_onboarding, abandoned_recovery, renewal_reminder, birthday_anniversary, payment_reminder, re_engagement, post_purchase
            }
            if (!Schema::hasColumn('drip_sequences', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id')->index();
            }
            if (!Schema::hasColumn('drip_sequences', 'target_segment_id')) {
                $table->foreignId('target_segment_id')->nullable()->after('trigger_event')->constrained('marketing_segments')->onDelete('set null');
            }
        });

        // 3. Extend `drip_steps` with Step Types (Conditional logic & Rep assignments)
        Schema::table('drip_steps', function (Blueprint $table) {
            if (!Schema::hasColumn('drip_steps', 'step_type')) {
                $table->string('step_type', 32)->default('send_message')->after('step_order');
                // send_message, wait_delay, condition_check, assign_rep, schedule_followup
            }
            if (!Schema::hasColumn('drip_steps', 'condition_rules')) {
                $table->json('condition_rules')->nullable()->after('skip_conditions');
            }
        });

        // 4. Campaign Approvals Audit Trail
        if (!Schema::hasTable('campaign_approvals')) {
            Schema::create('campaign_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('campaign_id')->constrained('marketing_campaigns')->onDelete('cascade');
                $table->foreignId('approver_user_id')->constrained('users')->onDelete('cascade');
                $table->string('role_level', 64)->default('marketing_manager'); // marketing_manager, org_admin
                $table->enum('action', ['requested', 'approved', 'rejected'])->default('requested');
                $table->text('comments')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'campaign_id']);
            });
        }

        // 5. Marketing Consents & Suppression Registry
        if (!Schema::hasTable('marketing_consents')) {
            Schema::create('marketing_consents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->string('entity_type', 32)->default('lead'); // lead, contact, customer
                $table->unsignedBigInteger('entity_id');
                $table->enum('channel', ['whatsapp', 'email', 'sms', 'voice', 'all'])->default('all');
                $table->enum('consent_status', ['opted_in', 'opted_out', 'pending'])->default('opted_in');
                $table->string('source', 64)->default('website_form'); // website_form, manual, import, stop_reply
                $table->text('reason')->nullable();
                $table->timestamp('opted_out_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'entity_type', 'entity_id', 'channel'], 'mkt_consent_lookup_idx');
                $table->index(['tenant_id', 'channel', 'consent_status'], 'mkt_consent_status_idx');
            });
        }

        // 6. Reusable Marketing Templates (WhatsApp, Email, SMS)
        if (!Schema::hasTable('marketing_templates')) {
            Schema::create('marketing_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->string('name', 191);
                $table->enum('channel', ['whatsapp', 'email', 'sms'])->default('email');
                $table->string('category', 64)->default('marketing'); // marketing, utility, transactional, festive
                $table->string('subject', 255)->nullable();
                $table->longText('content');
                $table->json('variables')->nullable(); // ['first_name', 'company', 'product']
                $table->string('dlt_template_id', 64)->nullable(); // India TRAI DLT template ID
                $table->enum('status', ['draft', 'approved', 'rejected'])->default('approved');
                $table->unsignedInteger('version')->default(1);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index(['tenant_id', 'channel', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_templates');
        Schema::dropIfExists('marketing_consents');
        Schema::dropIfExists('campaign_approvals');

        Schema::table('drip_steps', function (Blueprint $table) {
            $table->dropColumn(['step_type', 'condition_rules']);
        });

        Schema::table('drip_sequences', function (Blueprint $table) {
            $table->dropColumn(['journey_type', 'branch_id', 'target_segment_id']);
        });

        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'objective',
                'start_date',
                'end_date',
                'budget',
                'assigned_owner_id',
                'approval_status',
                'approved_by_user_id',
                'approved_at',
                'rejection_reason',
                'branch_id',
                'business_hours_only',
                'frequency_cap_days',
                'clicks_count',
                'opens_count',
                'conversions_count',
                'cost_per_lead',
                'cost_per_acquisition',
                'campaign_notes',
                'ai_drafted',
            ]);
        });
    }
};
