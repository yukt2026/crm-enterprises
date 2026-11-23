<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\FinanceTransaction;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentGatewayConfig;
use App\Domains\Finance\Models\PaymentLink;
use App\Domains\Finance\Models\Refund;
use App\Domains\Finance\Services\AdvancedPaymentService;
use App\Domains\Finance\Services\PaymentGatewayService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedPaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected AdvancedPaymentService $paymentService,
        protected PaymentGatewayService $gatewayService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List payment gateways configuration.
     */
    public function getGateways(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $gateways = PaymentGatewayConfig::where('tenant_id', $tenantId)->get();

        return $this->successResponse($gateways, 'Payment gateways retrieved.');
    }

    /**
     * Configure or update payment gateway.
     */
    public function saveGateway(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'provider' => 'required|in:razorpay,cashfree,upi_qr,bank_transfer',
            'display_name' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'is_test_mode' => 'boolean',
            'credentials' => 'nullable|array',
            'upi_vpa' => 'nullable|string|max:150',
            'merchant_name' => 'nullable|string|max:150',
            'bank_account_details' => 'nullable|array',
            'webhook_secret' => 'nullable|string|max:255',
        ]);

        $config = $this->gatewayService->saveGatewayConfig($tenantId, $validated);

        return $this->successResponse($config, 'Payment gateway configuration saved.');
    }

    /**
     * Generate dynamic NPCI UPI QR payload.
     */
    public function generateUpiQr(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'vpa' => 'nullable|string|max:100',
            'merchant_name' => 'nullable|string|max:100',
            'reference_id' => 'nullable|string|max:50',
            'note' => 'nullable|string|max:100',
        ]);

        $vpa = $validated['vpa'] ?? 'billing@enterpriseupi';
        $merchant = $validated['merchant_name'] ?? 'Enterprise CRM';
        $ref = $validated['reference_id'] ?? ('UPI' . time());
        $note = $validated['note'] ?? 'CRM Invoice Payment';

        $upiData = $this->gatewayService->generateDynamicUpiPayload($vpa, $merchant, (float) $validated['amount'], $ref, $note);

        return $this->successResponse($upiData, 'Dynamic UPI QR payload generated.');
    }

    /**
     * Create payment link.
     */
    public function createPaymentLink(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'min_partial_amount' => 'nullable|numeric|min:1',
            'amount_type' => 'nullable|in:fixed,partial,advance,due_amount',
            'currency' => 'nullable|string|max:10',
            'customer_id' => 'nullable|integer',
            'payable_type' => 'nullable|string',
            'payable_id' => 'nullable|integer',
            'gateway_provider' => 'nullable|string|in:razorpay,cashfree,upi_qr,bank_transfer',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $link = $this->paymentService->createPaymentLink($tenantId, $validated, $request->user());

        return $this->successResponse($link, 'Payment link generated successfully.');
    }

    /**
     * List payment links.
     */
    public function getPaymentLinks(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $links = PaymentLink::where('tenant_id', $tenantId)
            ->with(['customer', 'payable'])
            ->orderBy('id', 'desc')
            ->paginate($request->query('per_page', 15));

        return $this->paginatedResponse($links, 'Payment links retrieved.');
    }

    /**
     * Get payment link details by token (public or authenticated).
     */
    public function getPaymentLinkByToken(string $token): JsonResponse
    {
        $link = PaymentLink::where('link_token', $token)->with(['customer', 'payable'])->firstOrFail();

        return $this->successResponse($link, 'Payment link details retrieved.');
    }

    /**
     * Webhook ingestion endpoint with idempotency and signature verification.
     */
    public function handleWebhook(Request $request, string $provider): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID') ?? $this->getTenantId();
        $signature = $request->header('X-Razorpay-Signature') ?? $request->header('x-webhook-signature');

        $payload = $request->all();
        $result = $this->paymentService->processWebhookPayment($tenantId, $provider, $payload, $signature);

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully.',
            'data' => $result,
        ]);
    }

    /**
     * Allocate payment across invoices.
     */
    public function allocatePayment(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'invoice_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $allocation = $this->paymentService->allocatePayment(
            $tenantId,
            $id,
            $validated['invoice_id'],
            (float) $validated['amount'],
            $request->user()
        );

        return $this->successResponse($allocation, 'Payment successfully allocated to invoice.');
    }

    /**
     * Request refund with approval workflow.
     */
    public function requestRefund(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $refund = $this->paymentService->requestRefund(
            $tenantId,
            $id,
            (float) $validated['amount'],
            $validated['reason'],
            $request->user()
        );

        return $this->successResponse($refund, 'Refund request submitted for manager approval.');
    }

    /**
     * Approve refund request and disburse/generate credit note.
     */
    public function approveRefund(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $refund = $this->paymentService->approveRefund($tenantId, $id, $request->user(), $validated['notes'] ?? null);

        return $this->successResponse($refund, 'Refund approved and executed successfully.');
    }

    /**
     * List refunds.
     */
    public function getRefunds(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $refunds = Refund::where('tenant_id', $tenantId)
            ->with(['payment', 'invoice', 'creditNote', 'approvedByUser'])
            ->orderBy('id', 'desc')
            ->paginate($request->query('per_page', 15));

        return $this->paginatedResponse($refunds, 'Refund records retrieved.');
    }

    /**
     * List centralized financial transactions (General Transaction Register).
     */
    public function getTransactions(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = FinanceTransaction::where('tenant_id', $tenantId)
            ->with(['payment', 'invoice', 'customer', 'reconciledByUser'])
            ->orderBy('id', 'desc');

        if ($request->filled('reconciliation_status')) {
            $query->where('reconciliation_status', $request->query('reconciliation_status'));
        }

        if ($request->filled('internal_status')) {
            $query->where('internal_status', $request->query('internal_status'));
        }

        $txns = $query->paginate($request->query('per_page', 20));

        return $this->paginatedResponse($txns, 'Financial transactions retrieved.');
    }

    /**
     * Reconcile transaction.
     */
    public function reconcileTransaction(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'status' => 'required|in:matched,unreconciled,discrepancy,manual_override',
            'notes' => 'nullable|string|max:500',
        ]);

        $txn = $this->paymentService->reconcileTransaction($tenantId, $id, $validated['status'], $request->user(), $validated['notes'] ?? null);

        return $this->successResponse($txn, 'Transaction reconciled successfully.');
    }
}
