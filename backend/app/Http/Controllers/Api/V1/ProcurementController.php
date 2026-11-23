<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inventory\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\PurchaseRequest;
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
            'pan_number' => 'nullable|string|max:20',
            'gstin' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:150',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_ifsc' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:100',
            'products_supplied' => 'nullable|array',
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
            'pan_number' => $validated['pan_number'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'bank_account_number' => $validated['bank_account_number'] ?? null,
            'bank_ifsc' => $validated['bank_ifsc'] ?? null,
            'bank_branch' => $validated['bank_branch'] ?? null,
            'products_supplied' => $validated['products_supplied'] ?? [],
            'outstanding_balance' => 0.00,
            'vendor_rating' => 5.00,
            'payment_terms' => $validated['payment_terms'] ?? 'Net 30',
            'currency' => $validated['currency'] ?? 'INR',
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->successResponse($supplier, 'Supplier created successfully.', 201);
    }

    /**
     * Get single supplier profile.
     */
    public function showSupplier(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $supplier = Supplier::where('tenant_id', $tenantId)
            ->with(['purchaseOrders.warehouse', 'bills'])
            ->findOrFail($id);

        $data = $supplier->toArray();
        // Mask bank account by default unless user has specific permission/admin role
        $canViewBank = $request->user()?->hasRole('admin') || $request->user()?->is_owner;
        if (!$canViewBank && !empty($supplier->bank_account_number)) {
            $data['bank_account_number'] = $supplier->masked_bank_account;
        }

        return $this->successResponse($data);
    }

    /**
     * View supplier unmasked bank details with restricted permission check.
     */
    public function supplierBankDetails(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $supplier = Supplier::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse([
            'bank_name' => $supplier->bank_name,
            'bank_account_number' => $supplier->bank_account_number,
            'bank_ifsc' => $supplier->bank_ifsc,
            'bank_branch' => $supplier->bank_branch,
        ], 'Supplier bank details retrieved under secure audit log.');
    }

    /**
     * Update supplier details.
     */
    public function updateSupplier(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $supplier = Supplier::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'contact_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:50',
            'pan_number' => 'nullable|string|max:20',
            'gstin' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:150',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_ifsc' => 'nullable|string|max:50',
            'bank_branch' => 'nullable|string|max:100',
            'products_supplied' => 'nullable|array',
            'payment_terms' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:10',
            'status' => 'nullable|in:active,inactive',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($validated);
        return $this->successResponse($supplier, 'Supplier updated successfully.');
    }

    /**
     * List purchase requests.
     */
    public function purchaseRequests(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = PurchaseRequest::where('tenant_id', $tenantId)
            ->with(['requester', 'approver', 'warehouse', 'items.product'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $prs = $query->paginate(min((int) $request->query('per_page', 25), 100));
        return $this->successResponse(
            $prs->items(),
            'Purchase requests retrieved.',
            200,
            [
                'current_page' => $prs->currentPage(),
                'last_page' => $prs->lastPage(),
                'per_page' => $prs->perPage(),
                'total' => $prs->total(),
            ]
        );
    }

    /**
     * Create purchase request.
     */
    public function createPurchaseRequest(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'department_id' => 'nullable|exists:departments,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.estimated_unit_cost' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $pr = $this->purchaseService->createPurchaseRequest($tenantId, $validated, $request->user());
        return $this->successResponse($pr, 'Purchase request created successfully.', 201);
    }

    /**
     * Approve purchase request.
     */
    public function approvePurchaseRequest(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $pr = $this->purchaseService->approvePurchaseRequest($tenantId, $id, $request->user());
        return $this->successResponse($pr, 'Purchase request approved.');
    }

    /**
     * Reject purchase request.
     */
    public function rejectPurchaseRequest(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate(['rejection_reason' => 'required|string']);
        $pr = $this->purchaseService->rejectPurchaseRequest($tenantId, $id, $validated['rejection_reason'], $request->user());
        return $this->successResponse($pr, 'Purchase request rejected.');
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
     * Get single purchase order.
     */
    public function showPurchaseOrder(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $po = PurchaseOrder::where('tenant_id', $tenantId)
            ->with(['supplier', 'warehouse', 'branch', 'approver', 'bill', 'goodsReceiptNotes.items', 'items.product', 'items.variant'])
            ->findOrFail($id);

        return $this->successResponse($po);
    }

    /**
     * Approve purchase order.
     */
    public function approvePurchaseOrder(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $po = $this->purchaseService->approvePurchaseOrder($tenantId, $id, $request->user());
        return $this->successResponse($po, 'Purchase order approved.');
    }

    /**
     * Revise purchase order.
     */
    public function revisePurchaseOrder(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $po = $this->purchaseService->revisePurchaseOrder($tenantId, $id, $request->all(), $request->user());
        return $this->successResponse($po, 'Purchase order revision saved.');
    }

    /**
     * Goods Receipt Note (GRN): Receive items into stock with QC inspection.
     */
    public function receiveGoods(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'received_date' => 'nullable|date',
            'challan_number' => 'nullable|string|max:100',
            'invoice_reference' => 'nullable|string|max:100',
            'qc_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.accepted_quantity' => 'nullable|numeric|min:0',
            'items.*.rejected_damaged_quantity' => 'nullable|numeric|min:0',
            'items.*.rejection_reason' => 'nullable|string|max:255',
            'items.*.notes' => 'nullable|string',
        ]);

        $grn = $this->purchaseService->receiveGoods(
            tenantId: $tenantId,
            purchaseOrderId: $id,
            receivedData: $validated,
            user: $request->user()
        );

        return $this->successResponse($grn, 'Goods received into inventory with GRN inspection.');
    }

    /**
     * Generate Vendor Bill from Purchase Order and link to Finance module.
     */
    public function createBill(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'bill_number' => 'nullable|string|max:100',
            'bill_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $bill = $this->purchaseService->generateVendorBill($tenantId, $id, $validated, $request->user());
        return $this->successResponse($bill, 'Vendor bill generated and linked to Finance.', 201);
    }

    /**
     * List Goods Receipt Notes (GRN).
     */
    public function goodsReceiptNotes(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $grns = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->with(['purchaseOrder.supplier', 'warehouse', 'receiver', 'items.product'])
            ->orderByDesc('id')
            ->get();

        return $this->successResponse($grns);
    }
}
