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
        // 1. Support Ticket Categories
        if (!Schema::hasTable('support_ticket_categories')) {
            Schema::create('support_ticket_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('support_ticket_categories')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 2. Support SLA Policies
        if (!Schema::hasTable('support_sla_policies')) {
            Schema::create('support_sla_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('all'); // all, urgent, high, medium, low
                $table->unsignedInteger('response_target_minutes')->default(60); // 1 hour
                $table->unsignedInteger('resolution_target_minutes')->default(480); // 8 hours
                $table->boolean('business_hours_only')->default(true);
                $table->unsignedTinyInteger('escalation_threshold_percent')->default(80);
                $table->foreignId('escalate_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'priority']);
            });
        }

        // 3. Enhance Support Tickets
        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'ticket_number')) {
                $table->string('ticket_number', 50)->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('support_tickets', 'contact_id')) {
                $table->foreignId('contact_id')->nullable()->after('customer_id')->constrained('contacts')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('contact_id')->constrained('companies')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'source_channel')) {
                $table->string('source_channel', 50)->default('web_chat')->after('company_id'); // email, whatsapp, web_chat, phone, portal
            }
            if (!Schema::hasColumn('support_tickets', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('source_channel')->constrained('support_ticket_categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'subcategory')) {
                $table->string('subcategory', 150)->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('support_tickets', 'assigned_team_id')) {
                $table->foreignId('assigned_team_id')->nullable()->after('assigned_user_id')->constrained('sales_teams')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->after('assigned_team_id')->constrained('leads')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'deal_id')) {
                $table->foreignId('deal_id')->nullable()->after('lead_id')->constrained('deals')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('deal_id')->constrained('invoices')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'tags')) {
                $table->json('tags')->nullable()->after('invoice_id');
            }
            if (!Schema::hasColumn('support_tickets', 'attachments')) {
                $table->json('attachments')->nullable()->after('tags');
            }
            if (!Schema::hasColumn('support_tickets', 'sla_policy_id')) {
                $table->foreignId('sla_policy_id')->nullable()->after('attachments')->constrained('support_sla_policies')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'first_response_due_at')) {
                $table->timestamp('first_response_due_at')->nullable()->after('sla_policy_id');
            }
            if (!Schema::hasColumn('support_tickets', 'resolution_due_at')) {
                $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            }
            if (!Schema::hasColumn('support_tickets', 'first_responded_at')) {
                $table->timestamp('first_responded_at')->nullable()->after('resolution_due_at');
            }
            if (!Schema::hasColumn('support_tickets', 'sla_status')) {
                $table->string('sla_status', 30)->default('within_sla')->after('first_responded_at'); // within_sla, warning, breached
            }
            if (!Schema::hasColumn('support_tickets', 'escalated_to_user_id')) {
                $table->foreignId('escalated_to_user_id')->nullable()->after('sla_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('support_tickets', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable()->after('escalated_to_user_id');
            }
            if (!Schema::hasColumn('support_tickets', 'escalation_reason')) {
                $table->string('escalation_reason', 255)->nullable()->after('escalated_at');
            }
            if (!Schema::hasColumn('support_tickets', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable()->after('escalation_reason');
            }
            if (!Schema::hasColumn('support_tickets', 'closure_reason')) {
                $table->string('closure_reason', 255)->nullable()->after('resolution_notes');
            }
            if (!Schema::hasColumn('support_tickets', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('closure_reason');
            }
            if (!Schema::hasColumn('support_tickets', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('resolved_at');
            }
            if (!Schema::hasColumn('support_tickets', 'reopen_count')) {
                $table->unsignedInteger('reopen_count')->default(0)->after('closed_at');
            }
            if (!Schema::hasColumn('support_tickets', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('reopen_count');
            }
            if (!Schema::hasColumn('support_tickets', 'reopen_reason')) {
                $table->string('reopen_reason', 255)->nullable()->after('reopened_at');
            }
            if (!Schema::hasColumn('support_tickets', 'csat_rating')) {
                $table->unsignedTinyInteger('csat_rating')->nullable()->after('reopen_reason'); // 1-5
            }
            if (!Schema::hasColumn('support_tickets', 'csat_comment')) {
                $table->text('csat_comment')->nullable()->after('csat_rating');
            }
            if (!Schema::hasColumn('support_tickets', 'ai_summary')) {
                $table->text('ai_summary')->nullable()->after('csat_comment');
            }
            if (!Schema::hasColumn('support_tickets', 'ai_troubleshooting_steps')) {
                $table->text('ai_troubleshooting_steps')->nullable()->after('ai_summary');
            }
            if (!Schema::hasColumn('support_tickets', 'ai_suggested_category')) {
                $table->string('ai_suggested_category', 100)->nullable()->after('ai_troubleshooting_steps');
            }
            if (!Schema::hasColumn('support_tickets', 'ai_confidence_score')) {
                $table->decimal('ai_confidence_score', 4, 2)->nullable()->after('ai_suggested_category');
            }
            if (!Schema::hasColumn('support_tickets', 'ai_auto_reply_enabled')) {
                $table->boolean('ai_auto_reply_enabled')->default(false)->after('ai_confidence_score');
            }

            $table->string('priority', 20)->default('medium')->change();
            $table->string('status', 40)->default('new')->change();
        });

        // Add unique index on tenant_id + ticket_number if not already exists
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->index(['tenant_id', 'ticket_number']);
            $table->index(['tenant_id', 'sla_status']);
        });

        // 4. Support Ticket Messages
        if (!Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->string('sender_type', 30)->default('user'); // user, customer, system, ai
                $table->unsignedBigInteger('sender_id')->nullable();
                $table->longText('message');
                $table->boolean('is_internal')->default(false); // internal note vs customer reply
                $table->string('channel', 50)->default('web_chat'); // email, whatsapp, web_chat, internal
                $table->json('attachments')->nullable();
                $table->boolean('is_ai_generated')->default(false);
                $table->boolean('is_draft')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'ticket_id']);
                $table->index(['ticket_id', 'is_internal']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_sla_policies');
        Schema::dropIfExists('support_ticket_categories');
    }
};
