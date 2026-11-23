<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\InventoryService;
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
        protected InventoryService $inventoryService
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
}
