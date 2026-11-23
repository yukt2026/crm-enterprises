<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add project_id, branch_id, due_date to support_tickets
        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->index()->after('deal_id');
            }
            if (!Schema::hasColumn('support_tickets', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->index()->after('assigned_team_id');
            }
            if (!Schema::hasColumn('support_tickets', 'due_date')) {
                $table->timestamp('due_date')->nullable()->after('resolution_due_at');
            }
        });

        // 2. Ticket Status Lifecycle Transitions
        if (!Schema::hasTable('support_ticket_transitions')) {
            Schema::create('support_ticket_transitions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('ticket_id')->index();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40);
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 3. Customer Satisfaction & NPS Feedback
        if (!Schema::hasTable('customer_feedback')) {
            Schema::create('customer_feedback', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedTinyInteger('csat_score')->nullable(); // 1 - 5
                $table->unsignedTinyInteger('nps_score')->nullable(); // 0 - 10
                $table->string('rating_category', 50)->default('support_resolution');
                $table->text('feedback_text')->nullable();
                $table->string('channel', 30)->default('web_portal'); // email, whatsapp, web_portal, live_chat
                $table->timestamp('submitted_at')->useCurrent();
                $table->timestamps();
            });
        }

        // 4. Customer Success Health Scores & Churn Risk
        if (!Schema::hasTable('customer_health_scores')) {
            Schema::create('customer_health_scores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->decimal('health_score', 5, 2)->default(100.00); // 0 - 100
                $table->string('health_status', 30)->default('healthy'); // healthy, neutral, at_risk, critical
                $table->string('churn_risk_level', 20)->default('low'); // low, medium, high
                $table->string('onboarding_status', 30)->default('completed'); // not_started, in_progress, completed, stalled
                $table->date('renewal_date')->nullable();
                $table->decimal('contract_value', 15, 2)->default(0.00);
                $table->json('factors')->nullable(); // Explainable score breakdown
                $table->unsignedBigInteger('success_owner_id')->nullable()->index();
                $table->date('last_check_in_date')->nullable();
                $table->date('next_check_in_date')->nullable();
                $table->text('check_in_notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. Warranty & AMC (Annual Maintenance Contracts)
        if (!Schema::hasTable('warranty_contracts')) {
            Schema::create('warranty_contracts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('contract_type', 30)->default('warranty'); // warranty, amc, service_agreement
                $table->string('contract_number', 50)->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('product_name', 200)->nullable();
                $table->string('serial_number', 100)->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->text('coverage_details')->nullable();
                $table->date('renewal_date')->nullable();
                $table->decimal('renewal_price', 15, 2)->default(0.00);
                $table->string('status', 30)->default('active'); // active, expired, pending_renewal, cancelled
                $table->unsignedBigInteger('service_team_id')->nullable();
                $table->unsignedBigInteger('assigned_engineer_id')->nullable()->index();
                $table->integer('preventive_maintenance_interval_days')->default(90);
                $table->date('last_service_date')->nullable();
                $table->date('next_service_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 6. Knowledge Base Categories & Articles
        if (!Schema::hasTable('knowledge_categories')) {
            Schema::create('knowledge_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('name', 150);
                $table->string('slug', 150)->index();
                $table->text('description')->nullable();
                $table->string('icon', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('knowledge_articles')) {
            Schema::create('knowledge_articles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->string('title', 255);
                $table->string('slug', 255)->index();
                $table->longText('content');
                $table->text('excerpt')->nullable();
                $table->json('tags')->nullable();
                $table->boolean('is_published')->default(true);
                $table->string('visibility', 30)->default('public'); // public, internal, customer_portal
                $table->unsignedInteger('view_count')->default(0);
                $table->unsignedInteger('helpful_count')->default(0);
                $table->unsignedInteger('not_helpful_count')->default(0);
                $table->string('version', 20)->default('1.0');
                $table->unsignedBigInteger('author_user_id')->nullable();
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('knowledge_categories');
        Schema::dropIfExists('warranty_contracts');
        Schema::dropIfExists('customer_health_scores');
        Schema::dropIfExists('customer_feedback');
        Schema::dropIfExists('support_ticket_transitions');
    }
};
