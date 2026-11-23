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
        // 1. Workflows Master Table
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('category', 50)->default('crm'); // crm, sales, marketing, hr, billing, support
            $table->string('status', 30)->default('draft'); // draft, published, paused
            $table->string('trigger_type', 80); // lead.created, deal.won, schedule.cron, etc.
            $table->unsignedInteger('current_version')->default(1);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('total_runs_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'trigger_type']);
            $table->index(['tenant_id', 'is_active']);
        });

        // 2. Workflow Versions (Immutable Version Control)
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('definition'); // trigger, nodes, edges, positions
            $table->string('changelog', 255)->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workflow_id', 'version']);
            $table->index(['tenant_id', 'workflow_id']);
        });

        // 3. Workflow Execution Runs
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions')->cascadeOnDelete();
            $table->string('trigger_event', 80);
            $table->string('idempotency_key', 120)->nullable();
            $table->string('entity_type', 50)->nullable(); // lead, deal, invoice, ticket, etc.
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status', 30)->default('pending'); // pending, running, waiting, completed, failed, cancelled
            $table->string('current_node_id', 80)->nullable();
            $table->timestamp('resume_at')->nullable(); // for delay timers
            $table->string('wait_for_event', 80)->nullable(); // for event pause nodes
            $table->timestamp('wait_timeout_at')->nullable();
            $table->json('trigger_payload')->nullable();
            $table->json('context_data')->nullable(); // Accumulated execution variables & history
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'idempotency_key']);
            $table->index(['resume_at', 'status']);
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
        });

        // 4. Workflow Step-by-Step Execution Logs
        Schema::create('workflow_step_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->string('node_id', 80);
            $table->string('node_type', 50); // trigger, condition, action, delay, wait, stop
            $table->string('node_name', 150)->nullable();
            $table->string('status', 30)->default('running'); // running, completed, skipped, failed, waiting
            $table->json('input_state')->nullable();
            $table->json('output_state')->nullable();
            $table->string('branch_taken', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('execution_duration_ms')->default(0);
            $table->timestamps();

            $table->index(['workflow_run_id', 'node_id']);
            $table->index(['tenant_id', 'status']);
        });

        // 5. Pre-Built Reusable Workflow Templates
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 50)->default('crm');
            $table->text('description')->nullable();
            $table->string('trigger_type', 80);
            $table->json('definition');
            $table->boolean('is_system')->default(true);
            $table->timestamps();

            $table->index(['category']);
            $table->index(['trigger_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
        Schema::dropIfExists('workflow_step_logs');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflows');
    }
};
