<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\ProductCategory;
use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\ProductVariant;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\StockMovement;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Sales\Models\Product;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected User $userB;
    protected string $tokenAdmin;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Global',
            'slug' => 'beta-global',
            'is_active' => true,
        ]);

        $this->userAdmin = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice.admin@acme.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin']);

        $this->userB = User::create([
            'name' => 'Bob Beta',
            'email' => 'bob@beta.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin']);

        $this->tokenAdmin = $this->userAdmin->createToken('test_token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test_token_b')->plainTextToken;

        TenantContext::setTenantId($this->tenantA->id);
    }

    /**
     * Test product and service creation with opening stock and variants.
     */
    public function test_product_and_service_creation_with_opening_stock_and_variants(): void
    {
        // 1. Create product category
        $catRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/catalog/categories', [
                'name' => 'Enterprise Hardware',
                'description' => 'Servers and networking equipment',
            ]);

        $catRes->assertStatus(201);
        $categoryId = $catRes->json('data.id');

        // 2. Create tangible product with opening stock
        $prodRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/catalog', [
                'name' => 'Enterprise Server R740',
                'sku' => 'SRV-R740',
                'type' => 'product',
                'category_id' => $categoryId,
                'brand' => 'Dell',
                'unit' => 'pcs',
                'price' => 4500.00,
                'cost_price' => 3200.00,
                'tax_rate' => 18.0,
                'track_inventory' => true,
                'opening_stock' => 15,
            ]);

        $prodRes->assertStatus(201);
        $productId = $prodRes->json('data.id');
        $this->assertEquals(15, $prodRes->json('data.total_stock'));
        $this->assertEquals(15, $prodRes->json('data.available_stock'));

        // Verify stock movement ledger has opening_stock
        $movement = StockMovement::where('tenant_id', $this->tenantA->id)
            ->where('product_id', $productId)
            ->where('movement_type', 'opening_stock')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(15, $movement->quantity);
        $this->assertEquals(3200.00, $movement->unit_cost);
        $this->assertEquals(0, $movement->before_stock);
        $this->assertEquals(15, $movement->after_stock);

        // 3. Create Variant
        $varRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/catalog/{$productId}/variants", [
                'name' => 'R740 - 64GB RAM / 2TB NVMe',
                'sku' => 'SRV-R740-64G',
                'sale_price' => 5200.00,
                'cost_price' => 3800.00,
                'attribute_values' => ['ram' => '64GB', 'storage' => '2TB NVMe'],
            ]);

        $varRes->assertStatus(201);
        $this->assertEquals('SRV-R740-64G', $varRes->json('data.sku'));

        // 4. Create Service item (not stock-managed)
        $srvRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/catalog', [
                'name' => 'Cloud Migration Consulting',
                'type' => 'service',
                'price' => 1500.00,
                'service_duration' => '10 hours',
                'service_delivery_type' => 'remote',
            ]);

        $srvRes->assertStatus(201);
        $this->assertEquals('service', $srvRes->json('data.type'));
        $this->assertFalse($srvRes->json('data.track_inventory'));
        $this->assertEquals(999999, $srvRes->json('data.total_stock'));
    }

    /**
     * Test Stock Adjustments (increase, decrease, damage write-off) with immutable ledger.
     */
    public function test_stock_adjustment_increase_decrease_and_damage_with_ledger(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Central Hub',
            'code' => 'WH-HUB',
            'is_primary' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cisco Router 2901',
            'sku' => 'CISCO-2901',
            'type' => 'product',
            'price' => 1200.00,
            'cost_price' => 850.00,
            'track_inventory' => true,
        ]);

        ProductStock::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 20,
            'reserved_stock' => 0,
            'minimum_stock' => 5,
        ]);

        // 1. Process adjustment: Increase by 5, decrease by 3, damage by 2
        $adjRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/inventory/adjust', [
                'warehouse_id' => $warehouse->id,
                'reason' => 'Annual physical count discrepancy',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'adjustment_type' => 'increase',
                        'quantity' => 10,
                        'unit_cost' => 850.00,
                        'notes' => 'Found unrecorded batch',
                    ],
                    [
                        'product_id' => $product->id,
                        'adjustment_type' => 'damage',
                        'quantity' => 2,
                        'unit_cost' => 850.00,
                        'notes' => 'Water damage in storage bay',
                    ],
                ],
            ]);

        $adjRes->assertStatus(201);

        // Final expected stock: 20 + 10 - 2 = 28
        $stock = ProductStock::where('product_id', $product->id)->first();
        $this->assertEquals(28, $stock->current_stock);
        $this->assertEquals(2, $stock->damaged_stock);

        // Verify ledger movements
        $movements = StockMovement::where('product_id', $product->id)->get();
        $this->assertCount(2, $movements);
        $this->assertTrue($movements->contains('movement_type', 'adjustment_increase'));
        $this->assertTrue($movements->contains('movement_type', 'damage_writeoff'));

        // 2. Negative stock protection
        $insufficientRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/inventory/adjust', [
                'warehouse_id' => $warehouse->id,
                'reason' => 'Excessive deduction test',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'adjustment_type' => 'decrease',
                        'quantity' => 50, // exceeds 28
                    ],
                ],
            ]);

        $insufficientRes->assertStatus(422);
    }

    /**
     * Test inter-warehouse stock transfer.
     */
    public function test_inter_warehouse_stock_transfer(): void
    {
        $whSource = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Source Warehouse A',
            'code' => 'WH-A',
        ]);

        $whDest = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Destination Warehouse B',
            'code' => 'WH-B',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Logitech MX Master 3S',
            'sku' => 'LOGI-MX3S',
            'type' => 'product',
            'price' => 99.00,
            'cost_price' => 65.00,
            'track_inventory' => true,
        ]);

        ProductStock::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'warehouse_id' => $whSource->id,
            'current_stock' => 50,
            'reserved_stock' => 0,
        ]);

        // Transfer 15 units from WH-A to WH-B
        $transferRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/inventory/transfer', [
                'source_warehouse_id' => $whSource->id,
                'destination_warehouse_id' => $whDest->id,
                'notes' => 'Replenishing East Coast distribution',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 15,
                    ],
                ],
            ]);

        $transferRes->assertStatus(201);

        // Verify Source stock: 50 - 15 = 35
        $sourceStock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $whSource->id)->first();
        $this->assertEquals(35, $sourceStock->current_stock);

        // Verify Destination stock: 15
        $destStock = ProductStock::where('product_id', $product->id)->where('warehouse_id', $whDest->id)->first();
        $this->assertEquals(15, $destStock->current_stock);

        // Verify ledger has transfer_out and transfer_in
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'warehouse_id' => $whSource->id,
            'movement_type' => 'transfer_out',
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenantA->id,
            'product_id' => $product->id,
            'warehouse_id' => $whDest->id,
            'movement_type' => 'transfer_in',
            'quantity' => 15,
        ]);
    }

    /**
     * Test procurement, purchase order creation, and goods receipt (GRN) into inventory.
     */
    public function test_procurement_purchase_order_and_goods_receipt(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Main Receiving Depot',
            'code' => 'WH-DEPOT',
            'is_primary' => true,
        ]);

        $supplier = Supplier::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'TechData Global Supply',
            'code' => 'SUP-TECH',
            'contact_name' => 'John Vendor',
            'email' => 'orders@techdata.com',
            'status' => 'active',
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Dell 27-inch 4K Monitor',
            'sku' => 'DELL-U2723QE',
            'type' => 'product',
            'price' => 599.00,
            'cost_price' => 420.00,
            'track_inventory' => true,
        ]);

        // 1. Create Purchase Order for 20 monitors @ $420 each
        $poRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/procurement/purchase-orders', [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'status' => 'ordered',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 20,
                        'unit_cost' => 420.00,
                        'tax_percent' => 10.0,
                    ],
                ],
            ]);

        $poRes->assertStatus(201);
        $poId = $poRes->json('data.id');
        $poItemId = $poRes->json('data.items.0.id');
        $this->assertEquals(9240.00, $poRes->json('data.total_amount')); // 20 * 420 * 1.10 = 9240

        // Stock before GRN is 0
        $this->assertEquals(0, $product->fresh()->total_stock);

        // 2. Receive 20 units via Goods Receipt Note (GRN)
        $grnRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/procurement/purchase-orders/{$poId}/receive", [
                'items' => [
                    [
                        'item_id' => $poItemId,
                        'quantity' => 20,
                    ],
                ],
            ]);

        $grnRes->assertStatus(200);
        $this->assertEquals('received', $grnRes->json('data.status'));

        // Stock after GRN should be 20
        $this->assertEquals(20, $product->fresh()->total_stock);

        // Verify stock movement ledger
        $movement = StockMovement::where('product_id', $product->id)
            ->where('movement_type', 'purchase_receipt')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(20, $movement->quantity);
        $this->assertEquals(420.00, $movement->unit_cost);
    }

    /**
     * Test Low Stock Alerts and Valuation Report.
     */
    public function test_low_stock_alerts_and_valuation_report(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Metro Warehouse',
            'code' => 'WH-METRO',
            'is_primary' => true,
        ]);

        $prodLow = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Industrial Cable 100m',
            'sku' => 'CAB-IND-100',
            'type' => 'product',
            'price' => 150.00,
            'cost_price' => 90.00,
            'track_inventory' => true,
        ]);

        ProductStock::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $prodLow->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 3,
            'reserved_stock' => 0,
            'minimum_stock' => 10, // 3 <= 10 -> Low stock
        ]);

        $prodNormal = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Wireless Keyboard',
            'sku' => 'KB-WL-01',
            'type' => 'product',
            'price' => 50.00,
            'cost_price' => 30.00,
            'track_inventory' => true,
        ]);

        ProductStock::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $prodNormal->id,
            'warehouse_id' => $warehouse->id,
            'current_stock' => 50,
            'reserved_stock' => 0,
            'minimum_stock' => 15,
        ]);

        // 1. Check Low Stock Alerts endpoint
        $alertsRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson('/api/v1/crm/inventory/low-stock');

        $alertsRes->assertStatus(200);
        $alerts = $alertsRes->json('data');
        $this->assertCount(1, $alerts);
        $this->assertEquals($prodLow->id, $alerts[0]['product_id']);
        $this->assertEquals(7, $alerts[0]['deficit']); // 10 - 3 = 7

        // 2. Check Valuation Report endpoint
        $valRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson('/api/v1/crm/inventory/valuation');

        $valRes->assertStatus(200);
        // Cost: (3 * 90) + (50 * 30) = 270 + 1500 = 1770
        $this->assertEquals(1770.00, $valRes->json('data.total_cost_valuation'));
        // Retail: (3 * 150) + (50 * 50) = 450 + 2500 = 2950
        $this->assertEquals(2950.00, $valRes->json('data.total_retail_valuation'));
        $this->assertEquals(53, $valRes->json('data.total_units'));
    }

    /**
     * Test tenant isolation on inventory, catalog and procurement.
     */
    public function test_tenant_isolation_on_inventory_and_procurement(): void
    {
        $productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Exclusive Product',
            'sku' => 'ACME-EXCL',
            'price' => 1000.00,
            'cost_price' => 600.00,
        ]);

        // Tenant B lists catalog -> should NOT see Acme Exclusive Product
        $listRes = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->withHeader('X-Workspace-Id', $this->tenantB->id)
            ->getJson('/api/v1/crm/catalog');

        $listRes->assertStatus(200);
        $items = $listRes->json('data');
        $this->assertEmpty(collect($items)->where('id', $productA->id));

        // Tenant B directly requesting Tenant A's product -> 404
        $directRes = $this->withHeader('Authorization', "Bearer {$this->tokenB}")
            ->withHeader('X-Workspace-Id', $this->tenantB->id)
            ->getJson("/api/v1/crm/catalog/{$productA->id}");

        $directRes->assertStatus(404);
    }
}
