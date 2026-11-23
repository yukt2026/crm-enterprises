<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Models\GstRegistration;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Services\ComplianceService;
use App\Domains\Finance\Services\EInvoiceService;
use App\Domains\Finance\Services\EWayBillService;
use App\Domains\Finance\Services\GstInvoiceService;
use App\Domains\Finance\Services\GstReportService;
use App\Domains\Finance\Services\GstTaxCalculationService;
use App\Domains\Finance\Services\GstVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GstComplianceController extends Controller
{
    public function __construct(
        protected GstInvoiceService $invoiceService,
        protected GstTaxCalculationService $taxCalculationService,
        protected EInvoiceService $eInvoiceService,
        protected EWayBillService $eWayBillService,
        protected GstVerificationService $verificationService,
        protected GstReportService $reportService,
        protected ComplianceService $complianceService
    ) {}

    protected function getTenantId(Request $request): int
    {
        return (int) ($request->user()?->current_tenant_id ?? 1);
    }

    /**
     * Get active GST registrations.
     */
    public function getGstRegistrations(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $registrations = GstRegistration::where('tenant_id', $tenantId)->get();

        if ($registrations->isEmpty()) {
            $default = $this->invoiceService->getActiveGstRegistration($tenantId);
            $registrations = collect([$default]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $registrations,
        ]);
    }

    /**
     * Create or update GST registration.
     */
    public function saveGstRegistration(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'business_legal_name' => 'required|string|max:200',
            'trade_name' => 'nullable|string|max:200',
            'gstin' => 'required|string|size:15',
            'pan' => 'nullable|string|size:10',
            'registered_address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'state_code' => 'required|string|size:2',
            'pincode' => 'required|string|max:10',
            'business_type' => 'nullable|string',
            'gst_registration_status' => 'nullable|string',
            'tax_calculation_preference' => 'nullable|in:exclusive,inclusive',
            'default_place_of_supply' => 'nullable|string|size:2',
            'invoice_prefix' => 'nullable|string|max:20',
            'financial_year' => 'nullable|string|max:10',
            'invoice_numbering_series' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($data['is_default'])) {
            GstRegistration::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        if (!empty($data['id'])) {
            $reg = GstRegistration::where('tenant_id', $tenantId)->findOrFail($data['id']);
            $reg->update($data);
        } else {
            $data['tenant_id'] = $tenantId;
            $reg = GstRegistration::create($data);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'GST Registration configuration saved successfully.',
            'data' => $reg,
        ]);
    }

    /**
     * Verify GSTIN via format validation and live lookup.
     */
    public function verifyGstin(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'gstin' => 'required|string|max:50',
        ]);

        $result = $this->verificationService->verifyGstin($tenantId, $data['gstin']);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Preview GST calculation for given items and place of supply.
     */
    public function calculateTax(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $sellerReg = $this->invoiceService->getActiveGstRegistration($tenantId);

        $posCode = $request->input('place_of_supply_state_code', $sellerReg->default_place_of_supply);
        $supplyType = $this->taxCalculationService->determineSupplyType(
            $sellerReg->state_code,
            $posCode,
            $request->input('buyer_gst_treatment', 'taxable'),
            (bool) $request->input('is_export', false)
        );

        $items = $request->input('items', []);
        $calc = $this->taxCalculationService->calculateInvoiceGst(
            $items,
            $supplyType,
            (bool) $request->input('is_tax_inclusive', false),
            (bool) $request->input('is_reverse_charge', false),
            $request->input('export_type', 'with_payment')
        );

        return response()->json([
            'status' => 'success',
            'data' => $calc,
        ]);
    }

    /**
     * List GST invoices and documents.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $query = Invoice::where('tenant_id', $tenantId)->with(['items', 'gstRegistration'])->orderBy('created_at', 'desc');

        if ($request->has('document_type')) {
            $query->where('document_type', $request->input('document_type'));
        }
        if ($request->has('einvoice_status')) {
            $query->where('einvoice_status', $request->input('einvoice_status'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate(25),
        ]);
    }

    /**
     * Create a GST invoice or document.
     */
    public function createInvoice(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'document_type' => 'nullable|string',
            'customer_id' => 'nullable|integer',
            'buyer_gstin' => 'nullable|string',
            'buyer_legal_name' => 'nullable|string',
            'buyer_trade_name' => 'nullable|string',
            'buyer_state_code' => 'nullable|string',
            'buyer_gst_treatment' => 'nullable|string',
            'place_of_supply_state_code' => 'nullable|string',
            'is_tax_inclusive' => 'nullable|boolean',
            'is_reverse_charge' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.cess_rate' => 'nullable|numeric|min:0',
            'items.*.hsn_sac_code' => 'nullable|string',
            'items.*.unit' => 'nullable|string',
            'items.*.is_service' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:draft,issued',
            'export_type' => 'nullable|string',
            'shipping_bill_number' => 'nullable|string',
            'shipping_bill_date' => 'nullable|date',
            'port_code' => 'nullable|string',
        ]);

        $invoice = $this->invoiceService->createGstInvoice($tenantId, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'GST document created successfully.',
            'data' => $invoice,
        ], 201);
    }

    /**
     * Finalize draft invoice.
     */
    public function finalizeInvoice(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $invoice = $this->invoiceService->finalizeInvoice($tenantId, $id, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Invoice finalized and officially issued.',
            'data' => $invoice,
        ]);
    }

    /**
     * Create Credit Note against an existing invoice.
     */
    public function createCreditNote(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        $creditNote = $this->invoiceService->createCreditNote($tenantId, $id, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'GST Credit Note generated successfully.',
            'data' => $creditNote,
        ], 201);
    }

    /**
     * Submit invoice for E-Invoicing (IRP).
     */
    public function generateEInvoice(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $invoice = $this->eInvoiceService->generateEInvoice($tenantId, $id, $request->user(), true);

        return response()->json([
            'status' => 'success',
            'message' => 'E-Invoice IRN and signed QR code generated.',
            'data' => $invoice,
        ]);
    }

    /**
     * Cancel an E-Invoice.
     */
    public function cancelEInvoice(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $invoice = $this->eInvoiceService->cancelEInvoice($tenantId, $id, $data['reason'], $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'E-Invoice cancelled successfully.',
            'data' => $invoice,
        ]);
    }

    /**
     * Generate E-Way Bill for an invoice.
     */
    public function generateEWayBill(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'distance_km' => 'required|numeric|min:1',
            'vehicle_number' => 'required|string|max:32',
            'transporter_name' => 'nullable|string|max:128',
            'transporter_id' => 'nullable|string|max:64',
            'transporter_doc_no' => 'nullable|string|max:64',
            'transport_mode' => 'nullable|in:road,rail,air,ship',
            'vehicle_type' => 'nullable|in:regular,over_dimensional_cargo',
        ]);

        $invoice = $this->eWayBillService->generateEWayBill($tenantId, $id, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'E-Way Bill generated successfully.',
            'data' => $invoice,
        ]);
    }

    /**
     * Update vehicle for E-Way Bill (Part B update).
     */
    public function updateEWayBillVehicle(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'vehicle_number' => 'required|string|max:32',
        ]);

        $invoice = $this->eWayBillService->updateVehicle($tenantId, $id, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'E-Way Bill vehicle details updated.',
            'data' => $invoice,
        ]);
    }

    /**
     * Cancel E-Way Bill.
     */
    public function cancelEWayBill(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $invoice = $this->eWayBillService->cancelEWayBill($tenantId, $id, $data['reason'], $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'E-Way Bill cancelled successfully.',
            'data' => $invoice,
        ]);
    }

    /**
     * Comprehensive GST tax liability and summary report.
     */
    public function getTaxSummary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $summary = $this->reportService->getTaxSummary(
            $tenantId,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'status' => 'success',
            'data' => $summary,
        ]);
    }

    /**
     * GSTR-1 Sales Register.
     */
    public function getSalesRegister(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $sales = $this->reportService->getSalesRegisterGstr1(
            $tenantId,
            $request->input('start_date'),
            $request->input('end_date')
        );

        return response()->json([
            'status' => 'success',
            'data' => $sales,
        ]);
    }

    /**
     * HSN/SAC summary.
     */
    public function getHsnSummary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $hsn = $this->reportService->getHsnSacSummary($tenantId);

        return response()->json([
            'status' => 'success',
            'data' => $hsn,
        ]);
    }

    /**
     * Download Sales Register CSV.
     */
    public function exportSalesRegisterCsv(Request $request): StreamedResponse
    {
        $tenantId = $this->getTenantId($request);
        $csvData = $this->reportService->exportSalesRegisterCsv($tenantId);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="GST_Sales_Register_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($csvData) {
            echo $csvData;
        }, 200, $headers);
    }

    /**
     * List compliance calendar reminders.
     */
    public function getComplianceReminders(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $reminders = $this->complianceService->getComplianceReminders(
            $tenantId,
            $request->input('type'),
            $request->input('status')
        );

        return response()->json([
            'status' => 'success',
            'data' => $reminders,
        ]);
    }

    /**
     * Create compliance reminder.
     */
    public function createComplianceReminder(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'compliance_type' => 'required|string|max:50',
            'due_date' => 'required|date',
            'financial_period' => 'nullable|string|max:50',
            'responsible_accountant_name' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|max:30',
            'priority' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $reminder = $this->complianceService->createReminder($tenantId, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Compliance reminder created successfully.',
            'data' => $reminder,
        ], 201);
    }

    /**
     * Complete a compliance reminder.
     */
    public function completeComplianceReminder(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $reminder = $this->complianceService->completeReminder($tenantId, $id, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Compliance task marked as completed.',
            'data' => $reminder,
        ]);
    }

    /**
     * List compliance vault documents.
     */
    public function getComplianceDocuments(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $documents = $this->complianceService->getDocuments($tenantId, $request->input('type'));

        return response()->json([
            'status' => 'success',
            'data' => $documents,
        ]);
    }

    /**
     * Store uploaded compliance document in vault.
     */
    public function storeComplianceDocument(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId($request);
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'document_type' => 'required|string|max:50',
            'document_number' => 'nullable|string|max:100',
            'file_name' => 'nullable|string|max:255',
            'file_path' => 'nullable|string|max:500',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'version' => 'nullable|string|max:20',
            'tags' => 'nullable|array',
        ]);

        $doc = $this->complianceService->storeDocument($tenantId, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Compliance document saved to vault.',
            'data' => $doc,
        ], 201);
    }
}
