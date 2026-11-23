<?php

namespace App\Domains\Finance\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\GstRegistration;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GstInvoiceService
{
    public function __construct(
        protected GstTaxCalculationService $taxCalculationService
    ) {}

    /**
     * Get or create active default GST registration for tenant.
     */
    public function getActiveGstRegistration(int $tenantId, ?int $branchId = null): GstRegistration
    {
        $query = GstRegistration::where('tenant_id', $tenantId);
        if ($branchId) {
            $branchReg = (clone $query)->where('branch_id', $branchId)->first();
            if ($branchReg) {
                return $branchReg;
            }
        }

        $reg = $query->where('is_default', true)->first();
        if (!$reg) {
            $reg = GstRegistration::create([
                'tenant_id' => $tenantId,
                'business_legal_name' => 'Acme Enterprise India Pvt Ltd',
                'trade_name' => 'Acme Enterprise',
                'gstin' => '27AABCA1234D1Z5',
                'pan' => 'AABCA1234D',
                'registered_address' => 'Floor 4, Enterprise Tower, BKC, Bandra East',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'state_code' => '27',
                'pincode' => '400051',
                'business_type' => 'private_limited',
                'gst_registration_status' => 'active',
                'tax_calculation_preference' => 'exclusive',
                'default_place_of_supply' => '27',
                'invoice_prefix' => 'INV-',
                'financial_year' => '2026-27',
                'invoice_numbering_series' => 'sequential',
                'is_default' => true,
            ]);
        }

        return $reg;
    }

    /**
     * Generate document number based on document type and series.
     */
    public function generateDocumentNumber(int $tenantId, string $documentType, ?GstRegistration $gstReg = null): string
    {
        $reg = $gstReg ?? $this->getActiveGstRegistration($tenantId);
        $fy = str_replace('-', '', substr($reg->financial_year, 2, 2) . substr($reg->financial_year, 5, 2)); // e.g. "2627"

        $prefix = match ($documentType) {
            'tax_invoice' => $reg->invoice_prefix ?: 'INV-',
            'credit_note' => 'CN-',
            'debit_note' => 'DN-',
            'bill_of_supply' => 'BOS-',
            'delivery_challan' => 'DC-',
            'proforma' => 'PI-',
            'export_invoice' => 'EXP-',
            'purchase_voucher' => 'PV-',
            default => 'DOC-',
        };

        $count = Invoice::where('tenant_id', $tenantId)
            ->where('document_type', $documentType)
            ->count() + 1;

        return sprintf('%s%s-%04d', $prefix, $fy, $count);
    }

    /**
     * Create or update a GST Invoice with line items and legal tax breakdowns.
     */
    public function createGstInvoice(int $tenantId, array $data, ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($tenantId, $data, $user) {
            $documentType = $data['document_type'] ?? 'tax_invoice';
            $gstRegId = $data['gst_registration_id'] ?? null;
            $gstReg = $gstRegId
                ? GstRegistration::where('tenant_id', $tenantId)->findOrFail($gstRegId)
                : $this->getActiveGstRegistration($tenantId);

            $sellerStateCode = $gstReg->state_code;
            $placeOfSupplyCode = $data['place_of_supply_state_code'] ?? $gstReg->default_place_of_supply;
            $placeOfSupplyName = $this->taxCalculationService->getStateName($placeOfSupplyCode);

            $buyerTreatment = $data['buyer_gst_treatment'] ?? 'taxable';
            $isExport = ($documentType === 'export_invoice') || !empty($data['export_type']);
            $exportType = $data['export_type'] ?? ($isExport ? 'with_payment' : 'with_payment');

            $supplyType = $this->taxCalculationService->determineSupplyType(
                $sellerStateCode,
                $placeOfSupplyCode,
                $buyerTreatment,
                $isExport
            );

            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? ($gstReg->tax_calculation_preference === 'inclusive'));
            $isReverseCharge = (bool) ($data['is_reverse_charge'] ?? false);

            $rawItems = $data['items'] ?? [
                [
                    'item_name' => 'Enterprise Consulting Service',
                    'quantity' => 1,
                    'unit_price' => 100000,
                    'discount_amount' => 0,
                    'tax_rate' => 18,
                    'cess_rate' => 0,
                    'hsn_sac_code' => '998311',
                    'unit' => 'HRS',
                    'is_service' => true,
                ]
            ];

            // Run tax calculation engine
            $calc = $this->taxCalculationService->calculateInvoiceGst(
                $rawItems,
                $supplyType,
                $isTaxInclusive,
                $isReverseCharge,
                $exportType
            );

            $invoiceNumber = $data['invoice_number'] ?? $this->generateDocumentNumber($tenantId, $documentType, $gstReg);

            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'gst_registration_id' => $gstReg->id,
                'invoice_number' => $invoiceNumber,
                'document_type' => $documentType,
                'customer_id' => $data['customer_id'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'seller_gstin' => $gstReg->gstin,
                'seller_legal_name' => $gstReg->business_legal_name,
                'seller_state_code' => $sellerStateCode,
                'buyer_gstin' => $data['buyer_gstin'] ?? null,
                'buyer_legal_name' => $data['buyer_legal_name'] ?? $data['billing_name'] ?? 'Walk-in Client',
                'buyer_trade_name' => $data['buyer_trade_name'] ?? null,
                'buyer_state_code' => $data['buyer_state_code'] ?? $placeOfSupplyCode,
                'buyer_gst_treatment' => $buyerTreatment,
                'place_of_supply_state_code' => $placeOfSupplyCode,
                'place_of_supply_state_name' => $placeOfSupplyName,
                'supply_type' => $supplyType,
                'is_reverse_charge' => $isReverseCharge,
                'currency' => $data['currency'] ?? 'INR',
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['discount_amount'],
                'tax_rate' => count($calc['items']) > 0 ? $calc['items'][0]['tax_rate'] : 18.00,
                'tax_amount' => $calc['total_tax_amount'],
                'cgst_amount' => $calc['cgst_amount'],
                'sgst_amount' => $calc['sgst_amount'],
                'igst_amount' => $calc['igst_amount'],
                'cess_amount' => $calc['cess_amount'],
                'total_amount' => $calc['grand_total'],
                'round_off_amount' => $calc['round_off_amount'],
                'amount_in_words' => $calc['amount_in_words'],
                'outstanding_amount' => $calc['grand_total'],
                'paid_amount' => 0.00,
                'status' => $data['status'] ?? 'draft',
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'payment_terms' => $data['payment_terms'] ?? 'Net 30 Days',
                'billing_name' => $data['billing_name'] ?? $data['buyer_legal_name'] ?? 'Client',
                'billing_email' => $data['billing_email'] ?? null,
                'billing_phone' => $data['billing_phone'] ?? null,
                'billing_address' => $data['billing_address'] ?? 'Customer Address',
                'billing_tax_id' => $data['buyer_gstin'] ?? null,
                'notes' => $data['notes'] ?? null,
                'bank_name' => $data['bank_name'] ?? 'HDFC Bank Ltd',
                'bank_account_number' => $data['bank_account_number'] ?? '50200012345678',
                'bank_ifsc' => $data['bank_ifsc'] ?? 'HDFC0000060',
                'bank_branch' => $data['bank_branch'] ?? 'BKC Mumbai',
                'terms_and_conditions' => $data['terms_and_conditions'] ?? '1. Payment due within 30 days. 2. Interest @ 18% p.a. applicable on delayed payments.',
                'original_invoice_id' => $data['original_invoice_id'] ?? null,
                'original_invoice_number' => $data['original_invoice_number'] ?? null,
                'original_invoice_date' => $data['original_invoice_date'] ?? null,
                'reason_for_issuance' => $data['reason_for_issuance'] ?? null,
                'export_type' => $exportType,
                'shipping_bill_number' => $data['shipping_bill_number'] ?? null,
                'shipping_bill_date' => $data['shipping_bill_date'] ?? null,
                'port_code' => $data['port_code'] ?? null,
                'issued_at' => ($data['status'] ?? '') === 'issued' ? now() : null,
            ]);

