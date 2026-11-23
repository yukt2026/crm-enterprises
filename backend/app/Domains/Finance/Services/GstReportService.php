<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceItem;
use App\Domains\Finance\Models\VendorBill;
use Illuminate\Support\Facades\DB;

class GstReportService
{
    /**
     * Generate Comprehensive GST Tax Summary (Output vs Input vs Net Liability).
     */
    public function getTaxSummary(int $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        $invoiceQuery = Invoice::where('tenant_id', $tenantId)->whereNotIn('status', ['draft', 'cancelled', 'void']);
        $billQuery = VendorBill::where('tenant_id', $tenantId)->whereNotIn('status', ['draft', 'rejected', 'void']);

        if ($startDate) {
            $invoiceQuery->whereDate('created_at', '>=', $startDate);
            $billQuery->whereDate('bill_date', '>=', $startDate);
        }
        if ($endDate) {
            $invoiceQuery->whereDate('created_at', '<=', $endDate);
            $billQuery->whereDate('bill_date', '<=', $endDate);
        }

        // Output Tax (Outward Supplies from Invoices)
        $outputCgst = (float) (clone $invoiceQuery)->sum('cgst_amount');
        $outputSgst = (float) (clone $invoiceQuery)->sum('sgst_amount');
        $outputIgst = (float) (clone $invoiceQuery)->sum('igst_amount');
        $outputCess = (float) (clone $invoiceQuery)->sum('cess_amount');
        $totalOutputTax = round($outputCgst + $outputSgst + $outputIgst + $outputCess, 2);
        $totalOutwardTaxable = (float) (clone $invoiceQuery)->sum('subtotal');

        // Input Tax Credit (Inward Supplies from Vendor Bills)
        $inputTaxTotal = (float) (clone $billQuery)->sum('tax_amount');
        // Standard 50-50 allocation estimate for intra-state ITC if not explicitly split
        $inputCgst = round($inputTaxTotal * 0.45, 2);
        $inputSgst = round($inputTaxTotal * 0.45, 2);
        $inputIgst = round($inputTaxTotal * 0.10, 2);

        // Net Payable Liability = max(0, Output - Input)
        $netPayable = max(0.0, round($totalOutputTax - $inputTaxTotal, 2));

        return [
            'period' => [
                'start_date' => $startDate ?: 'All Time',
                'end_date' => $endDate ?: 'Present',
            ],
            'outward_supplies' => [
                'taxable_value' => round($totalOutwardTaxable, 2),
                'cgst' => round($outputCgst, 2),
                'sgst' => round($outputSgst, 2),
                'igst' => round($outputIgst, 2),
                'cess' => round($outputCess, 2),
                'total_output_tax' => $totalOutputTax,
                'total_invoiced_value' => round((clone $invoiceQuery)->sum('total_amount'), 2),
            ],
            'inward_supplies_itc' => [
                'total_itc_available' => round($inputTaxTotal, 2),
                'cgst_itc' => $inputCgst,
                'sgst_itc' => $inputSgst,
                'igst_itc' => $inputIgst,
                'total_bills_value' => round((clone $billQuery)->sum('total_amount'), 2),
            ],
            'net_tax_liability' => [
                'cgst_payable' => max(0.0, round($outputCgst - $inputCgst, 2)),
                'sgst_payable' => max(0.0, round($outputSgst - $inputSgst, 2)),
                'igst_payable' => max(0.0, round($outputIgst - $inputIgst, 2)),
                'total_net_payable' => $netPayable,
            ],
            'compliance_disclaimer' => 'Internal business reconciliation report for accounting and audit reference. Not an official GSTN filing receipt.',
        ];
    }

    /**
     * Generate GSTR-1 Outward Supplies Sales Register.
     */
    public function getSalesRegisterGstr1(int $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Invoice::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['draft', 'cancelled', 'void'])
            ->with('items');

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $invoices = $query->get();

        $b2b = [];
        $b2cLarge = [];
        $b2cSmall = [];
        $cdnr = []; // Credit/Debit Notes Registered
        $exports = [];

