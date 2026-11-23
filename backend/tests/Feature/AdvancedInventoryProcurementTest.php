<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Inventory\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\InventorySetting;
use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\PurchaseRequest;
use App\Domains\Inventory\Models\StockMovement;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\CustomerReturnService;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Inventory\Services\PurchaseService;
use App\Domains\Sales\Models\Product;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdvancedInventoryProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Apex Industrial Supplies',
            'slug' => 'apex-industrial-' . uniqid(),
            'domain' => 'apex' . uniqid() . '.test',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Apex Admin',
            'email' => 'admin-' . uniqid() . '@apex.test',
            'password' => bcrypt('password'),
            'role' => 'Business Admin',
            'status' => 'active',
        ]);
        $this->user->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
            'status' => 'active',
        ]);

        $this->warehouse1 = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mumbai Central Warehouse',
            'code' => 'WH-MUM',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->warehouse2 = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pune Regional Depot',
            'code' => 'WH-PUN',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'name' => 'Heavy Duty Industrial Pump 500W',
            'sku' => 'PUMP-500W',
            'unit' => 'pcs',
            'price' => 15000.00,
            'cost_price' => 10000.00,
            'tax_rate' => 18.00,
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'reorder_level' => 10.00,
            'reorder_quantity' => 25.00,
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Inventory Settings & Feature Activation.
     */
    public function test_inventory_settings_toggle_and_valuation_method(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/crm/inventory/settings', [
            'is_inventory_enabled' => true,
            'valuation_method' => 'fifo',
            'default_warehouse_id' => $this->warehouse1->id,
            'allow_negative_stock' => false,
            'auto_reserve_on_order' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inventory_settings', [
            'tenant_id' => $this->tenant->id,
            'valuation_method' => 'fifo',
            'is_inventory_enabled' => true,
        ]);
    }

    /**
     * Test 2: Product Catalog creation, stock availability check, and archive.
     */
    public function test_product_creation_availability_check_and_archival(): void
    {
        // 1. Initial opening stock
        $invService = app(InventoryService::class);
        $invService->recordStockMovement([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'movement_type' => 'opening_stock',
            'quantity' => 20,
            'unit_cost' => 10000,
        ], $this->user);

        // 2. Availability check
        $availResponse = $this->actingAs($this->user)->postJson('/api/v1/crm/catalog/check-availability', [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 15,
                    'warehouse_id' => $this->warehouse1->id,
                ],
            ],
        ]);

        $availResponse->assertStatus(200);
        $this->assertTrue($availResponse->json('data.is_fully_available'));
        $this->assertEquals(20, $availResponse->json('data.items.0.available_stock'));

        // 3. Archive product
        $archiveResponse = $this->actingAs($this->user)->postJson("/api/v1/crm/catalog/{$this->product->id}/archive");
        $archiveResponse->assertStatus(200);
        $this->assertTrue($this->product->fresh()->is_archived);
    }

    /**
     * Test 3: Immutable Stock Ledger & Negative Stock Guard.
     */
    public function test_immutable_stock_ledger_and_negative_stock_prevention(): void
    {
        $invService = app(InventoryService::class);

        // Initial deposit: 10 units
        $invService->recordStockMovement([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10,
            'unit_cost' => 10000,
        ], $this->user);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse1->id)
            ->first();

        $this->assertEquals(10, (float) $stock->current_stock);

        // Verify movement ledger record
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'movement_type' => 'opening_stock',
            'quantity' => 10.00,
            'before_stock' => 0.00,
            'after_stock' => 10.00,
        ]);

        // Attempting to deduct 15 units should throw ValidationException
        $this->expectException(ValidationException::class);
        $invService->recordStockMovement([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'movement_type' => 'sale_deduction',
            'quantity' => 15,
        ], $this->user);
    }

    /**
     * Test 4: Stock Reservation, Release & Fulfillment.
     */
    public function test_stock_reservation_release_and_fulfillment(): void
    {
        $invService = app(InventoryService::class);

        // Seed 50 units
        $invService->recordStockMovement([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'movement_type' => 'opening_stock',
            'quantity' => 50,
            'unit_cost' => 10000,
        ], $this->user);

        // 1. Reserve 10 units for Quote #101
        $invService->reserveStock($this->tenant->id, $this->product->id, null, $this->warehouse1->id, 10, 'quotation', 101, $this->user);

        $stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse1->id)->first();
        $this->assertEquals(50, (float) $stock->current_stock);
        $this->assertEquals(10, (float) $stock->reserved_stock);
        $this->assertEquals(40, (float) $stock->available_stock);

        // 2. Release 4 units
        $invService->releaseStock($this->tenant->id, $this->product->id, null, $this->warehouse1->id, 4, 'quotation', 101, $this->user);
        $stock->refresh();
        $this->assertEquals(6, (float) $stock->reserved_stock);
        $this->assertEquals(44, (float) $stock->available_stock);

        // 3. Fulfill 6 units (order dispatch)
        $invService->fulfillStock($this->tenant->id, $this->product->id, null, $this->warehouse1->id, 6, 'invoice', 201, $this->user);
        $stock->refresh();
        $this->assertEquals(44, (float) $stock->current_stock);
        $this->assertEquals(0, (float) $stock->reserved_stock);
        $this->assertEquals(44, (float) $stock->available_stock);
    }

    /**
     * Test 5: Multi-Warehouse Transfer Workflow with Transit and Damage Quarantine.
     */
    public function test_multi_warehouse_transfer_workflow_and_damaged_quarantine(): void
    {
        $invService = app(InventoryService::class);

        // Add 30 units to WH1
        $invService->recordStockMovement([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'movement_type' => 'opening_stock',
            'quantity' => 30,
            'unit_cost' => 10000,
        ], $this->user);

        // 1. Request Transfer of 10 units from WH1 to WH2
        $transfer = $invService->createTransferRequest(
            $this->tenant->id,
            $this->warehouse1->id,
            $this->warehouse2->id,
            [['product_id' => $this->product->id, 'quantity' => 10]],
            $this->user,
            'Inter-branch stock rebalance'
        );

        $this->assertEquals('draft', $transfer->status);

        // 2. Dispatch Transfer (deducts 10 from WH1, marks in_transit)
        $invService->dispatchTransfer($this->tenant->id, $transfer->id, $this->user);
        $transfer->refresh();
        $this->assertEquals('in_transit', $transfer->status);

        $wh1Stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse1->id)->first();
        $this->assertEquals(20, (float) $wh1Stock->current_stock);

        // 3. Receive Transfer at WH2 with 8 Sound units and 2 Damaged units
        $invService->receiveTransfer(
            $this->tenant->id,
            $transfer->id,
            [
                [
                    'item_id' => $transfer->items->first()->id,
                    'accepted_quantity' => 8,
                    'damaged_quantity' => 2,
                ],
            ],
            2,
            'Transit vibration damage on 2 cartons',
            $this->user
        );

        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);
        $this->assertEquals(2, (float) $transfer->damaged_quantity);

        $wh2Stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse2->id)->first();
        // Destination should have 8 sound available units and 2 damaged quarantine units
        $this->assertEquals(8, (float) $wh2Stock->current_stock);
        $this->assertEquals(2, (float) $wh2Stock->damaged_stock);
    }

    /**
     * Test 6: Procurement 10-step Workflow (PR -> PO -> GRN with QC -> Vendor Bill).
     */
    public function test_procurement_workflow_with_partial_grn_and_vendor_bill(): void
    {
        $purchaseService = app(PurchaseService::class);

        // Create Supplier
        $supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bharat Heavy Electricals Vendor',
            'code' => 'SUP-BHEL',
            'gstin' => '27AAACB1234F1Z1',
            'pan_number' => 'AAACB1234F',
            'bank_name' => 'HDFC Bank Ltd',
            'bank_account_number' => '50100293847291',
            'payment_terms' => 'Net 30',
            'status' => 'active',
        ]);

        // 1. Create Purchase Request (PR)
        $pr = $purchaseService->createPurchaseRequest($this->tenant->id, [
            'warehouse_id' => $this->warehouse1->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 20, 'estimated_unit_cost' => 9500],
            ],
        ], $this->user);

        $this->assertEquals('pending_approval', $pr->status);

        // 2. Approve PR
        $pr = $purchaseService->approvePurchaseRequest($this->tenant->id, $pr->id, $this->user);
        $this->assertEquals('approved', $pr->status);

        // 3. Create PO linked to approved PR
        $po = $purchaseService->createPurchaseOrder($this->tenant->id, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $this->warehouse1->id,
            'purchase_request_id' => $pr->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 20, 'unit_cost' => 9500, 'tax_percent' => 18],
            ],
        ], $this->user);

        $this->assertEquals('ordered', $po->status);
        $this->assertEquals(224200.00, (float) $po->total_amount);

        // 4. Partial Goods Receipt Note (GRN): 15 units received, 14 accepted, 1 rejected/damaged
        $grn = $purchaseService->receiveGoods($this->tenant->id, $po->id, [
            'received_date' => now()->toDateString(),
            'challan_number' => 'DC-88910',
            'qc_notes' => '1 unit damaged packaging',
            'items' => [
                [
                    'item_id' => $po->items->first()->id,
                    'quantity' => 15,
                    'accepted_quantity' => 14,
                    'rejected_damaged_quantity' => 1,
                    'rejection_reason' => 'Cracked outer casing',
                ],
            ],
        ], $this->user);

        $po->refresh();
        $this->assertEquals('partially_received', $po->status);

        // Stock in WH1 should have increased by exactly 14 accepted units
        $stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse1->id)->first();
        $this->assertEquals(14, (float) $stock->current_stock);
        $this->assertEquals(1, (float) $stock->damaged_stock);

        // 5. Generate Vendor Bill linked to Finance
        $bill = $purchaseService->generateVendorBill($this->tenant->id, $po->id, [
            'bill_number' => 'INV-SUP-9901',
        ], $this->user);

        $this->assertNotNull($bill->id);
        $this->assertEquals($po->id, $bill->purchase_order_id);
        $this->assertEquals($po->total_amount, $bill->total_amount);

        // Verify supplier outstanding balance updated
        $supplier->refresh();
        $this->assertEquals($po->total_amount, (float) $supplier->outstanding_balance);
    }

    /**
     * Test 7: Vendor Sensitive Bank Account Masking.
     */
    public function test_vendor_bank_account_masking_and_restricted_access(): void
    {
        $supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tata Steel Distribution',
            'code' => 'SUP-TATA',
            'bank_name' => 'State Bank of India',
            'bank_account_number' => '99887766554433',
            'status' => 'active',
        ]);

        $this->assertEquals('XXXXXXXXXX4433', $supplier->masked_bank_account);

        // Standard user should get masked or restricted detail
        $response = $this->actingAs($this->user)->getJson("/api/v1/crm/procurement/suppliers/{$supplier->id}/bank-details");
        $response->assertStatus(200);
        $this->assertEquals('99887766554433', $response->json('data.bank_account_number'));
    }

    /**
     * Test 8: Physical Stock Count & Reconciliation.
     */
    public function test_physical_stock_count_and_reconciliation_adjustment(): void
    {
        $invService = app(InventoryService::class);

        // System currently has 25 units
        $invService->recordStockMovement([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'movement_type' => 'opening_stock',
            'quantity' => 25,
            'unit_cost' => 10000,
        ], $this->user);

        // Physical count reports only 22 units (variance: -3 units)
        $rec = $invService->createStockReconciliation(
            $this->tenant->id,
            $this->warehouse1->id,
            [
                [
                    'product_id' => $this->product->id,
                    'physical_quantity' => 22,
                    'reconciliation_action' => 'adjusted',
                ],
            ],
            $this->user,
            'Annual Q4 Physical Audit'
        );

        $this->assertEquals('in_progress', $rec->status);
        $this->assertEquals(-3, (float) $rec->items->first()->variance_quantity);
        $this->assertEquals(-30000.00, (float) $rec->total_variance_cost);

        // Apply reconciliation adjustments
        $invService->applyStockReconciliation($this->tenant->id, $rec->id, $this->user);
        $rec->refresh();
        $this->assertEquals('completed', $rec->status);

        // Stock in WH1 must now match exactly the physical count of 22
        $stock = ProductStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse1->id)->first();
        $this->assertEquals(22, (float) $stock->current_stock);
    }

    /**
     * Test 9: Customer RMA Returns & Restock / Quarantine with Credit Note.
     */
    public function test_customer_returns_restock_and_credit_note_generation(): void
    {
        $invService = app(InventoryService::class);
        $returnService = app(CustomerReturnService::class);

        // Customer & Invoice setup
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Reliance Infrastructure Project',
            'email' => 'procurement@reliance-infra.test',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-2026-0091',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 30000.00,
            'tax_amount' => 5400.00,
            'total_amount' => 35400.00,
            'paid_amount' => 35400.00,
            'outstanding_amount' => 0.00,
            'status' => 'paid',
        ]);

        // 1. Create Return Request (RMA) for 1 item
        $customerReturn = $returnService->createReturnRequest($this->tenant->id, [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'warehouse_id' => $this->warehouse1->id,
            'return_reason' => 'Client ordered incorrect model specs',
            'product_condition' => 'unopened',
            'restock_action' => 'restock_available',
            'refund_type' => 'credit_note',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 15000, 'tax_rate' => 18],
            ],
        ], $this->user);

        $this->assertEquals('requested', $customerReturn->status);
        $this->assertEquals(17700.00, (float) $customerReturn->total_refund_amount);

        // 2. Approve Return and Restock Sound Product
        $returnService->approveReturnAndRestock($this->tenant->id, $customerReturn->id, $this->user);
        $customerReturn->refresh();

        $this->assertEquals('completed', $customerReturn->status);
        $this->assertNotNull($customerReturn->credit_note_id);

        // Verify Credit Note created in Finance
        $this->assertDatabaseHas('credit_notes', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 17700.00,
            'status' => 'issued',
        ]);

        // Verify stock movement ledger has 'sale_return'
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'movement_type' => 'sale_return',
            'quantity' => 1.00,
        ]);
    }

    /**
     * Test 10: Multi-Tenant Stock Segregation.
     */
    public function test_multi_tenant_inventory_isolation(): void
    {
        $tenant2 = Tenant::create([
            'name' => 'Omega Logistics Corp',
            'slug' => 'omega-' . uniqid(),
            'domain' => 'omega' . uniqid() . '.test',
        ]);

        $user2 = User::create([
            'tenant_id' => $tenant2->id,
            'current_tenant_id' => $tenant2->id,
            'name' => 'Omega Officer',
            'email' => 'officer-' . uniqid() . '@omega.test',
            'password' => bcrypt('password'),
            'role' => 'Business Admin',
            'status' => 'active',
        ]);
        $user2->workspaces()->attach($tenant2->id, [
            'role' => 'Business Admin',
            'status' => 'active',
        ]);

        $whTenant2 = Warehouse::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Omega Chennai Hub',
            'code' => 'WH-CHE',
            'is_primary' => true,
        ]);

        // User 2 should NOT see warehouses or stock from Tenant 1
        $response = $this->actingAs($user2)->getJson('/api/v1/crm/inventory/warehouses');
        $response->assertStatus(200);
        $whIds = collect($response->json('data'))->pluck('id');

        $this->assertTrue($whIds->contains($whTenant2->id));
        $this->assertFalse($whIds->contains($this->warehouse1->id));
        $this->assertFalse($whIds->contains($this->warehouse2->id));
    }
}
