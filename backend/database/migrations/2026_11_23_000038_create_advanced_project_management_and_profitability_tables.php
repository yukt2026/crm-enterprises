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
        // 1. Enhance Projects Table
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'code')) {
                $table->string('code', 50)->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('projects', 'deal_id')) {
                $table->foreignId('deal_id')->nullable()->after('company_id')->constrained('deals')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'quotation_id')) {
                $table->foreignId('quotation_id')->nullable()->after('deal_id')->constrained('quotations')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('quotation_id');
            }
            if (!Schema::hasColumn('projects', 'manager_user_id')) {
                $table->foreignId('manager_user_id')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'labor_cost_rate')) {
                $table->decimal('labor_cost_rate', 10, 2)->default(0)->after('budget');
            }
            if (!Schema::hasColumn('projects', 'billing_rate')) {
                $table->decimal('billing_rate', 10, 2)->default(0)->after('labor_cost_rate');
            }
            if (!Schema::hasColumn('projects', 'client_portal_enabled')) {
                $table->boolean('client_portal_enabled')->default(true)->after('progress');
            }
            if (!Schema::hasColumn('projects', 'notes')) {
                $table->text('notes')->nullable()->after('description');
            }

            $table->index(['tenant_id', 'deal_id'], 'prj_tenant_deal_idx');
            $table->index(['tenant_id', 'code'], 'prj_tenant_code_idx');
        });

        // 2. Project Status History Table
        if (!Schema::hasTable('project_status_histories')) {
            Schema::create('project_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('from_status', 50)->nullable();
                $table->string('to_status', 50);
                $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'project_id'], 'prj_status_hist_idx');
            });
        }

        // 3. Enhance Tasks Table
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'parent_task_id')) {
                $table->foreignId('parent_task_id')->nullable()->after('project_id')->constrained('tasks')->nullOnDelete();
            }
            if (!Schema::hasColumn('tasks', 'milestone_id')) {
                $table->foreignId('milestone_id')->nullable()->after('parent_task_id')->constrained('project_milestones')->nullOnDelete();
            }
            if (!Schema::hasColumn('tasks', 'labels')) {
                $table->json('labels')->nullable()->after('priority');
            }
            if (!Schema::hasColumn('tasks', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('status');
            }
            if (!Schema::hasColumn('tasks', 'block_reason')) {
                $table->text('block_reason')->nullable()->after('is_blocked');
            }
            if (!Schema::hasColumn('tasks', 'order_index')) {
                $table->integer('order_index')->default(0)->after('status');
            }

            $table->index(['tenant_id', 'parent_task_id'], 'tasks_parent_idx');
            $table->index(['tenant_id', 'milestone_id'], 'tasks_milestone_idx');
        });

        // 4. Task Assignees Table (Multiple Assignees)
        if (!Schema::hasTable('task_assignees')) {
            Schema::create('task_assignees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('assigned_at')->useCurrent();

                $table->unique(['task_id', 'user_id'], 'task_user_unique');
                $table->index(['tenant_id', 'user_id'], 'task_assignees_tenant_user_idx');
            });
        }

        // 5. Enhance Task Dependencies Table
        Schema::table('task_dependencies', function (Blueprint $table) {
            if (!Schema::hasColumn('task_dependencies', 'lag_days')) {
                $table->integer('lag_days')->default(0)->after('dependency_type');
            }
        });

        // 6. Enhance Project Milestones Table
        Schema::table('project_milestones', function (Blueprint $table) {
            if (!Schema::hasColumn('project_milestones', 'amount')) {
                $table->decimal('amount', 15, 2)->default(0)->after('description');
            }
            if (!Schema::hasColumn('project_milestones', 'billing_percentage')) {
                $table->decimal('billing_percentage', 5, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('project_milestones', 'deliverables_summary')) {
                $table->text('deliverables_summary')->nullable()->after('description');
            }
            if (!Schema::hasColumn('project_milestones', 'approval_status')) {
                $table->string('approval_status', 50)->default('pending_review')->after('status');
            }
            if (!Schema::hasColumn('project_milestones', 'client_review_token')) {
                $table->string('client_review_token', 64)->nullable()->unique()->after('approval_status');
            }
            if (!Schema::hasColumn('project_milestones', 'client_feedback')) {
                $table->text('client_feedback')->nullable()->after('client_review_token');
            }
            if (!Schema::hasColumn('project_milestones', 'internal_reviewed_by')) {
                $table->foreignId('internal_reviewed_by')->nullable()->after('client_feedback')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('project_milestones', 'internal_reviewed_at')) {
                $table->timestamp('internal_reviewed_at')->nullable()->after('internal_reviewed_by');
            }
            if (!Schema::hasColumn('project_milestones', 'client_approved_at')) {
                $table->timestamp('client_approved_at')->nullable()->after('internal_reviewed_at');
            }
            if (!Schema::hasColumn('project_milestones', 'order_index')) {
                $table->integer('order_index')->default(0)->after('status');
            }

            $table->index(['tenant_id', 'approval_status'], 'prj_milestones_appr_idx');
        });

        // 7. Milestone Approval Logs Table
        if (!Schema::hasTable('milestone_approval_logs')) {
            Schema::create('milestone_approval_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('milestone_id')->constrained('project_milestones')->cascadeOnDelete();
                $table->string('actor_type', 30)->default('internal'); // internal, client
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name', 150)->nullable();
                $table->string('action', 50); // submitted, internal_approved, sent_to_client, client_approved, revision_requested, rejected
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'milestone_id'], 'milestone_appr_logs_idx');
            });
        }

        // 8. Enhance Task Time Logs Table
        Schema::table('task_time_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('task_time_logs', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('task_id')->constrained('projects')->nullOnDelete();
            }
            if (!Schema::hasColumn('task_time_logs', 'hourly_rate')) {
                $table->decimal('hourly_rate', 10, 2)->default(0)->after('duration_minutes');
            }
            if (!Schema::hasColumn('task_time_logs', 'billable_amount')) {
                $table->decimal('billable_amount', 10, 2)->default(0)->after('hourly_rate');
            }
            if (!Schema::hasColumn('task_time_logs', 'is_running')) {
                $table->boolean('is_running')->default(false)->after('is_billable');
            }
            if (!Schema::hasColumn('task_time_logs', 'status')) {
                $table->string('status', 30)->default('approved')->after('is_running'); // pending, approved, rejected
            }
            if (!Schema::hasColumn('task_time_logs', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('task_time_logs', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }
            if (!Schema::hasColumn('task_time_logs', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }

            $table->index(['tenant_id', 'project_id'], 'time_logs_project_idx');
            $table->index(['tenant_id', 'status'], 'time_logs_status_idx');
        });

        // 9. Project Documents Table (Client vs Internal File Vault)
        if (!Schema::hasTable('project_documents')) {
            Schema::create('project_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title', 150);
                $table->string('file_name', 255);
                $table->string('file_path', 500);
                $table->unsignedInteger('file_size_kb')->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->string('category', 50)->default('deliverable'); // deliverable, design, agreement, spec, other
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_client_visible')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id', 'is_client_visible'], 'prj_docs_vis_idx');
            });
        }

        // 10. Link Invoices to Projects
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'project_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('deal_id')->constrained('projects')->nullOnDelete();
                $table->index(['tenant_id', 'project_id'], 'invoices_project_idx');
            });
        }

        // 11. Link Expenses to Projects
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('expenses', 'project_id')) {
                    $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->nullOnDelete();
                    $table->index(['tenant_id', 'project_id'], 'expenses_project_idx');
                }
                if (!Schema::hasColumn('expenses', 'customer_id')) {
                    $table->foreignId('customer_id')->nullable()->after('project_id')->constrained('customers')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('milestone_approval_logs');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('project_status_histories');
    }
};
