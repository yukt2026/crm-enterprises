<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\StockReconciliation;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Inventory\Services\InventorySettingService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected InventoryService $inventoryService,
        protected InventorySettingService $settingService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List all warehouses for the workspace.
     */
    public function warehouses(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $warehouses = Warehouse::where('tenant_id', $tenantId)
            ->withCount('stocks')
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();

        return $this->successResponse($warehouses);
    }

    /**
     * Create a new warehouse.
     */
    public function createWarehouse(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'is_primary' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_primary'])) {
            Warehouse::where('tenant_id', $tenantId)->update(['is_primary' => false]);
        }

        $warehouse = Warehouse::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('WH-' . strtoupper(substr($validated['name'], 0, 3))),
            'contact_name' => $validated['contact_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'country' => $validated['country'] ?? null,
            'is_primary' => $validated['is_primary'] ?? false,
            'is_active' => true,
        ]);

        return $this->successResponse($warehouse, 'Warehouse created successfully.', 201);
    }

    /**
     * List product stock records with filters.
     */
    public function stocks(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = ProductStock::where('product_stocks.tenant_id', $tenantId)
            ->with(['product:id,name,sku,unit,price,cost_price,type', 'warehouse:id,name,code', 'variant:id,name,sku'])
            ->orderBy('product_stocks.id', 'desc');

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->query('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->query('product_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $stocks = $query->paginate($perPage);

        return $this->successResponse(
            $stocks->items(),
            'Stock levels retrieved.',
            200,
            [
                'current_page' => $stocks->currentPage(),
                'last_page' => $stocks->lastPage(),
                'per_page' => $stocks->perPage(),
                'total' => $stocks->total(),
            ]
        );
    }

    /**
     * Process manual stock adjustment.
     */
    public function adjust(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'reason' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.adjustment_type' => 'required|in:increase,decrease,damage',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $adjustment = $this->inventoryService->processAdjustment(
            tenantId: $tenantId,
            warehouseId: (int) $validated['warehouse_id'],
            reason: $validated['reason'],
            items: $validated['items'],
            user: $request->user()
        );

        return $this->successResponse($adjustment, 'Stock adjustment processed successfully.', 201);
    }

    /**
     * Process stock transfer between warehouses.
     */
    public function transfer(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.notes' => 'nullable|string',
        ]);

        $transfer = $this->inventoryService->processTransfer(
            tenantId: $tenantId,
            sourceWarehouseId: (int) $validated['source_warehouse_id'],
            destWarehouseId: (int) $validated['destination_warehouse_id'],
            items: $validated['items'],
            user: $request->user(),
            notes: $validated['notes'] ?? null
        );

        return $this->successResponse($transfer, 'Stock transferred successfully.', 201);
    }

    /**
     * Query immutable stock movement ledger.
     */
    public function ledger(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $filters = [
            'product_id' => $request->query('product_id'),
            'warehouse_id' => $request->query('warehouse_id'),
            'movement_type' => $request->query('movement_type'),
            'per_page' => $request->query('per_page', 25),
        ];

        $paginator = $this->inventoryService->getStockLedger($tenantId, $filters);

        return $this->successResponse(
            $paginator->items(),
            'Stock movement ledger retrieved.',
            200,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /**
     * Current stock valuation report.
     */
    public function valuation(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null;

        $report = $this->inventoryService->getValuationReport($tenantId, $warehouseId);
        return $this->successResponse($report);
    }

    /**
     * Low stock alert items.
     */
    public function lowStockAlerts(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null;

        $alerts = $this->inventoryService->getLowStockAlerts($tenantId, $warehouseId);
        return $this->successResponse($alerts);
    }

    /**
     * Reorder report with suggested procurement quantities.
     */
    public function reorderReport(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->query('warehouse_id') : null;

        $report = $this->inventoryService->getReorderReport($tenantId, $warehouseId);
        return $this->successResponse($report);
    }

    /**
     * Product profitability report.
     */
    public function profitabilityReport(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $report = $this->inventoryService->getProductProfitabilityReport($tenantId);
        return $this->successResponse($report);
    }

    /**
     * Get tenant inventory settings.
     */
    public function getSettings(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $settings = $this->settingService->getSettings($tenantId)->load('defaultWarehouse');
        return $this->successResponse($settings);
    }

    /**
     * Update tenant inventory settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'is_inventory_enabled' => 'nullable|boolean',
            'valuation_method' => 'nullable|in:moving_average,fifo,standard',
            'default_warehouse_id' => 'nullable|exists:warehouses,id',
            'allow_negative_stock' => 'nullable|boolean',
            'auto_reserve_on_order' => 'nullable|boolean',
            'auto_deduct_on_invoice' => 'nullable|boolean',
            'low_stock_notification' => 'nullable|boolean',
            'default_reorder_lead_days' => 'nullable|integer|min:1',
        ]);

        $settings = $this->settingService->updateSettings($tenantId, $validated);
        return $this->successResponse($settings, 'Inventory settings updated.');
    }

    /**
     * Get single warehouse details with stocks.
     */
    public function showWarehouse(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $warehouse = Warehouse::where('tenant_id', $tenantId)
            ->with(['branch', 'manager', 'stocks.product', 'stocks.variant'])
            ->findOrFail($id);

        return $this->successResponse($warehouse);
    }

    /**
     * Update warehouse.
     */
    public function updateWarehouse(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'code' => 'nullable|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
            'manager_id' => 'nullable|exists:users,id',
            'contact_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'capacity_sqft' => 'nullable|numeric|min:0',
            'capacity_units' => 'nullable|numeric|min:0',
            'is_primary' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_primary'])) {
            Warehouse::where('tenant_id', $tenantId)->where('id', '!=', $id)->update(['is_primary' => false]);
        }

        $warehouse->update($validated);
        return $this->successResponse($warehouse->fresh(['branch', 'manager']), 'Warehouse updated.');
    }

    /**
     * Reserve stock for quotation or order.
     */
    public function reserve(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        $movement = $this->inventoryService->reserveStock(
            $tenantId,
            (int) $validated['product_id'],
            $validated['product_variant_id'] ? (int) $validated['product_variant_id'] : null,
            (int) $validated['warehouse_id'],
            (float) $validated['quantity'],
            $validated['reference_type'] ?? null,
            $validated['reference_id'] ?? null,
            $request->user()
        );

        return $this->successResponse($movement, 'Stock reserved successfully.');
    }

    /**
     * Release stock reservation.
     */
    public function release(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|integer',
        ]);

        $movement = $this->inventoryService->releaseStock(
            $tenantId,
            (int) $validated['product_id'],
            $validated['product_variant_id'] ? (int) $validated['product_variant_id'] : null,
            (int) $validated['warehouse_id'],
            (float) $validated['quantity'],
            $validated['reference_type'] ?? null,
            $validated['reference_id'] ?? null,
            $request->user()
        );

        return $this->successResponse($movement, 'Stock reservation released.');
    }

    /**
     * Issue stock for internal consumption or sample.
     */
    public function issue(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $movement = $this->inventoryService->issueStock(
            $tenantId,
            (int) $validated['product_id'],
            $validated['product_variant_id'] ? (int) $validated['product_variant_id'] : null,
            (int) $validated['warehouse_id'],
            (float) $validated['quantity'],
            $validated['reason'],
            $request->user()
        );

        return $this->successResponse($movement, 'Stock issued successfully.');
    }

    /**
     * List stock transfers.
     */
    public function transfers(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = StockTransfer::where('tenant_id', $tenantId)
            ->with(['sourceWarehouse', 'destinationWarehouse', 'user', 'approver', 'items.product'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $transfers = $query->paginate($perPage);

        return $this->successResponse(
            $transfers->items(),
            'Transfers retrieved.',
            200,
            [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
            ]
        );
    }

    /**
     * Create transfer request (Draft / Pending Approval).
     */
    public function createTransferRequest(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'source_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.notes' => 'nullable|string',
        ]);

        $transfer = $this->inventoryService->createTransferRequest(
            tenantId: $tenantId,
            sourceWarehouseId: (int) $validated['source_warehouse_id'],
            destWarehouseId: (int) $validated['destination_warehouse_id'],
            items: $validated['items'],
            user: $request->user(),
            notes: $validated['notes'] ?? null
        );

        return $this->successResponse($transfer, 'Transfer request created.', 201);
    }

    /**
     * Approve transfer.
     */
    public function approveTransfer(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $transfer = $this->inventoryService->approveTransfer($tenantId, $id, $request->user());
        return $this->successResponse($transfer, 'Transfer approved.');
    }

    /**
     * Dispatch transfer (mark in transit & deduct source stock).
     */
    public function dispatchTransfer(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $transfer = $this->inventoryService->dispatchTransfer($tenantId, $id, $request->user());
        return $this->successResponse($transfer, 'Transfer dispatched and marked in transit.');
    }

    /**
     * Receive transfer at destination (records accepted in available, quarantines damaged).
     */
    public function receiveTransfer(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:stock_transfer_items,id',
            'items.*.accepted_quantity' => 'required|numeric|min:0',
            'items.*.damaged_quantity' => 'nullable|numeric|min:0',
            'damage_notes' => 'nullable|string',
        ]);

        $transfer = $this->inventoryService->receiveTransfer(
            tenantId: $tenantId,
            transferId: $id,
            receivedItems: $validated['items'],
            totalDamaged: null,
            damageNotes: $validated['damage_notes'] ?? null,
            receiver: $request->user()
        );

        return $this->successResponse($transfer, 'Transfer receipt completed.');
    }

    /**
     * List stock reconciliations.
     */
    public function reconciliations(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $recs = StockReconciliation::where('tenant_id', $tenantId)
            ->with(['warehouse', 'counter', 'reconciler', 'items.product'])
            ->orderByDesc('id')
            ->get();

        return $this->successResponse($recs);
    }

    /**
     * Create stock reconciliation / physical count.
     */
    public function createReconciliation(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.physical_quantity' => 'required|numeric|min:0',
            'items.*.reconciliation_action' => 'nullable|in:adjusted,flagged,ignored',
            'items.*.notes' => 'nullable|string',
        ]);

        $rec = $this->inventoryService->createStockReconciliation(
            tenantId: $tenantId,
            warehouseId: (int) $validated['warehouse_id'],
            items: $validated['items'],
            counter: $request->user(),
            notes: $validated['notes'] ?? null
        );

        return $this->successResponse($rec, 'Stock reconciliation initiated.', 201);
    }

    /**
     * Apply stock reconciliation adjustments to live stock.
     */
    public function applyReconciliation(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $rec = $this->inventoryService->applyStockReconciliation($tenantId, $id, $request->user());
        return $this->successResponse($rec, 'Reconciliation adjustments applied to live stock.');
    }
}
