<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Modify Quotations Status if MySQL
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE quotations MODIFY COLUMN status VARCHAR(30) DEFAULT 'draft'");
            DB::statement("ALTER TABLE invoices MODIFY COLUMN status VARCHAR(30) DEFAULT 'draft'");
            DB::statement("ALTER TABLE payments MODIFY COLUMN status VARCHAR(30) DEFAULT 'success'");
        }

        // 2. Enhance Quotations Table
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('deal_id')->constrained('leads')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('lead_id')->constrained('contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->after('contact_id')->constrained('companies')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('quote_number');
            $table->foreignId('parent_quotation_id')->nullable()->after('version')->constrained('quotations')->nullOnDelete();
            $table->string('currency', 10)->default('USD')->after('parent_quotation_id');
            $table->enum('discount_type', ['percentage', 'fixed'])->default('fixed')->after('subtotal');
            $table->decimal('discount_rate', 5, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_rate');
            $table->boolean('is_tax_inclusive')->default(false)->after('tax_amount');
            $table->text('terms_conditions')->nullable()->after('notes');
            $table->timestamp('accepted_at')->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->timestamp('viewed_at')->nullable()->after('rejected_at');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'deal_id']);
        });

        // 3. Enhance Quotation Items Table
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('quotation_id')->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->text('description')->nullable()->after('item_name');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('total_price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
            $table->string('hsn_sac_code', 50)->nullable()->after('tax_amount');
        });

        // 4. Enhance Invoices Table
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('invoice_number')->constrained('quotations')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->after('quotation_id')->constrained('deals')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('customer_id')->constrained('contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->after('contact_id')->constrained('companies')->nullOnDelete();
            $table->string('currency', 10)->default('USD')->after('company_id');
            $table->enum('discount_type', ['percentage', 'fixed'])->default('fixed')->after('subtotal');
            $table->decimal('discount_rate', 5, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_rate');
            $table->decimal('shipping_amount', 15, 2)->default(0)->after('discount_amount');
            $table->boolean('is_tax_inclusive')->default(false)->after('tax_amount');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount');
            $table->decimal('outstanding_amount', 15, 2)->default(0)->after('paid_amount');
            $table->string('payment_terms', 100)->default('Net 30')->after('due_date');
            $table->string('billing_name', 150)->nullable()->after('payment_terms');
            $table->string('billing_email', 150)->nullable()->after('billing_name');
            $table->string('billing_phone', 50)->nullable()->after('billing_email');
            $table->text('billing_address')->nullable()->after('billing_phone');
            $table->string('billing_tax_id', 50)->nullable()->after('billing_address');
            $table->timestamp('issued_at')->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('issued_at');
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->string('razorpay_order_id', 100)->nullable()->after('voided_at');
            $table->string('razorpay_payment_link', 500)->nullable()->after('razorpay_order_id');

            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'outstanding_amount']);
        });

        // 5. Enhance Invoice Items Table
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('invoice_id')->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->text('description')->nullable()->after('item_name');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('total_price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
            $table->string('hsn_sac_code', 50)->nullable()->after('tax_amount');
        });

        // 6. Enhance Payments Table
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number', 50)->nullable()->after('reference_number');
            $table->foreignId('customer_id')->nullable()->after('invoice_id')->constrained('customers')->nullOnDelete();
            $table->string('currency', 10)->default('USD')->after('amount');
            $table->string('payment_channel', 50)->default('manual')->after('payment_method');
            $table->string('gateway_order_id', 100)->nullable()->after('payment_channel');
            $table->string('gateway_payment_id', 100)->nullable()->after('gateway_order_id');
            $table->string('gateway_signature', 255)->nullable()->after('gateway_payment_id');
            $table->decimal('refunded_amount', 15, 2)->default(0)->after('gateway_signature');
            $table->foreignId('user_id')->nullable()->after('refunded_amount')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('user_id');

            $table->index(['tenant_id', 'gateway_payment_id']);
            $table->index(['tenant_id', 'receipt_number']);
        });

        // 7. Refunds Table
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('refund_number', 50);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('reason', 255);
            $table->enum('status', ['pending', 'processed', 'failed'])->default('processed');
            $table->string('gateway_refund_id', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'refund_number']);
            $table->index(['tenant_id', 'invoice_id']);
        });

        // 8. Credit Notes Table
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('credit_note_number', 50);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('reason', 255);
            $table->enum('status', ['draft', 'issued', 'applied', 'void'])->default('issued');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'credit_note_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('refunds');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'receipt_number',
                'customer_id',
                'currency',
                'payment_channel',
                'gateway_order_id',
                'gateway_payment_id',
                'gateway_signature',
                'refunded_amount',
                'user_id',
                'notes',
            ]);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn([
                'product_id',
                'product_variant_id',
                'description',
                'discount_amount',
                'tax_rate',
                'tax_amount',
                'hsn_sac_code',
            ]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropForeign(['deal_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'quotation_id',
                'deal_id',
                'contact_id',
                'company_id',
                'currency',
                'discount_type',
                'discount_rate',
                'discount_amount',
                'shipping_amount',
                'is_tax_inclusive',
                'paid_amount',
                'outstanding_amount',
                'payment_terms',
                'billing_name',
                'billing_email',
                'billing_phone',
                'billing_address',
                'billing_tax_id',
                'issued_at',
                'paid_at',
                'voided_at',
                'razorpay_order_id',
                'razorpay_payment_link',
            ]);
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn([
                'product_id',
                'product_variant_id',
                'description',
                'discount_amount',
                'tax_rate',
                'tax_amount',
                'hsn_sac_code',
            ]);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['company_id']);
            $table->dropForeign(['parent_quotation_id']);
            $table->dropColumn([
                'lead_id',
                'contact_id',
                'company_id',
                'version',
                'parent_quotation_id',
                'currency',
                'discount_type',
                'discount_rate',
                'discount_amount',
                'is_tax_inclusive',
                'terms_conditions',
                'accepted_at',
                'rejected_at',
                'viewed_at',
            ]);
        });
    }
};
