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
        // 1. Product Categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'slug']);
        });

        // 2. Enhance Products Table for Unified Catalog & Inventory
        Schema::table('products', function (Blueprint $table) {
            $table->enum('type', ['product', 'service'])->default('product')->after('tenant_id');
            $table->foreignId('category_id')->nullable()->after('type')->constrained('product_categories')->nullOnDelete();
            $table->string('brand', 100)->nullable()->after('category');
            $table->string('unit', 50)->default('pcs')->after('brand'); // pcs, box, kg, hrs, month
            $table->decimal('cost_price', 15, 2)->default(0)->after('price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost_price');
            $table->string('hsn_sac_code', 50)->nullable()->after('tax_rate');
            $table->string('barcode', 100)->nullable()->after('hsn_sac_code');
            $table->boolean('track_inventory')->default(true)->after('barcode');
            $table->boolean('allow_negative_stock')->default(false)->after('track_inventory');
            $table->string('service_duration', 100)->nullable()->after('allow_negative_stock'); // e.g. '1 hour', 'Monthly'
            $table->string('service_delivery_type', 100)->nullable()->after('service_duration'); // remote, on_site
            $table->string('image_url', 500)->nullable()->after('service_delivery_type');
            $table->json('pricing_rules')->nullable()->after('image_url');
            $table->json('attributes_schema')->nullable()->after('pricing_rules');

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'category_id']);
            $table->index(['tenant_id', 'status']);
        });

        // 3. Product Variants & Attributes
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 150); // e.g. "Size: L, Color: Navy"
            $table->string('sku', 100)->nullable();
            $table->json('attribute_values')->nullable(); // {"size": "L", "color": "Navy"}
            $table->decimal('sale_price', 15, 2)->nullable();
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'sku']);
        });

        // 4. Warehouses & Storage Locations
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_primary']);
        });

        // 5. Product Stocks (Current Stock by Warehouse)
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('current_stock', 15, 2)->default(0);
            $table->decimal('reserved_stock', 15, 2)->default(0);
            $table->decimal('minimum_stock', 15, 2)->default(0);
            $table->decimal('damaged_stock', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id'], 'prod_stocks_tenant_prod_wh_idx');
            $table->index(['tenant_id', 'warehouse_id'], 'prod_stocks_tenant_wh_idx');
        });

        // 6. Stock Movement Ledger (Audit Immutable Ledger)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('movement_type', 50); 
            // opening_stock, purchase_receipt, sale_deduction, sale_return, adjustment_increase, adjustment_decrease, transfer_out, transfer_in, damage_writeoff, stock_reserved, stock_released
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('before_stock', 15, 2)->default(0);
            $table->decimal('after_stock', 15, 2)->default(0);
            $table->string('reference_type', 100)->nullable(); // e.g. deal, quotation, invoice, purchase_order, adjustment, transfer
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id'], 'movements_tenant_prod_wh_idx');
            $table->index(['tenant_id', 'movement_type'], 'movements_tenant_type_idx');
            $table->index(['tenant_id', 'created_at'], 'movements_tenant_created_idx');
        });

        // 7. Stock Adjustments (Physical count adjustments, damage, write-offs)
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('adjustment_number', 50);
            $table->string('reason', 255);
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('approved');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'warehouse_id']);
            $table->index(['tenant_id', 'adjustment_number']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->enum('adjustment_type', ['increase', 'decrease', 'damage']);
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. Stock Transfers (Inter-warehouse transfers)
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('transfer_number', 50);
            $table->enum('status', ['draft', 'in_transit', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'source_warehouse_id']);
            $table->index(['tenant_id', 'destination_warehouse_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Suppliers & Vendors
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('contact_name', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('payment_terms', 100)->default('Net 30');
            $table->string('currency', 10)->default('USD');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'status']);
        });

        // 10. Purchase Orders (Procurement)
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('po_number', 50);
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', ['draft', 'ordered', 'received', 'partially_received', 'cancelled'])->default('draft');
            $table->string('currency', 10)->default('USD');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'po_number']);
            $table->index(['tenant_id', 'supplier_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('received_quantity', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_variants');

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn([
                'type',
                'category_id',
                'brand',
                'unit',
                'cost_price',
                'tax_rate',
                'hsn_sac_code',
                'barcode',
                'track_inventory',
                'allow_negative_stock',
                'service_duration',
                'service_delivery_type',
                'image_url',
                'pricing_rules',
                'attributes_schema',
            ]);
        });

        Schema::dropIfExists('product_categories');
    }
};
