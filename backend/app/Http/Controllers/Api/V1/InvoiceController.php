<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\InvoiceBillingService;
use App\Domains\Finance\Services\PaymentGatewayService;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected InvoiceBillingService $invoiceBillingService,
        protected PaymentGatewayService $paymentGatewayService,
        protected RbacService $rbacService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List invoices
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && !$this->rbacService->can($user, 'invoices', 'view')) {
            abort(403, 'Unauthorized access: You do not have permission to view invoices.');
        }

        $tenantId = $this->getTenantId();
        $filters = $request->only(['status', 'customer_id', 'deal_id', 'search']);
        $perPage = (int) $request->query('per_page', 15);

        $invoices = $this->invoiceBillingService->listInvoices($tenantId, $filters, $perPage);

        return $this->paginatedResponse($invoices, 'Invoices retrieved successfully.');
    }

    /**
     * Create invoice
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'quotation_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'currency' => 'nullable|string|max:10',
            'discount_type' => 'nullable|string|in:fixed,percentage',
            'discount_rate' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_amount' => 'nullable|numeric|min:0',
            'is_tax_inclusive' => 'nullable|boolean',
            'payment_terms' => 'nullable|string',
            'billing_name' => 'nullable|string|max:255',
            'billing_email' => 'nullable|email|max:255',
            'billing_phone' => 'nullable|string|max:50',
            'billing_address' => 'nullable|string',
            'billing_tax_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'nullable|string|in:draft,issued',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_variant_id' => 'nullable|integer',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.discount_type' => 'nullable|string|in:fixed,percentage',
            'items.*.discount_rate' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.hsn_sac_code' => 'nullable|string|max:50',
        ]);

        $invoice = $this->invoiceBillingService->createInvoice($tenantId, $validated, $request->user());

        return $this->successResponse($invoice, 'Invoice created successfully.', 201);
    }

    /**
     * Show invoice details
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $invoice = Invoice::where('tenant_id', $tenantId)
            ->with(['items.product', 'customer', 'deal', 'contact', 'company', 'quotation', 'payments', 'refunds'])
            ->findOrFail($id);

        $user = request()->user();
        if ($user && !$this->rbacService->can($user, 'invoices', 'view', $invoice)) {
            abort(403, 'Unauthorized access: You do not have permission to view this invoice record.');
        }

        return $this->successResponse($invoice, 'Invoice details retrieved.');
    }

    /**
     * Issue invoice
     */
    public function issue(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $invoice = Invoice::where('tenant_id', $tenantId)->with('items')->findOrFail($id);

        $issued = $this->invoiceBillingService->issueInvoice($invoice, $request->user());

        return $this->successResponse($issued, 'Invoice issued successfully and inventory adjusted.');
    }

    /**
     * Void invoice
     */
    public function void(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($id);
        $reason = (string) $request->input('reason', '');

        $voided = $this->invoiceBillingService->voidInvoice($invoice, $reason, $request->user());

        return $this->successResponse($voided, 'Invoice voided successfully.');
    }

    /**
     * Record payment against invoice
     */
    public function recordPayment(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,card,upi,razorpay,cheque',
            'payment_channel' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $payment = $this->invoiceBillingService->recordPayment($tenantId, $id, $validated, $request->user());

        return $this->successResponse($payment, 'Payment recorded successfully.', 201);
    }

    /**
     * Create Razorpay Order / Payment Link
     */
    public function createPaymentLink(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($id);

        if ($invoice->outstanding_amount <= 0) {
            return $this->errorResponse('Invoice is already fully paid.', 422);
        }

        $order = $this->paymentGatewayService->createRazorpayOrder($invoice);

        return $this->successResponse([
            'order' => $order,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'outstanding_amount' => $invoice->outstanding_amount,
            'currency' => $invoice->currency,
            'payment_link' => $invoice->razorpay_payment_link,
            'key_id' => $this->paymentGatewayService->getKeyId(),
        ], 'Payment link generated successfully.');
    }

    /**
     * Verify Razorpay Client Payment and record payment
     */
    public function verifyPayment(int $id, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $isValid = $this->paymentGatewayService->verifyPaymentSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        );

        if (!$isValid) {
            return $this->errorResponse('Payment verification failed: Invalid cryptographic signature.', 400);
        }

        $payment = $this->invoiceBillingService->recordPayment(
            $tenantId,
            $invoice->id,
            [
                'amount' => $invoice->outstanding_amount,
                'currency' => $invoice->currency,
                'payment_method' => 'razorpay',
                'payment_channel' => 'razorpay',
                'gateway_order_id' => $validated['razorpay_order_id'],
                'gateway_payment_id' => $validated['razorpay_payment_id'],
                'gateway_signature' => $validated['razorpay_signature'],
                'notes' => 'Verified online payment via Razorpay checkout',
            ],
            $request->user()
        );

        return $this->successResponse($payment, 'Payment verified and recorded successfully.');
    }

    /**
     * Download or view HTML invoice
     */
    public function downloadPdf(int $id)
    {
        $tenantId = $this->getTenantId();
        $invoice = Invoice::where('tenant_id', $tenantId)->with(['items', 'customer'])->findOrFail($id);

        $html = $this->invoiceBillingService->generateDocumentHtml($invoice);

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "inline; filename=\"invoice-{$invoice->invoice_number}.html\"",
        ]);
    }

    /**
     * Delete draft invoice
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($id);

        if ($invoice->status !== 'draft') {
            return $this->errorResponse('Cannot delete an issued or paid invoice.', 422);
        }

        $invoice->delete();

        return $this->successResponse(null, 'Invoice deleted successfully.');
    }
}
