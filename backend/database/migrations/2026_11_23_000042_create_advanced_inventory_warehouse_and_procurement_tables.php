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
        // 1. Inventory Organization / Feature Settings
        if (!Schema::hasTable('inventory_settings')) {
            Schema::create('inventory_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
                $table->boolean('is_inventory_enabled')->default(true);
                $table->string('valuation_method', 30)->default('moving_average'); // moving_average, fifo, standard
                $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->boolean('allow_negative_stock')->default(false);
                $table->boolean('auto_reserve_on_order')->default(true);
                $table->boolean('auto_deduct_on_invoice')->default(true);
                $table->boolean('low_stock_notification')->default(true);
                $table->integer('default_reorder_lead_days')->default(7);
                $table->timestamps();
            });
        }

        // 2. Enhance Products Table
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'min_selling_price')) {
                $table->decimal('min_selling_price', 15, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('products', 'is_archived')) {
                $table->boolean('is_archived')->default(false)->after('status');
            }
            if (!Schema::hasColumn('products', 'secondary_unit')) {
                $table->string('secondary_unit', 50)->nullable()->after('unit');
            }
            if (!Schema::hasColumn('products', 'unit_conversion_factor')) {
                $table->decimal('unit_conversion_factor', 10, 4)->default(1.0000)->after('secondary_unit');
            }
            if (!Schema::hasColumn('products', 'reorder_level')) {
                $table->decimal('reorder_level', 15, 2)->default(10.00)->after('unit_conversion_factor');
            }
            if (!Schema::hasColumn('products', 'reorder_quantity')) {
                $table->decimal('reorder_quantity', 15, 2)->default(50.00)->after('reorder_level');
            }
        });

        // 3. Enhance Warehouses Table
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')->constrained('branches')->nullOnDelete();
            }
            if (!Schema::hasColumn('warehouses', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('warehouses', 'capacity_sqft')) {
                $table->decimal('capacity_sqft', 12, 2)->nullable()->after('country');
            }
            if (!Schema::hasColumn('warehouses', 'capacity_units')) {
                $table->decimal('capacity_units', 15, 2)->nullable()->after('capacity_sqft');
            }
        });

        // 4. Enhance Product Stocks Table
        Schema::table('product_stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('product_stocks', 'incoming_stock')) {
                $table->decimal('incoming_stock', 15, 2)->default(0)->after('reserved_stock');
            }
            if (!Schema::hasColumn('product_stocks', 'returned_stock')) {
                $table->decimal('returned_stock', 15, 2)->default(0)->after('damaged_stock');
            }
            if (!Schema::hasColumn('product_stocks', 'reorder_level')) {
                $table->decimal('reorder_level', 15, 2)->default(10.00)->after('returned_stock');
            }
        });

        // 5. Enhance Suppliers / Vendors Table
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'pan_number')) {
                $table->string('pan_number', 20)->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('suppliers', 'gstin')) {
                $table->string('gstin', 20)->nullable()->after('pan_number');
            }
            if (!Schema::hasColumn('suppliers', 'bank_name')) {
                $table->string('bank_name', 150)->nullable()->after('gstin');
            }
            if (!Schema::hasColumn('suppliers', 'bank_account_number')) {
                $table->string('bank_account_number', 100)->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('suppliers', 'bank_ifsc')) {
                $table->string('bank_ifsc', 50)->nullable()->after('bank_account_number');
            }
            if (!Schema::hasColumn('suppliers', 'bank_branch')) {
                $table->string('bank_branch', 100)->nullable()->after('bank_ifsc');
            }
            if (!Schema::hasColumn('suppliers', 'products_supplied')) {
                $table->json('products_supplied')->nullable()->after('bank_branch');
            }
            if (!Schema::hasColumn('suppliers', 'outstanding_balance')) {
                $table->decimal('outstanding_balance', 15, 2)->default(0)->after('products_supplied');
            }
            if (!Schema::hasColumn('suppliers', 'vendor_rating')) {
                $table->decimal('vendor_rating', 3, 2)->default(5.00)->after('outstanding_balance');
            }
            if (!Schema::hasColumn('suppliers', 'documents')) {
                $table->json('documents')->nullable()->after('vendor_rating');
            }
        });

        // 6. Purchase Requests (Procurement Step 1)
        if (!Schema::hasTable('purchase_requests')) {
            Schema::create('purchase_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('pr_number', 50);
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'ordered'])->default('draft');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->decimal('total_estimated_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'pr_number']);
            });
        }

        if (!Schema::hasTable('purchase_request_items')) {
            Schema::create('purchase_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->decimal('quantity', 15, 2);
                $table->decimal('estimated_unit_cost', 15, 2)->default(0);
                $table->decimal('total_cost', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 7. Enhance Purchase Orders Table
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'purchase_request_id')) {
                $table->foreignId('purchase_request_id')->nullable()->after('supplier_id')->constrained('purchase_requests')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_orders', 'approval_status')) {
                $table->enum('approval_status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('approved')->after('status');
            }
            if (!Schema::hasColumn('purchase_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('purchase_orders', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('warehouse_id')->constrained('branches')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_orders', 'revision_number')) {
                $table->integer('revision_number')->default(0)->after('po_number');
            }
            if (!Schema::hasColumn('purchase_orders', 'bill_id')) {
                $table->foreignId('bill_id')->nullable()->after('total_amount')->constrained('vendor_bills')->nullOnDelete();
            }
        });

        // 8. Goods Receipt Notes (GRN & Quality Check Inspection)
        if (!Schema::hasTable('goods_receipt_notes')) {
            Schema::create('goods_receipt_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('grn_number', 50);
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
                $table->enum('status', ['inspected', 'accepted', 'rejected', 'partially_accepted'])->default('accepted');
                $table->date('received_date');
                $table->string('challan_number', 100)->nullable();
                $table->string('invoice_reference', 100)->nullable();
                $table->text('qc_notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'purchase_order_id']);
                $table->index(['tenant_id', 'grn_number']);
            });
        }

        if (!Schema::hasTable('goods_receipt_note_items')) {
            Schema::create('goods_receipt_note_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_note_id')->constrained('goods_receipt_notes')->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->decimal('ordered_quantity', 15, 2);
                $table->decimal('received_quantity', 15, 2);
                $table->decimal('accepted_quantity', 15, 2);
                $table->decimal('rejected_damaged_quantity', 15, 2)->default(0);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->string('rejection_reason', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 9. Enhance Stock Transfers Table
        Schema::table('stock_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_transfers', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_transfers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('stock_transfers', 'dispatched_by')) {
                $table->foreignId('dispatched_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_transfers', 'received_by')) {
                $table->foreignId('received_by')->nullable()->after('dispatched_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_transfers', 'damaged_quantity')) {
                $table->decimal('damaged_quantity', 15, 2)->default(0)->after('received_by');
            }
            if (!Schema::hasColumn('stock_transfers', 'damage_notes')) {
                $table->text('damage_notes')->nullable()->after('damaged_quantity');
            }
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_transfer_items', 'received_quantity')) {
                $table->decimal('received_quantity', 15, 2)->default(0)->after('quantity');
            }
            if (!Schema::hasColumn('stock_transfer_items', 'damaged_quantity')) {
                $table->decimal('damaged_quantity', 15, 2)->default(0)->after('received_quantity');
            }
        });

        // 10. Physical Stock Count & Reconciliation
        if (!Schema::hasTable('stock_reconciliations')) {
            Schema::create('stock_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('reconciliation_number', 50);
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('counted_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['in_progress', 'completed', 'cancelled'])->default('in_progress');
                $table->decimal('total_variance_cost', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('reconciled_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'warehouse_id']);
                $table->index(['tenant_id', 'reconciliation_number']);
            });
        }

        if (!Schema::hasTable('stock_reconciliation_items')) {
            Schema::create('stock_reconciliation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_reconciliation_id')->constrained('stock_reconciliations')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->decimal('system_quantity', 15, 2);
                $table->decimal('physical_quantity', 15, 2);
                $table->decimal('variance_quantity', 15, 2); // physical - system
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->decimal('variance_cost', 15, 2)->default(0);
                $table->enum('reconciliation_action', ['adjusted', 'flagged', 'ignored'])->default('adjusted');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 11. Customer Returns (RMA) & Restock / Quarantine
        if (!Schema::hasTable('customer_returns')) {
            Schema::create('customer_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('return_number', 50);
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->string('order_reference', 100)->nullable();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->enum('status', [
                    'requested',
                    'inspecting',
                    'restock_approved',
                    'damaged_quarantined',
                    'refund_approved',
                    'completed',
                    'rejected'
                ])->default('requested');
                $table->string('return_reason', 255);
                $table->enum('product_condition', ['unopened', 'opened_good', 'defective', 'damaged_in_transit'])->default('opened_good');
                $table->enum('restock_action', ['restock_available', 'quarantine_damage', 'scrap', 'return_to_vendor'])->default('restock_available');
                $table->enum('refund_type', ['credit_note', 'bank_refund', 'replacement'])->default('credit_note');
                $table->foreignId('credit_note_id')->nullable()->constrained('credit_notes')->nullOnDelete();
                $table->foreignId('replacement_product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->decimal('total_refund_amount', 15, 2)->default(0);
                $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('inspection_notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
                $table->index(['tenant_id', 'invoice_id']);
                $table->index(['tenant_id', 'return_number']);
            });
        }

        if (!Schema::hasTable('customer_return_items')) {
            Schema::create('customer_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->decimal('quantity', 15, 2);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->foreignId('restock_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->boolean('is_restocked')->default(false);
                $table->boolean('is_damaged')->default(false);
                $table->text('condition_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_return_items');
        Schema::dropIfExists('customer_returns');
        Schema::dropIfExists('stock_reconciliation_items');
        Schema::dropIfExists('stock_reconciliations');
        Schema::dropIfExists('goods_receipt_note_items');
        Schema::dropIfExists('goods_receipt_notes');
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('inventory_settings');

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn(['received_quantity', 'damaged_quantity']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['dispatched_by']);
            $table->dropForeign(['received_by']);
            $table->dropColumn(['approved_by', 'approved_at', 'dispatched_by', 'received_by', 'damaged_quantity', 'damage_notes']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['bill_id']);
            $table->dropColumn(['purchase_request_id', 'approval_status', 'approved_by', 'approved_at', 'branch_id', 'revision_number', 'bill_id']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'pan_number',
                'gstin',
                'bank_name',
                'bank_account_number',
                'bank_ifsc',
                'bank_branch',
                'products_supplied',
                'outstanding_balance',
                'vendor_rating',
                'documents',
            ]);
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropColumn(['incoming_stock', 'returned_stock', 'reorder_level']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['branch_id', 'manager_id', 'capacity_sqft', 'capacity_units']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_selling_price', 'is_archived', 'secondary_unit', 'unit_conversion_factor', 'reorder_level', 'reorder_quantity']);
        });
    }
};
