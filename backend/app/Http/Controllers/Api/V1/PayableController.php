<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\VendorBill;
use App\Domains\Finance\Models\VendorPayment;
use App\Domains\Finance\Services\PayableService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayableController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected PayableService $payableService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List vendor bills.
     */
    public function getBills(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = VendorBill::where('tenant_id', $tenantId)
            ->with(['supplier', 'approvedByUser'])
            ->orderBy('id', 'desc');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $bills = $query->paginate($request->query('per_page', 15));

        return $this->paginatedResponse($bills, 'Vendor bills retrieved.');
    }

    /**
     * Create vendor bill.
     */
    public function createBill(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'supplier_id' => 'required|integer',
            'bill_number' => 'nullable|string|max:50',
            'purchase_order_id' => 'nullable|integer',
            'bill_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'subtotal' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
            'file_attachment' => 'nullable|string|max:500',
        ]);

        $bill = $this->payableService->createBill($tenantId, $validated, $request->user());

        return $this->successResponse($bill, 'Vendor bill created successfully.');
    }

    /**
     * Approve vendor bill.
     */
    public function approveBill(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $bill = $this->payableService->approveBill($tenantId, $id, $request->user());

        return $this->successResponse($bill, 'Vendor bill approved for disbursement.');
    }

    /**
     * Record outgoing payment to vendor.
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'supplier_id' => 'nullable|integer',
            'vendor_bill_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'payment_method' => 'nullable|string|max:50',
            'payment_date' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->payableService->recordPayment($tenantId, $validated, $request->user());

        return $this->successResponse($payment, 'Vendor payment disbursed and recorded.');
    }

    /**
     * List vendor payments.
     */
    public function getPayments(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $payments = VendorPayment::where('tenant_id', $tenantId)
            ->with(['supplier', 'vendorBill'])
            ->orderBy('id', 'desc')
            ->paginate($request->query('per_page', 15));

        return $this->paginatedResponse($payments, 'Vendor payments retrieved.');
    }

    /**
     * Accounts Payable (AP) Ageing Report.
     */
    public function getAgeingReport(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $supplierId = $request->query('supplier_id') ? (int) $request->query('supplier_id') : null;

        $report = $this->payableService->getPayableAgeingReport($tenantId, $supplierId);

        return $this->successResponse($report, 'Accounts Payable ageing report retrieved.');
    }
}
