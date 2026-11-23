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
        // 1. Extend `pipelines` with Owner, Business Model & Branch Scoping
        Schema::table('pipelines', function (Blueprint $table) {
            if (!Schema::hasColumn('pipelines', 'owner_user_id')) {
                $table->foreignId('owner_user_id')->nullable()->after('description')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('pipelines', 'business_model')) {
                $table->string('business_model', 64)->nullable()->after('owner_user_id'); // b2b_enterprise, b2c_retail, channel_partner, gov_tender
            }
            if (!Schema::hasColumn('pipelines', 'department')) {
                $table->string('department', 64)->nullable()->after('business_model');
            }
            if (!Schema::hasColumn('pipelines', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('department')->index();
            }
        });

        // 2. Extend `pipeline_stages` with Allowed Transitions & Approval Flags
        Schema::table('pipeline_stages', function (Blueprint $table) {
            if (!Schema::hasColumn('pipeline_stages', 'allowed_transitions')) {
                $table->json('allowed_transitions')->nullable()->after('required_fields'); // array of allowed stage codes or IDs
            }
            if (!Schema::hasColumn('pipeline_stages', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false)->after('allowed_transitions');
            }
            if (!Schema::hasColumn('pipeline_stages', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('order_column');
            }
        });

        // 3. Extend `deals` with Customer, Branch, Sales Team, Followup & Project Conversion
        Schema::table('deals', function (Blueprint $table) {
            if (!Schema::hasColumn('deals', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('contact_id')->constrained('customers')->onDelete('set null');
            }
            if (!Schema::hasColumn('deals', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('assigned_user_id')->index();
            }
            if (!Schema::hasColumn('deals', 'sales_team')) {
                $table->string('sales_team', 64)->nullable()->after('branch_id');
            }
            if (!Schema::hasColumn('deals', 'notes')) {
                $table->text('notes')->nullable()->after('competitor');
            }
            if (!Schema::hasColumn('deals', 'next_follow_up_at')) {
                $table->timestamp('next_follow_up_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('deals', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('lost_at');
            }
            if (!Schema::hasColumn('deals', 'reopened_by_user_id')) {
                $table->foreignId('reopened_by_user_id')->nullable()->after('reopened_at')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('deals', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('reopened_by_user_id')->index();
            }
        });

        // 4. Extend `quotations` with Approval Status, Template & Invoice Conversion Guard
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'template_name')) {
                $table->string('template_name', 64)->default('classic')->after('status');
            }
            if (!Schema::hasColumn('quotations', 'approval_status')) {
                $table->string('approval_status', 32)->default('not_submitted')->after('template_name')->index(); // not_submitted, pending_approval, approved, rejected
            }
            if (!Schema::hasColumn('quotations', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('approval_status')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('quotations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            }
            if (!Schema::hasColumn('quotations', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('quotations', 'converted_to_invoice_id')) {
                $table->foreignId('converted_to_invoice_id')->nullable()->after('rejection_reason')->constrained('invoices')->onDelete('set null');
            }
            if (!Schema::hasColumn('quotations', 'converted_to_invoice_at')) {
                $table->timestamp('converted_to_invoice_at')->nullable()->after('converted_to_invoice_id');
            }
            if (!Schema::hasColumn('quotations', 'sent_via')) {
                $table->string('sent_via', 32)->nullable()->after('converted_to_invoice_at'); // email, whatsapp, portal
            }
            if (!Schema::hasColumn('quotations', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('sent_via');
            }
            if (!Schema::hasColumn('quotations', 'customer_action_ip')) {
                $table->string('customer_action_ip', 64)->nullable()->after('sent_at');
            }
            if (!Schema::hasColumn('quotations', 'customer_action_notes')) {
                $table->text('customer_action_notes')->nullable()->after('customer_action_ip');
            }
        });

        // 5. Discount Approval Rules & Requests
        if (!Schema::hasTable('discount_rules')) {
            Schema::create('discount_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->string('role_name', 64)->default('Sales Executive');
                $table->decimal('max_discount_percentage', 5, 2)->default(10.00); // e.g. 10% self-approval
                $table->decimal('min_deal_value', 15, 2)->default(0.00);
                $table->boolean('requires_manager_approval')->default(true);
                $table->boolean('requires_admin_approval')->default(false); // for > 20%
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'role_name']);
            });
        }

        if (!Schema::hasTable('discount_approvals')) {
            Schema::create('discount_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('quotation_id')->nullable()->constrained('quotations')->onDelete('cascade');
                $table->foreignId('deal_id')->nullable()->constrained('deals')->onDelete('cascade');
                $table->foreignId('requested_by_user_id')->constrained('users')->onDelete('cascade');
                $table->decimal('discount_rate', 5, 2)->default(0.00);
                $table->decimal('discount_amount', 15, 2)->default(0.00);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
                $table->foreignId('approver_user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->text('approver_comments')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        // 6. Contract & Proposal Templates
        if (!Schema::hasTable('contract_templates')) {
            Schema::create('contract_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->string('name', 191);
                $table->enum('type', ['proposal', 'contract', 'nda', 'sla'])->default('contract');
                $table->longText('content');
                $table->json('variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index(['tenant_id', 'type']);
            });
        }

        // 7. Contracts, Proposals & E-Signatures
        if (!Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('deal_id')->nullable()->constrained('deals')->onDelete('set null');
                $table->foreignId('quotation_id')->nullable()->constrained('quotations')->onDelete('set null');
                $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
                $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null');
                $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('set null');
                $table->string('title', 255);
                $table->enum('contract_type', ['proposal', 'contract', 'nda', 'sla'])->default('contract');
                $table->string('contract_number', 64)->unique();
                $table->string('status', 32)->default('draft')->index(); // draft, pending_approval, sent, signed, rejected, active, expired, terminated
                $table->unsignedInteger('document_version')->default(1);
                $table->longText('terms_conditions')->nullable();
                $table->decimal('contract_value', 15, 2)->default(0.00);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('auto_renew')->default(false);
                $table->unsignedInteger('renewal_notice_days')->default(30);
                $table->enum('e_signature_provider', ['aadhaar_esign', 'docusign', 'internal_otp', 'clickwrap'])->default('clickwrap');
                $table->enum('signature_status', ['unsigned', 'sent_for_signature', 'signed', 'declined'])->default('unsigned')->index();
                $table->string('signer_name', 191)->nullable();
                $table->string('signer_email', 191)->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('audit_trail_hash', 128)->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'deal_id']);
                $table->index(['tenant_id', 'status', 'signature_status']);
            });
        }

        // 8. Sales Commission Plans
        if (!Schema::hasTable('commission_plans')) {
            Schema::create('commission_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->string('name', 191);
                $table->enum('plan_type', ['fixed', 'percentage', 'tiered'])->default('percentage');
                $table->enum('calculation_basis', ['deal_won', 'payment_collected'])->default('deal_won');
                $table->decimal('rate_percentage', 5, 2)->default(5.00); // e.g. 5%
                $table->decimal('fixed_amount', 12, 2)->default(0.00);
                $table->json('tier_rules')->nullable(); // [{"min": 0, "max": 100000, "rate": 5}, {"min": 100000, "rate": 8}]
                $table->decimal('target_quota', 15, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 9. Sales Commissions Ledger
        if (!Schema::hasTable('sales_commissions')) {
            Schema::create('sales_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('commission_plan_id')->nullable()->constrained('commission_plans')->onDelete('set null');
                $table->foreignId('deal_id')->nullable()->constrained('deals')->onDelete('set null');
                $table->foreignId('quotation_id')->nullable()->constrained('quotations')->onDelete('set null');
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
                $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
                $table->decimal('base_amount', 15, 2)->default(0.00);
                $table->decimal('commission_rate', 5, 2)->default(0.00);
                $table->decimal('commission_amount', 15, 2)->default(0.00);
                $table->enum('calculation_basis', ['deal_won', 'payment_collected'])->default('deal_won');
                $table->enum('status', ['pending_approval', 'approved', 'paid', 'reversed'])->default('pending_approval')->index();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('approved_at')->nullable();
                $table->date('payout_date')->nullable();
                $table->text('reversal_reason')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'user_id', 'status']);
                $table->index(['tenant_id', 'deal_id']);
                $table->index(['tenant_id', 'payment_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_commissions');
        Schema::dropIfExists('commission_plans');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('discount_approvals');
        Schema::dropIfExists('discount_rules');

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'template_name',
                'approval_status',
                'approved_by_user_id',
                'approved_at',
                'rejection_reason',
                'converted_to_invoice_id',
                'converted_to_invoice_at',
                'sent_via',
                'sent_at',
                'customer_action_ip',
                'customer_action_notes',
            ]);
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn([
                'customer_id',
                'branch_id',
                'sales_team',
                'notes',
                'next_follow_up_at',
                'reopened_at',
                'reopened_by_user_id',
                'project_id',
            ]);
        });

        Schema::table('pipeline_stages', function (Blueprint $table) {
            $table->dropColumn(['allowed_transitions', 'requires_approval', 'is_default']);
        });

        Schema::table('pipelines', function (Blueprint $table) {
            $table->dropColumn(['owner_user_id', 'business_model', 'department', 'branch_id']);
        });
    }
};