        foreach ($invoices as $inv) {
            $row = [
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->issued_at?->toDateString() ?: $inv->created_at->toDateString(),
                'buyer_name' => $inv->buyer_legal_name,
                'buyer_gstin' => $inv->buyer_gstin,
                'place_of_supply' => $inv->place_of_supply_state_code,
                'supply_type' => $inv->supply_type,
                'taxable_value' => (float) $inv->subtotal,
                'cgst' => (float) $inv->cgst_amount,
                'sgst' => (float) $inv->sgst_amount,
                'igst' => (float) $inv->igst_amount,
                'cess' => (float) $inv->cess_amount,
                'total_invoice_value' => (float) $inv->total_amount,
                'irn' => $inv->irn,
                'eway_bill_number' => $inv->eway_bill_number,
            ];

            if (in_array($inv->document_type, ['credit_note', 'debit_note'])) {
                $cdnr[] = array_merge($row, [
                    'original_invoice' => $inv->original_invoice_number,
                    'reason' => $inv->reason_for_issuance,
                ]);
            } elseif ($inv->document_type === 'export_invoice' || $inv->supply_type === 'export') {
                $exports[] = array_merge($row, [
                    'export_type' => $inv->export_type,
                    'shipping_bill' => $inv->shipping_bill_number,
                ]);
            } elseif (!empty($inv->buyer_gstin)) {
                $b2b[] = $row;
            } elseif ($inv->supply_type === 'inter_state' && $inv->total_amount > 250000) {
                $b2cLarge[] = $row;
            } else {
                $b2cSmall[] = $row;
            }
        }

        return [
            'b2b_invoices' => $b2b,
            'b2c_large' => $b2cLarge,
            'b2c_small' => $b2cSmall,
            'credit_debit_notes' => $cdnr,
            'exports' => $exports,
            'total_invoices_count' => $invoices->count(),
            'total_taxable_value' => round($invoices->sum('subtotal'), 2),
            'total_tax_value' => round($invoices->sum('tax_amount'), 2),
            'total_invoice_value' => round($invoices->sum('total_amount'), 2),
            'disclaimer' => 'Internal GSTR-1 format summary. Reconcile with official GST portal before filing.',
        ];
    }

    /**
     * Generate HSN/SAC summary report.
     */
    public function getHsnSacSummary(int $tenantId): array
    {
        $items = InvoiceItem::whereHas('invoice', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->whereNotIn('status', ['draft', 'cancelled', 'void']);
        })->get();

        $hsnMap = [];
        foreach ($items as $item) {
            $code = $item->hsn_sac_code ?: '998311';
            if (!isset($hsnMap[$code])) {
                $hsnMap[$code] = [
                    'hsn_sac' => $code,
                    'description' => $item->item_name,
                    'is_service' => $item->is_service,
                    'unit' => $item->unit ?: 'PCS',
                    'total_quantity' => 0,
                    'total_taxable' => 0.0,
                    'cgst' => 0.0,
                    'sgst' => 0.0,
                    'igst' => 0.0,
                    'cess' => 0.0,
                    'total_tax' => 0.0,
                    'total_value' => 0.0,
                ];
            }

            $hsnMap[$code]['total_quantity'] += (float) $item->quantity;
            $hsnMap[$code]['total_taxable'] += (float) $item->taxable_amount;
            $hsnMap[$code]['cgst'] += (float) $item->cgst_amount;
            $hsnMap[$code]['sgst'] += (float) $item->sgst_amount;
            $hsnMap[$code]['igst'] += (float) $item->igst_amount;
            $hsnMap[$code]['cess'] += (float) $item->cess_amount;
            $hsnMap[$code]['total_tax'] += (float) $item->tax_amount;
            $hsnMap[$code]['total_value'] += (float) $item->total_price;
        }

        return array_values($hsnMap);
    }

    /**
     * Generate CSV content for Sales Register export.
     */
    public function exportSalesRegisterCsv(int $tenantId): string
    {
        $sales = $this->getSalesRegisterGstr1($tenantId);
        $lines = [];

        $lines[] = 'GST SALES REGISTER - GSTR-1 SUMMARY';
        $lines[] = 'Notice: Internal business reconciliation export. Not an official GSTN filing.';
        $lines[] = '';
        $lines[] = 'Invoice No,Date,Document Type,Customer Name,Customer GSTIN,POS,Supply Type,Taxable Value,CGST,SGST,IGST,Total Tax,Grand Total,IRN,E-Way Bill';

        $allRows = array_merge(
            $sales['b2b_invoices'],
            $sales['b2c_large'],
            $sales['b2c_small'],
            $sales['credit_debit_notes'],
            $sales['exports']
        );

        foreach ($allRows as $r) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s","%s","%s",%.2f,%.2f,%.2f,%.2f,%.2f,%.2f,"%s","%s"',
                $r['invoice_number'],
                $r['invoice_date'],
                $r['supply_type'],
                str_replace('"', '""', $r['buyer_name']),
                $r['buyer_gstin'] ?: 'N/A',
                $r['place_of_supply'],
                $r['supply_type'],
                $r['taxable_value'],
                $r['cgst'],
                $r['sgst'],
                $r['igst'],
                $r['cgst'] + $r['sgst'] + $r['igst'],
                $r['total_invoice_value'],
                $r['irn'] ?: '',
                $r['eway_bill_number'] ?: ''
            );
        }

        return implode("\n", $lines);
    }
}
