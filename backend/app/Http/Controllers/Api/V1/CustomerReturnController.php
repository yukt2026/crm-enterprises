<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Inventory\Models\CustomerReturn;
use App\Domains\Inventory\Services\CustomerReturnService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReturnController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected CustomerReturnService $returnService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List customer return requests.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = CustomerReturn::where('tenant_id', $tenantId)
            ->with(['customer', 'invoice', 'warehouse', 'creditNote', 'items.product'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }

        $returns = $query->paginate(min((int) $request->query('per_page', 25), 100));

        return $this->successResponse(
            $returns->items(),
            'Customer returns retrieved.',
            200,
            [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ]
        );
    }

    /**
     * Create customer return request (RMA).
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'order_reference' => 'nullable|string|max:100',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'return_reason' => 'required|string|max:255',
            'product_condition' => 'nullable|in:unopened,opened_good,defective,damaged_in_transit',
            'restock_action' => 'nullable|in:restock_available,quarantine_damage,scrap,return_to_vendor',
            'refund_type' => 'nullable|in:credit_note,bank_refund,replacement',
            'replacement_product_id' => 'nullable|exists:products,id',
            'inspection_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.condition_notes' => 'nullable|string',
        ]);

        $return = $this->returnService->createReturnRequest($tenantId, $validated, $request->user());

        return $this->successResponse($return, 'Customer return request (RMA) created successfully.', 201);
    }

    /**
     * Show return details.
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $return = CustomerReturn::where('tenant_id', $tenantId)
            ->with(['customer', 'invoice', 'warehouse', 'creditNote', 'replacementProduct', 'inspector', 'approver', 'items.product', 'items.variant'])
            ->findOrFail($id);

        return $this->successResponse($return);
    }

    /**
     * Inspect returned goods and determine condition / restock action.
     */
    public function inspect(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'product_condition' => 'required|in:unopened,opened_good,defective,damaged_in_transit',
            'restock_action' => 'required|in:restock_available,quarantine_damage,scrap,return_to_vendor',
            'inspection_notes' => 'nullable|string',
        ]);

        $return = $this->returnService->inspectReturn(
            $tenantId,
            $id,
            $validated['product_condition'],
            $validated['restock_action'],
            $validated['inspection_notes'] ?? null,
            $request->user()
        );

        return $this->successResponse($return, 'Return inspection completed.');
    }

    /**
     * Approve return: restocks sound items to available inventory or quarantines damage, and issues Credit Note.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $return = $this->returnService->approveReturnAndRestock($tenantId, $id, $request->user());

        return $this->successResponse($return, 'Customer return approved and processed successfully.');
    }

    /**
     * Return analysis report.
     */
    public function reports(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $returns = CustomerReturn::where('tenant_id', $tenantId)->get();

        $totalReturns = count($returns);
        $totalRefundAmount = (float) $returns->sum('total_refund_amount');
        $damagedCount = $returns->whereIn('product_condition', ['defective', 'damaged_in_transit'])->count();
        $damageRate = $totalReturns > 0 ? round(($damagedCount / $totalReturns) * 100, 1) : 0;

        return $this->successResponse([
            'total_returns' => $totalReturns,
            'total_refund_amount' => round($totalRefundAmount, 2),
            'damaged_returns_count' => $damagedCount,
            'damage_rate_percent' => $damageRate,
            'by_condition' => [
                'unopened' => $returns->where('product_condition', 'unopened')->count(),
                'opened_good' => $returns->where('product_condition', 'opened_good')->count(),
                'defective' => $returns->where('product_condition', 'defective')->count(),
                'damaged_in_transit' => $returns->where('product_condition', 'damaged_in_transit')->count(),
            ],
            'by_status' => [
                'requested' => $returns->where('status', 'requested')->count(),
                'inspecting' => $returns->where('status', 'inspecting')->count(),
                'completed' => $returns->where('status', 'completed')->count(),
            ],
        ]);
    }
}
