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
        // 1. Extend `leads` table with enterprise and Indian B2B attributes
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('leads', 'original_source')) {
                $table->string('original_source', 100)->nullable()->after('source');
            }
            if (!Schema::hasColumn('leads', 'original_campaign')) {
                $table->string('original_campaign', 100)->nullable()->after('campaign');
            }
            if (!Schema::hasColumn('leads', 'external_lead_id')) {
                $table->string('external_lead_id', 150)->nullable()->after('original_campaign')->index();
            }
            if (!Schema::hasColumn('leads', 'custom_fields_payload')) {
                $table->json('custom_fields_payload')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('leads', 'last_contacted_at')) {
                $table->timestamp('last_contacted_at')->nullable()->after('next_followup_at');
            }
            if (!Schema::hasColumn('leads', 'sla_policy_id')) {
                $table->unsignedBigInteger('sla_policy_id')->nullable()->after('last_contacted_at');
            }
            if (!Schema::hasColumn('leads', 'sla_first_response_due_at')) {
                $table->timestamp('sla_first_response_due_at')->nullable()->after('sla_policy_id');
            }
            if (!Schema::hasColumn('leads', 'sla_first_responded_at')) {
                $table->timestamp('sla_first_responded_at')->nullable()->after('sla_first_response_due_at');
            }
            if (!Schema::hasColumn('leads', 'sla_status')) {
                $table->string('sla_status', 50)->default('within_sla')->after('sla_first_responded_at'); // within_sla, breached, escalated
            }
            if (!Schema::hasColumn('leads', 'sla_escalated_at')) {
                $table->timestamp('sla_escalated_at')->nullable()->after('sla_status');
            }
            if (!Schema::hasColumn('leads', 'reassigned_count')) {
                $table->unsignedTinyInteger('reassigned_count')->default(0)->after('sla_escalated_at');
            }
        });

        // 2. Lead Routing Rules
        Schema::create('lead_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name', 150);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable(); // e.g. {"sources": ["indiamart", "website"], "cities": ["Mumbai"], "products": ["ERP"]}
            $table->string('assignment_method', 50)->default('round_robin'); // round_robin, least_active, availability, branch_based, location_based, department_based, product_based, source_based, campaign_based, manual
            $table->json('target_user_ids')->nullable(); // Array of user IDs
            $table->foreignId('target_team_id')->nullable();
            $table->foreignId('target_branch_id')->nullable();
            $table->foreignId('fallback_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedInteger('last_assigned_index')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'priority']);
        });

        // 3. Lead SLA Policies & Leakage Prevention
        Schema::create('lead_sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name', 150)->default('Standard Indian B2B Lead SLA');
            $table->boolean('is_default')->default(true);
            $table->unsignedInteger('first_response_time_minutes')->default(15);
            $table->unsignedInteger('followup_deadline_hours')->default(24);
            $table->unsignedInteger('max_idle_hours')->default(48);
            $table->boolean('working_hours_only')->default(true);
            $table->string('business_hours_start', 10)->default('09:00');
            $table->string('business_hours_end', 10)->default('18:00');
            $table->json('working_days')->nullable(); // [1,2,3,4,5,6] (Mon-Sat)
            $table->json('holiday_calendar')->nullable(); // ["2026-01-26", "2026-08-15"]
            $table->unsignedInteger('escalation_level_1_minutes')->default(15); // Rep warning
            $table->unsignedInteger('escalation_level_2_minutes')->default(45); // Manager alert & reassign
            $table->boolean('auto_reassign_on_escalation')->default(true);
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
        });

        // 4. Lead Escalation & Leakage Audit Logs
        Schema::create('lead_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->unsignedBigInteger('sla_policy_id')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(1);
            $table->string('reason', 255);
            $table->string('action_taken', 255);
            $table->foreignId('previous_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('new_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'triggered_at']);
        });

        // 5. Lead Lifecycle Stage Transition History
        Schema::create('lead_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('from_stage', 50)->nullable();
            $table->string('to_stage', 50);
            $table->unsignedInteger('time_in_previous_stage_seconds')->default(0);
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'to_stage']);
        });

        // 6. Lead Follow-Up Management (Calls, WhatsApp, Email, Meetings)
        Schema::create('lead_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('followup_type', 50)->default('call'); // call, whatsapp, email, meeting, task_reminder
            $table->timestamp('scheduled_at');
            $table->string('status', 50)->default('pending'); // pending, completed, missed, rescheduled
            $table->string('outcome', 50)->nullable(); // connected, busy, no_answer, interested, callback_requested, not_interested, meeting_scheduled
            $table->text('outcome_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('rescheduled_to_id')->nullable()->constrained('lead_followups')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });

        // 7. Lead Scoring Configurations & Thresholds
        Schema::create('lead_scoring_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->unsignedInteger('hot_threshold')->default(70);
            $table->unsignedInteger('warm_threshold')->default(40);
            $table->json('weights')->nullable(); // Weights for source, budget, location, timeline, requirements, engagement, AI
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        // 8. Lead Merge Requests (Formal review & duplicate resolution)
        Schema::create('lead_merge_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('master_lead_id')->constrained('leads')->onDelete('cascade');
            $table->foreignId('duplicate_lead_id')->constrained('leads')->onDelete('cascade');
            $table->string('status', 50)->default('pending'); // pending, approved, rejected
            $table->foreignId('requested_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('field_overrides')->nullable();
            $table->json('similarity_reasons')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 9. Indian B2B Portals & External Ingestion Interfaces
        Schema::create('lead_portal_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('portal', 50); // indiamart, justdial, tradeindia, generic_webhook, referral
            $table->string('name', 150);
            $table->string('api_key', 255)->nullable();
            $table->string('webhook_secret', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_assign')->default(true);
            $table->boolean('auto_score')->default(true);
            $table->string('default_campaign', 100)->nullable();
            $table->unsignedInteger('total_captured_count')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'portal', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_portal_integrations');
        Schema::dropIfExists('lead_merge_requests');
        Schema::dropIfExists('lead_scoring_configurations');
        Schema::dropIfExists('lead_followups');
        Schema::dropIfExists('lead_stage_histories');
        Schema::dropIfExists('lead_escalation_logs');
        Schema::dropIfExists('lead_sla_policies');
        Schema::dropIfExists('lead_routing_rules');

        Schema::table('leads', function (Blueprint $table) {
            $columns = [
                'branch_id', 'original_source', 'original_campaign', 'external_lead_id',
                'custom_fields_payload', 'last_contacted_at', 'sla_policy_id',
                'sla_first_response_due_at', 'sla_first_responded_at', 'sla_status',
                'sla_escalated_at', 'reassigned_count',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
