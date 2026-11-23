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
        // 1. Workflow Automation Usage Limits per Tenant
        if (!Schema::hasTable('workflow_automation_limits')) {
            Schema::create('workflow_automation_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedInteger('max_active_workflows')->default(50);
                $table->unsignedInteger('max_monthly_executions')->default(50000);
                $table->unsignedInteger('current_month_executions')->default(0);
                $table->unsignedInteger('max_external_api_calls')->default(5000);
                $table->unsignedInteger('max_whatsapp_actions')->default(10000);
                $table->unsignedInteger('max_email_actions')->default(25000);
                $table->unsignedInteger('max_sms_actions')->default(10000);
                $table->unsignedInteger('max_ai_actions')->default(2000);
                $table->unsignedInteger('max_delayed_tasks')->default(1000);
                $table->timestamp('last_reset_at')->nullable();
                $table->timestamps();

                $table->unique('tenant_id');
            });
        }

        // 2. Workflow Human Approval Workflows
        if (!Schema::hasTable('workflow_approvals')) {
            Schema::create('workflow_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
                $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
                $table->string('node_id', 80);
                $table->string('title', 150);
                $table->string('category', 50)->default('general'); // discount, refund, expense, customer_update, campaign, record_transfer, financial, general
                $table->string('status', 30)->default('pending'); // pending, approved, rejected, expired
                $table->string('approver_role', 50)->nullable(); // admin, sales_manager, finance_manager, branch_manager
                $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action_type', 80)->nullable();
                $table->json('action_params')->nullable();
                $table->json('context_summary')->nullable();
                $table->text('comments')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('decision_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decision_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['workflow_run_id', 'node_id']);
                $table->index(['approver_role', 'status']);
            });
        }

        // 3. Enhance workflow_runs with loop protection & lineage columns
        Schema::table('workflow_runs', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_runs', 'parent_run_id')) {
                $table->unsignedBigInteger('parent_run_id')->nullable()->after('workflow_version_id');
            }
            if (!Schema::hasColumn('workflow_runs', 'depth')) {
                $table->unsignedTinyInteger('depth')->default(1)->after('parent_run_id');
            }
            if (!Schema::hasColumn('workflow_runs', 'is_dry_run')) {
                $table->boolean('is_dry_run')->default(false)->after('status');
            }
            if (!Schema::hasColumn('workflow_runs', 'execution_duration_ms')) {
                $table->unsignedInteger('execution_duration_ms')->default(0)->after('error_message');
            }
            if (!Schema::hasColumn('workflow_runs', 'approval_id')) {
                $table->unsignedBigInteger('approval_id')->nullable()->after('current_node_id');
            }
        });

        // 4. Enhance workflows table with execution preferences
        Schema::table('workflows', function (Blueprint $table) {
            if (!Schema::hasColumn('workflows', 'execution_mode')) {
                $table->string('execution_mode', 20)->default('sync')->after('trigger_type'); // sync, async
            }
            if (!Schema::hasColumn('workflows', 'timezone')) {
                $table->string('timezone', 50)->default('Asia/Kolkata')->after('execution_mode');
            }
            if (!Schema::hasColumn('workflows', 'working_hours_only')) {
                $table->boolean('working_hours_only')->default(false)->after('timezone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['execution_mode', 'timezone', 'working_hours_only']);
        });

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropColumn(['parent_run_id', 'depth', 'is_dry_run', 'execution_duration_ms', 'approval_id']);
        });

        Schema::dropIfExists('workflow_approvals');
        Schema::dropIfExists('workflow_automation_limits');
    }
};
