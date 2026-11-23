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
        // 1. Multiple Pipelines per Tenant
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_default']);
            $table->index(['tenant_id', 'is_active']);
        });

        // 2. Custom Pipeline Stages with Probabilities & Required Validation
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50); // qualification, proposal, negotiation, won, lost
            $table->unsignedTinyInteger('probability')->default(20); // 0-100%
            $table->unsignedSmallInteger('order_column')->default(0);
            $table->string('color', 50)->default('#10b981');
            $table->boolean('is_won')->default(false);
            $table->boolean('is_lost')->default(false);
            $table->json('required_fields')->nullable(); // e.g. ['close_date', 'value'] or ['lost_reason', 'competitor']
            $table->unsignedSmallInteger('stale_days_threshold')->default(14); // alert if deal sits in stage > X days
            $table->timestamps();

            $table->index(['tenant_id', 'pipeline_id', 'order_column']);
            $table->index(['tenant_id', 'code']);
        });

        // 3. Enhance Deals Table with Weighted Value, Stage IDs, Competitor, Attribution & Collaborators
        Schema::table('deals', function (Blueprint $table) {
            $table->foreignId('pipeline_stage_id')->nullable()->after('pipeline_id')->constrained('pipeline_stages')->nullOnDelete();
            $table->string('currency', 3)->default('USD')->after('value');
            $table->decimal('weighted_value', 15, 2)->default(0)->after('probability');
            $table->timestamp('stage_updated_at')->nullable()->after('weighted_value');
            $table->string('competitor', 150)->nullable()->after('lost_reason');
            $table->string('source', 50)->nullable()->after('competitor');
            $table->string('campaign', 150)->nullable()->after('source');
            $table->string('utm_source', 100)->nullable()->after('campaign');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 150)->nullable()->after('utm_medium');
            $table->json('collaborator_ids')->nullable()->after('assigned_user_id');
            $table->boolean('is_archived')->default(false)->after('tags');
            $table->timestamp('won_at')->nullable()->after('is_archived');
            $table->timestamp('lost_at')->nullable()->after('won_at');

            $table->index(['tenant_id', 'pipeline_id', 'pipeline_stage_id'], 'deals_pipeline_stage_idx');
            $table->index(['tenant_id', 'is_archived']);
        });

        // 4. Deal Products / Associated Services
        Schema::create('deal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('name', 200);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'deal_id']);
        });

        // 5. Deal Stage Histories / Transition Audit Log
        Schema::create('deal_stage_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->string('from_stage_name', 100)->nullable();
            $table->string('to_stage_name', 100);
            $table->unsignedSmallInteger('days_in_previous_stage')->default(0);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'deal_id']);
        });

        // 6. Enhance Tasks with Context Links, Recurrence, Checklist & Reminders
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->after('created_by')->constrained('leads')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('lead_id')->constrained('contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->after('contact_id')->constrained('companies')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->after('company_id')->constrained('deals')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->after('deal_id')->constrained('support_tickets')->nullOnDelete();
            $table->date('start_date')->nullable()->after('due_date');
            $table->timestamp('reminder_at')->nullable()->after('start_date');
            $table->boolean('is_recurring')->default(false)->after('reminder_at');
            $table->string('recurring_rule', 50)->nullable()->after('is_recurring'); // daily, weekly, monthly
            $table->json('checklist_items')->nullable()->after('recurring_rule'); // [{id, text, completed}]
            $table->timestamp('completed_at')->nullable()->after('checklist_items');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');

            $table->index(['tenant_id', 'assigned_user_id', 'status'], 'tasks_tenant_user_status_idx');
            $table->index(['tenant_id', 'due_date'], 'tasks_tenant_due_date_idx');
            $table->index(['tenant_id', 'deal_id'], 'tasks_tenant_deal_idx');
        });

        // 7. Sales Teams
        Schema::create('sales_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('member_ids')->nullable(); // Array of user IDs
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'leader_id']);
        });

        // 8. Sales Quotas & Targets
        Schema::create('sales_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sales_team_id')->nullable()->constrained('sales_teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('period', 20)->default('monthly'); // monthly, quarterly, yearly
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('target_revenue', 15, 2)->default(0);
            $table->unsignedInteger('target_deals_count')->default(0);
            $table->unsignedInteger('target_leads_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'start_date']);
            $table->index(['tenant_id', 'sales_team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_quotas');
        Schema::dropIfExists('sales_teams');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_tenant_user_status_idx');
            $table->dropIndex('tasks_tenant_due_date_idx');
            $table->dropIndex('tasks_tenant_deal_idx');
            $table->dropColumn([
                'created_by', 'lead_id', 'contact_id', 'company_id', 'deal_id', 'ticket_id',
                'start_date', 'reminder_at', 'is_recurring', 'recurring_rule',
                'checklist_items', 'completed_at', 'cancelled_at'
            ]);
        });

        Schema::dropIfExists('deal_stage_histories');
        Schema::dropIfExists('deal_products');

        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex('deals_pipeline_stage_idx');
            $table->dropColumn([
                'pipeline_stage_id', 'currency', 'weighted_value', 'stage_updated_at',
                'competitor', 'source', 'campaign', 'utm_source', 'utm_medium',
                'utm_campaign', 'collaborator_ids', 'is_archived', 'won_at', 'lost_at'
            ]);
        });

        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }
};
