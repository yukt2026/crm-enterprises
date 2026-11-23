<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\InvoiceBillingService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected InvoiceBillingService $invoiceBillingService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List payment transactions
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $perPage = (int) $request->query('per_page', 15);

        $query = Payment::where('tenant_id', $tenantId)
            ->with(['invoice', 'customer', 'user', 'refunds'])
            ->orderBy('id', 'desc');

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->query('invoice_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', $search)
                  ->orWhere('reference_number', 'like', $search)
                  ->orWhere('gateway_payment_id', 'like', $search);
            });
        }

        $payments = $query->paginate($perPage);

        return $this->paginatedResponse($payments, 'Payment transactions retrieved.');
    }

    /**
     * Show payment details
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $payment = Payment::where('tenant_id', $tenantId)
            ->with(['invoice.items', 'customer', 'user', 'refunds'])
            ->findOrFail($id);

        return $this->successResponse($payment, 'Payment details retrieved.');
    }

    /**
     * Process refund against payment
     */
    public function refund(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $refund = $this->invoiceBillingService->processRefund(
            $tenantId,
            $id,
            (float) $validated['amount'],
            $validated['reason'],
            $request->user()
        );

        return $this->successResponse($refund, 'Refund processed successfully.', 201);
    }
}
