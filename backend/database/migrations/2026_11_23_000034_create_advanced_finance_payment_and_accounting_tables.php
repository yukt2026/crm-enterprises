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
        // 1. Payment Gateways Configuration
        if (!Schema::hasTable('payment_gateways_config')) {
            Schema::create('payment_gateways_config', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('provider', 50); // razorpay, cashfree, upi_qr, bank_transfer
                $table->string('display_name', 100);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_test_mode')->default(false);
                $table->json('credentials')->nullable();
                $table->string('upi_vpa', 150)->nullable();
                $table->string('merchant_name', 150)->nullable();
                $table->json('bank_account_details')->nullable();
                $table->string('webhook_secret', 255)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'provider']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 2. Payment Links
        if (!Schema::hasTable('payment_links')) {
            Schema::create('payment_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('link_token', 64)->unique();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->nullableMorphs('payable'); // Invoice, Deal, Quotation, Project, etc.
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->enum('amount_type', ['fixed', 'partial', 'advance', 'due_amount'])->default('due_amount');
                $table->decimal('amount', 15, 2);
                $table->decimal('min_partial_amount', 15, 2)->nullable();
                $table->string('currency', 10)->default('INR');
                $table->dateTime('expires_at')->nullable();
                $table->string('gateway_provider', 50)->default('razorpay');
                $table->string('gateway_link_id', 150)->nullable();
                $table->string('gateway_link_url', 500)->nullable();
                $table->text('upi_qr_payload')->nullable();
                $table->enum('status', ['active', 'paid', 'partially_paid', 'expired', 'cancelled'])->default('active');
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->dateTime('customer_notified_at')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'link_token']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 3. Centralized Transaction Register (Finance Transactions / Ledger)
        if (!Schema::hasTable('finance_transactions')) {
            Schema::create('finance_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('transaction_number', 50)->unique();
                $table->string('internal_transaction_id', 64)->unique();
                $table->string('provider_transaction_id', 150)->nullable();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->string('gateway', 50); // razorpay, cashfree, upi, bank_transfer, cash
                $table->string('payment_method', 50)->default('bank_transfer'); // upi, card, netbanking, wallet, bank_transfer, cash
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10)->default('INR');
                $table->decimal('fee_amount', 15, 2)->default(0);
                $table->decimal('tax_on_fee', 15, 2)->default(0);
                $table->decimal('net_amount', 15, 2);
                $table->date('transaction_date');
                $table->string('provider_status', 50)->default('captured');
                $table->string('internal_status', 50)->default('posted'); // posted, pending, failed, reversed
                $table->string('reconciliation_status', 50)->default('unreconciled'); // matched, unreconciled, discrepancy, manual_override
                $table->dateTime('reconciled_at')->nullable();
                $table->foreignId('reconciled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('refund_reference', 100)->nullable();
                $table->string('webhook_event_id', 150)->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_posted')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'transaction_number']);
                $table->index(['tenant_id', 'webhook_event_id']);
                $table->index(['tenant_id', 'reconciliation_status']);
                $table->index(['tenant_id', 'internal_status']);
            });
        }

        // 4. Payment Allocations (for partial and advance payments)
        if (!Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->decimal('allocated_amount', 15, 2);
                $table->dateTime('allocated_at');
                $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'payment_id']);
                $table->index(['tenant_id', 'invoice_id']);
            });
        }

        // 5. Invoices Enhancements (Accounts Receivable, Overdue & Dispute Tracking)
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'promise_to_pay_date')) {
                $table->date('promise_to_pay_date')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('invoices', 'customer_commitment_notes')) {
                $table->text('customer_commitment_notes')->nullable()->after('promise_to_pay_date');
            }
            if (!Schema::hasColumn('invoices', 'is_disputed')) {
                $table->boolean('is_disputed')->default(false)->after('customer_commitment_notes');
            }
            if (!Schema::hasColumn('invoices', 'dispute_reason')) {
                $table->string('dispute_reason', 255)->nullable()->after('is_disputed');
            }
            if (!Schema::hasColumn('invoices', 'dispute_status')) {
                $table->string('dispute_status', 50)->nullable()->after('dispute_reason');
            }
            if (!Schema::hasColumn('invoices', 'collection_user_id')) {
                $table->foreignId('collection_user_id')->nullable()->after('dispute_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'recovery_status')) {
                $table->string('recovery_status', 50)->default('current')->after('collection_user_id'); // current, follow_up_needed, promise_to_pay, escalated, legal_notice, written_off
            }
        });

        // 6. Vendor Bills (Accounts Payable)
        if (!Schema::hasTable('vendor_bills')) {
            Schema::create('vendor_bills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('bill_number', 50);
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
                $table->date('bill_date');
                $table->date('due_date');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->decimal('outstanding_amount', 15, 2)->default(0);
                $table->string('currency', 10)->default('INR');
                $table->string('status', 50)->default('draft'); // draft, pending_approval, approved, partially_paid, paid, overdue, disputed, cancelled
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->string('file_attachment', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'bill_number']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'due_date']);
            });
        }

        // 7. Vendor Payments
        if (!Schema::hasTable('vendor_payments')) {
            Schema::create('vendor_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('payment_number', 50);
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('vendor_bill_id')->nullable()->constrained('vendor_bills')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10)->default('INR');
                $table->string('payment_method', 50)->default('bank_transfer'); // bank_transfer, upi, cheque, cash
                $table->date('payment_date');
                $table->string('reference_number', 100)->nullable(); // UTR / Cheque No
                $table->string('status', 50)->default('pending_approval'); // pending_approval, approved, disbursed, failed, cancelled
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('disbursed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'payment_number']);
                $table->index(['tenant_id', 'supplier_id']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 8. Refunds Enhancement (Approval policy & credit note linkage)
        Schema::table('refunds', function (Blueprint $table) {
            if (!Schema::hasColumn('refunds', 'approval_status')) {
                $table->string('approval_status', 50)->default('approved')->after('status'); // pending_approval, approved, rejected
            }
            if (!Schema::hasColumn('refunds', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('refunds', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('approved_by_user_id');
            }
            if (!Schema::hasColumn('refunds', 'rejection_reason')) {
                $table->string('rejection_reason', 255)->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('refunds', 'credit_note_id')) {
                $table->foreignId('credit_note_id')->nullable()->after('rejection_reason')->constrained('credit_notes')->nullOnDelete();
            }
        });

        // 9. Expenses & Reimbursements Management
        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('expense_number', 50)->unique();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('category', 50); // travel, office_supplies, software_saas, marketing, utilities, professional_services, meals_entertainment, other
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->date('expense_date');
                $table->decimal('amount', 15, 2);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->boolean('is_tax_inclusive')->default(false);
                $table->string('currency', 10)->default('INR');
                $table->string('receipt_url', 500)->nullable();
                $table->boolean('is_reimbursement')->default(false);
                $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 50)->default('pending_approval'); // draft, pending_approval, approved, rejected, reimbursed, paid
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->string('rejection_reason', 255)->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'expense_number']);
                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'category']);
                $table->index(['tenant_id', 'expense_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropForeign(['credit_note_id']);
            $table->dropColumn(['approval_status', 'approved_by_user_id', 'approved_at', 'rejection_reason', 'credit_note_id']);
        });
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_bills');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['collection_user_id']);
            $table->dropColumn([
                'promise_to_pay_date',
                'customer_commitment_notes',
                'is_disputed',
                'dispute_reason',
                'dispute_status',
                'collection_user_id',
                'recovery_status'
            ]);
        });
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('payment_links');
        Schema::dropIfExists('payment_gateways_config');
    }
};