            // Save line items
            foreach ($calc['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_name' => $item['item_name'] ?? 'Product/Service',
                    'is_service' => (bool) ($item['is_service'] ?? false),
                    'unit' => $item['unit'] ?? 'PCS',
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'taxable_amount' => $item['taxable_amount'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'cgst_rate' => $item['cgst_rate'],
                    'cgst_amount' => $item['cgst_amount'],
                    'sgst_rate' => $item['sgst_rate'],
                    'sgst_amount' => $item['sgst_amount'],
                    'igst_rate' => $item['igst_rate'],
                    'igst_amount' => $item['igst_amount'],
                    'cess_rate' => $item['cess_rate'],
                    'cess_amount' => $item['cess_amount'],
                    'total_price' => $item['total_price'],
                    'hsn_sac_code' => $item['hsn_sac_code'] ?? '998311',
                ]);
            }

            return $invoice->load(['items', 'gstRegistration']);
        });
    }

    /**
     * Finalize draft invoice to legally issued state.
     */
    public function finalizeInvoice(int $tenantId, int $invoiceId, ?User $user = null): Invoice
    {
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($invoiceId);

        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Invoice cannot be finalized because its status is already '{$invoice->status}'.",
            ]);
        }

        $invoice->status = 'issued';
        $invoice->issued_at = now();
        $invoice->save();

        return $invoice->load(['items', 'gstRegistration']);
    }

    /**
     * Create Credit Note against an existing Invoice.
     */
    public function createCreditNote(int $tenantId, int $invoiceId, array $data, ?User $user = null): Invoice
    {
        $original = Invoice::where('tenant_id', $tenantId)->with('items')->findOrFail($invoiceId);

        $reason = $data['reason'] ?? 'Sales Return / Deficiency in Service';
        $creditAmount = round((float) ($data['amount'] ?? $original->total_amount), 2);

        // Derive credit items proportion or single line
        $ratio = ($original->total_amount > 0) ? ($creditAmount / $original->total_amount) : 1;
        $creditItems = [];

        foreach ($original->items as $item) {
            $creditItems[] = [
                'item_name' => 'Credit Note Adjustment: ' . $item->item_name,
                'quantity' => 1,
                'unit_price' => round($item->taxable_amount * $ratio, 2),
                'discount_amount' => 0,
                'tax_rate' => $item->tax_rate,
                'cess_rate' => $item->cess_rate,
                'hsn_sac_code' => $item->hsn_sac_code,
                'unit' => $item->unit,
                'is_service' => $item->is_service,
            ];
        }

        $creditData = [
            'document_type' => 'credit_note',
            'customer_id' => $original->customer_id,
            'buyer_gstin' => $original->buyer_gstin,
            'buyer_legal_name' => $original->buyer_legal_name,
            'buyer_state_code' => $original->buyer_state_code,
            'place_of_supply_state_code' => $original->place_of_supply_state_code,
            'original_invoice_id' => $original->id,
            'original_invoice_number' => $original->invoice_number,
            'original_invoice_date' => $original->issued_at?->toDateString() ?: $original->created_at->toDateString(),
            'reason_for_issuance' => $reason,
            'status' => 'issued',
            'items' => $creditItems,
        ];

        return $this->createGstInvoice($tenantId, $creditData, $user);
    }
}
