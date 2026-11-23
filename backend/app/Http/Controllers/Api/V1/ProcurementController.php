<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Inventory\Services\PurchaseService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected PurchaseService $purchaseService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List suppliers.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->withCount('purchaseOrders')
            ->orderBy('name')
            ->get();

        return $this->successResponse($suppliers);
    }

    /**
     * Create a supplier.
     */
    public function createSupplier(Request $request): JsonResponse
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
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:50',
            'payment_terms' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $validated['code'] ?? ('SUP-' . strtoupper(substr($validated['name'], 0, 3))),
            'contact_name' => $validated['contact_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'tax_id' => $validated['tax_id'] ?? null,
            'payment_terms' => $validated['payment_terms'] ?? 'Net 30',
            'currency' => $validated['currency'] ?? 'USD',
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->successResponse($supplier, 'Supplier created successfully.', 201);
    }

    /**
     * List purchase orders.
     */
    public function purchaseOrders(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['supplier', 'warehouse', 'items.product'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $orders = $query->paginate($perPage);

        return $this->successResponse(
            $orders->items(),
            'Purchase orders retrieved.',
            200,
            [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        );
    }

    /**
     * Create purchase order.
     */
    public function createPurchaseOrder(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date',
            'status' => 'nullable|in:draft,ordered',
            'currency' => 'nullable|string|max:10',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $po = $this->purchaseService->createPurchaseOrder($tenantId, $validated, $request->user());

        return $this->successResponse($po, 'Purchase order created successfully.', 201);
    }

    /**
     * Goods Receipt Note (GRN): Receive items into stock.
     */
    public function receiveGoods(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $po = $this->purchaseService->receiveGoods(
            tenantId: $tenantId,
            purchaseOrderId: $id,
            receivedItems: $validated['items'],
            user: $request->user()
        );

        return $this->successResponse($po, 'Goods received into inventory successfully.');
    }
}
