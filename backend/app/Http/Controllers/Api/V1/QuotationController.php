<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\Quotation;
use App\Domains\Finance\Services\InvoiceBillingService;
use App\Domains\Finance\Services\QuotationService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuotationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected QuotationService $quotationService,
        protected InvoiceBillingService $invoiceBillingService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List quotations
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $filters = $request->only(['status', 'customer_id', 'deal_id', 'search']);
        $perPage = (int) $request->query('per_page', 15);

        $quotations = $this->quotationService->listQuotations($tenantId, $filters, $perPage);

        return $this->paginatedResponse($quotations, 'Quotations retrieved successfully.');
    }

    /**
     * Store a new quotation
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'customer_id' => 'nullable|integer',
            'lead_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'currency' => 'nullable|string|max:10',
            'discount_type' => 'nullable|string|in:fixed,percentage',
            'discount_rate' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'is_tax_inclusive' => 'nullable|boolean',
            'terms_conditions' => 'nullable|string',
            'notes' => 'nullable|string',
            'valid_until' => 'nullable|date',
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

        $quotation = $this->quotationService->createQuotation($tenantId, $validated, $request->user());

        return $this->successResponse($quotation, 'Quotation created successfully.', 201);
    }

    /**
     * Show quotation details including items and revisions
     */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $quotation = Quotation::where('tenant_id', $tenantId)
            ->with(['items.product', 'customer', 'deal', 'contact', 'company', 'revisions'])
            ->findOrFail($id);

        return $this->successResponse($quotation, 'Quotation details retrieved.');
    }

    /**
     * Create a revision of this quotation
     */
    public function revise(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $changes = $request->all();

        $revision = $this->quotationService->createRevision($tenantId, $id, $changes, $request->user());

        return $this->successResponse($revision, 'New revision created successfully.', 201);
    }

    /**
     * Update quotation status
     */
    public function markStatus(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'status' => 'required|string|in:draft,sent,viewed,accepted,rejected,expired',
        ]);

        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);
        $updated = $this->quotationService->markStatus($quotation, $validated['status'], $request->user());

        return $this->successResponse($updated, "Quotation status updated to {$validated['status']}.");
    }

    /**
     * Convert quotation to Invoice
     */
    public function convertToInvoice(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $invoice = $this->quotationService->convertToInvoice($tenantId, $id, $request->user());

        return $this->successResponse($invoice, 'Quotation successfully converted to Invoice.', 201);
    }

    /**
     * Generate HTML printable document
     */
    public function downloadPdf(int $id)
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->with(['items', 'customer'])->findOrFail($id);

        $html = $this->invoiceBillingService->generateDocumentHtml($quotation);

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => "inline; filename=\"quotation-{$quotation->quotation_number}.html\"",
        ]);
    }

    /**
     * Submit quotation for manager approval.
     */
    public function submitForApproval(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);

        $comments = $request->input('comments');
        $updated = $this->quotationService->submitForApproval($quotation, $request->user(), $comments);

        return $this->successResponse($updated, 'Quotation submitted for approval.');
    }

    /**
     * Approve quotation.
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);

        $comments = $request->input('comments');
        $updated = $this->quotationService->approve($quotation, $request->user(), $comments);

        return $this->successResponse($updated, 'Quotation approved successfully.');
    }

    /**
     * Reject quotation.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);

        $reason = $request->validate(['reason' => 'required|string|min:3'])['reason'];
        $updated = $this->quotationService->reject($quotation, $request->user(), $reason);

        return $this->successResponse($updated, 'Quotation rejected.');
    }

    /**
     * Check discount against limits and file approval if needed.
     */
    public function checkDiscount(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);

        $rate = (float) $request->input('discount_rate', $quotation->discount_rate ?? 0);
        $reason = $request->input('reason');

        $result = $this->quotationService->checkDiscountApproval($quotation, $rate, $request->user(), $reason);

        return $this->successResponse($result);
    }

    /**
     * Record customer digital acceptance or rejection.
     */
    public function customerAction(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
            'notes' => 'nullable|string',
        ]);

        $ip = $request->ip() ?? '127.0.0.1';
        $updated = $this->quotationService->recordCustomerAction($quotation, $validated['action'], $ip, $validated['notes'] ?? null);

        return $this->successResponse($updated, "Customer {$validated['action']} action recorded.");
    }

    /**
     * Send quotation via Email or WhatsApp.
     */
    public function sendVia(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'channel' => 'required|in:email,whatsapp,portal',
            'recipient' => 'required|string',
        ]);

        $quotation->update([
            'status' => 'sent',
            'sent_via' => $validated['channel'],
            'sent_at' => now(),
        ]);

        return $this->successResponse($quotation, "Quotation sent via {$validated['channel']} to {$validated['recipient']}.");
    }

    /**
     * Delete draft quotation
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotation = Quotation::where('tenant_id', $tenantId)->findOrFail($id);

        if ($quotation->status === 'accepted') {
            return $this->errorResponse('Cannot delete an accepted quotation.', 422);
        }

        $quotation->delete();

        return $this->successResponse(null, 'Quotation deleted successfully.');
    }
}
