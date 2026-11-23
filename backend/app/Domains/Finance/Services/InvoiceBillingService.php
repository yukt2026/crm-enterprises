<?php

namespace App\Domains\Finance\Services;

use App\Domains\AuditSecurity\Services\AuditLogger;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceItem;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Finance\Models\Refund;
use App\Domains\Inventory\Services\InventoryService;
use App\Domains\Sales\Models\Product;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceBillingService
{
    public function __construct(
        protected ?InventoryService $inventoryService = null
    ) {}

    /**
     * List invoices with tenant isolation and filter scopes
     */
    public function listInvoices(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer', 'deal', 'contact', 'company', 'quotation', 'payments', 'items.product'])
            ->orderBy('id', 'desc');

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'overdue') {
                $query->whereIn('status', ['issued', 'partially_paid'])
                      ->where('due_date', '<', Carbon::today()->toDateString());
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['deal_id'])) {
            $query->where('deal_id', $filters['deal_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', $search)
                  ->orWhere('billing_name', 'like', $search)
                  ->orWhere('billing_email', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Calculate line items and summary totals for invoice
     */
    public function calculateTotals(
        array $items,
        float $discountRate = 0.0,
        string $discountType = 'percentage',
        float $discountAmountInput = 0.0,
        float $shippingAmount = 0.0,
        bool $isTaxInclusive = false
    ): array {
        $calculatedItems = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $item) {
            $qty = max(0.01, (float) ($item['quantity'] ?? 1));
            $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
            $gross = round($qty * $unitPrice, 2);

            $itemDiscType = $item['discount_type'] ?? 'percentage';
            $itemDiscRate = isset($item['discount_rate']) ? (float) $item['discount_rate'] : 0.0;
            $itemDiscAmt = 0.0;

            if ($itemDiscType === 'percentage' && $itemDiscRate > 0) {
                $itemDiscAmt = round($gross * ($itemDiscRate / 100), 2);
            } elseif ($itemDiscType === 'fixed' && !empty($item['discount_amount'])) {
                $itemDiscAmt = round(min($gross, (float) $item['discount_amount']), 2);
            }

            $netLine = max(0, $gross - $itemDiscAmt);
            $taxRate = isset($item['tax_rate']) ? (float) $item['tax_rate'] : 0.0;

            if ($isTaxInclusive) {
                $itemBaseSubtotal = $taxRate > 0 ? round($netLine / (1 + ($taxRate / 100)), 2) : $netLine;
                $itemTax = round($netLine - $itemBaseSubtotal, 2);
                $itemTotal = $netLine;
            } else {
                $itemBaseSubtotal = $netLine;
                $itemTax = round($netLine * ($taxRate / 100), 2);
                $itemTotal = round($itemBaseSubtotal + $itemTax, 2);
            }

            $subtotal += $itemBaseSubtotal;
            $taxTotal += $itemTax;

            $calculatedItems[] = [
                'product_id' => $item['product_id'] ?? null,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'item_name' => $item['item_name'] ?? ($item['name'] ?? 'Item'),
                'description' => $item['description'] ?? null,
                'hsn_sac_code' => $item['hsn_sac_code'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_type' => $itemDiscType,
                'discount_rate' => $itemDiscRate,
                'discount_amount' => $itemDiscAmt,
                'tax_rate' => $taxRate,
                'tax_amount' => $itemTax,
                'total_amount' => $itemTotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $taxTotal = round($taxTotal, 2);

        $docDiscount = 0.0;
        if ($discountType === 'percentage' && $discountRate > 0) {
            $docDiscount = round($subtotal * ($discountRate / 100), 2);
        } elseif ($discountType === 'fixed' && $discountAmountInput > 0) {
            $docDiscount = round(min($subtotal, $discountAmountInput), 2);
        }

        $shipping = max(0, round($shippingAmount, 2));

        if ($isTaxInclusive) {
            $totalAmount = round(array_sum(array_column($calculatedItems, 'total_amount')) - $docDiscount + $shipping, 2);
        } else {
            $totalAmount = round($subtotal + $taxTotal - $docDiscount + $shipping, 2);
        }

        return [
            'items' => $calculatedItems,
            'subtotal' => $subtotal,
            'tax_amount' => $taxTotal,
            'discount_amount' => $docDiscount,
            'shipping_amount' => $shipping,
            'total_amount' => max(0, $totalAmount),
        ];
    }

    /**
     * Generate standard sequential invoice number
     */
    public function generateInvoiceNumber(int $tenantId): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $count = Invoice::where('tenant_id', $tenantId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->count();

        return sprintf('%s%04d', $prefix, $count + 1);
    }

    /**
     * Generate receipt number
     */
    public function generateReceiptNumber(int $tenantId): string
    {
        $prefix = 'RCT-' . date('Ym') . '-';
        $count = Payment::where('tenant_id', $tenantId)
            ->where('receipt_number', 'like', $prefix . '%')
            ->count();

        return sprintf('%s%04d', $prefix, $count + 1);
    }

    /**
     * Generate refund number
     */
    public function generateRefundNumber(int $tenantId): string
    {
        $prefix = 'REF-' . date('Ym') . '-';
        $count = Refund::where('tenant_id', $tenantId)
            ->where('refund_number', 'like', $prefix . '%')
            ->count();

        return sprintf('%s%04d', $prefix, $count + 1);
    }

    /**
     * Create Invoice
     */
    public function createInvoice(int $tenantId, array $data, ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($tenantId, $data, $user) {
            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages(['items' => 'At least one line item is required.']);
            }

            $discountRate = (float) ($data['discount_rate'] ?? 0);
            $discountType = $data['discount_type'] ?? 'percentage';
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $shippingAmount = (float) ($data['shipping_amount'] ?? 0);
            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? false);

            $calculated = $this->calculateTotals($itemsData, $discountRate, $discountType, $discountAmount, $shippingAmount, $isTaxInclusive);

            $invoiceNumber = $data['invoice_number'] ?? $this->generateInvoiceNumber($tenantId);
            $initialStatus = $data['status'] ?? 'draft';

            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'] ?? null,
                'quotation_id' => $data['quotation_id'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'status' => $initialStatus,
                'currency' => $data['currency'] ?? 'INR',
                'subtotal' => $calculated['subtotal'],
                'discount_type' => $discountType,
                'discount_rate' => $discountRate,
                'discount_amount' => $calculated['discount_amount'],
                'tax_amount' => $calculated['tax_amount'],
                'shipping_amount' => $calculated['shipping_amount'],
                'total_amount' => $calculated['total_amount'],
                'paid_amount' => 0.0,
                'outstanding_amount' => $calculated['total_amount'],
                'is_tax_inclusive' => $isTaxInclusive,
                'payment_terms' => $data['payment_terms'] ?? 'Due on receipt',
                'billing_name' => $data['billing_name'] ?? null,
                'billing_email' => $data['billing_email'] ?? null,
                'billing_phone' => $data['billing_phone'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'billing_tax_id' => $data['billing_tax_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'issued_at' => ($initialStatus !== 'draft') ? Carbon::now() : null,
                'due_date' => $data['due_date'] ?? Carbon::now()->addDays(15)->toDateString(),
            ]);

            foreach ($calculated['items'] as $item) {
                InvoiceItem::create(array_merge($item, [
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice->id,
                ]));
            }

            // Deduct inventory if initially issued
            if ($initialStatus === 'issued') {
                $this->deductInventoryForInvoice($invoice, $user);
            }

            AuditLogger::log('invoice_created', $invoice, null, $invoice->toArray(), $user);

            return $invoice->load(['items.product', 'customer', 'deal', 'contact', 'company']);
        });
    }

    /**
     * Create invoice from quotation
     */
    public function createFromQuotation(Quotation $quotation, ?User $user = null): Invoice
    {
        $itemsData = $quotation->items->map(fn($it) => [
            'product_id' => $it->product_id,
            'product_variant_id' => $it->product_variant_id,
            'item_name' => $it->item_name,
            'description' => $it->description,
            'hsn_sac_code' => $it->hsn_sac_code,
            'quantity' => $it->quantity,
            'unit_price' => $it->unit_price,
            'discount_type' => $it->discount_type,
            'discount_rate' => $it->discount_rate,
            'discount_amount' => $it->discount_amount,
            'tax_rate' => $it->tax_rate,
        ])->toArray();

        // Customer details
        $customer = $quotation->customer;
        $contact = $quotation->contact;
        $billingName = $customer?->name ?? ($contact ? ($contact->first_name . ' ' . $contact->last_name) : 'Customer');
        $billingEmail = $customer?->email ?? $contact?->email;
        $billingPhone = $customer?->phone ?? $contact?->phone;
        $billingAddress = $customer?->address ?? ($customer?->city ? "{$customer->city}, {$customer->state}" : null);

        $data = [
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'deal_id' => $quotation->deal_id,
            'contact_id' => $quotation->contact_id,
            'company_id' => $quotation->company_id,
            'currency' => $quotation->currency,
            'discount_type' => $quotation->discount_type,
            'discount_rate' => $quotation->discount_rate,
            'discount_amount' => $quotation->discount_amount,
            'is_tax_inclusive' => $quotation->is_tax_inclusive,
            'billing_name' => $billingName,
            'billing_email' => $billingEmail,
            'billing_phone' => $billingPhone,
            'billing_address' => $billingAddress,
            'payment_terms' => $quotation->terms_conditions ?? 'Due in 15 days',
            'notes' => $quotation->notes,
            'status' => 'draft',
            'items' => $itemsData,
        ];

        return $this->createInvoice($quotation->tenant_id, $data, $user);
    }

    /**
     * Issue invoice and trigger inventory deduction for stock items
     */
    public function issueInvoice(Invoice $invoice, ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $user) {
            if ($invoice->status !== 'draft') {
                return $invoice;
            }

            $invoice->status = 'issued';
            $invoice->issued_at = Carbon::now();
            $invoice->save();

            $this->deductInventoryForInvoice($invoice, $user);

            AuditLogger::log('invoice_issued', $invoice, ['status' => 'draft'], ['status' => 'issued'], $user);

            return $invoice;
        });
    }

    /**
     * Deduct stock for invoice items that track inventory
     */
    protected function deductInventoryForInvoice(Invoice $invoice, ?User $user = null): void
    {
        if (!$this->inventoryService) {
            $this->inventoryService = app(InventoryService::class);
        }

        $warehouse = $this->inventoryService->ensureDefaultWarehouse($invoice->tenant_id);

        foreach ($invoice->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            $product = Product::find($item->product_id);
            if ($product && $product->track_inventory && $product->type !== 'service') {
                try {
                    $this->inventoryService->recordStockMovement([
                        'tenant_id' => $invoice->tenant_id,
                        'product_id' => $product->id,
                        'product_variant_id' => $item->product_variant_id,
                        'warehouse_id' => $warehouse->id,
                        'movement_type' => 'sale_deduction',
                        'quantity' => (float) $item->quantity,
                        'unit_cost' => (float) ($product->cost_price ?? 0),
                        'reference_type' => 'invoice',
                        'reference_id' => $invoice->id,
                        'notes' => "Invoice #{$invoice->invoice_number} stock deduction",
                    ], $user);
                } catch (\Throwable $e) {
                    // Log or handle stock deduction exception without crashing if warehouse not configured
                    report($e);
                }
            }
        }
    }

    /**
     * Void invoice
     */
    public function voidInvoice(Invoice $invoice, string $reason = '', ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $user) {
            $oldStatus = $invoice->status;
            $invoice->status = 'void';
            $invoice->voided_at = Carbon::now();
            if ($reason) {
                $invoice->notes = ($invoice->notes ? $invoice->notes . "\n" : '') . "Voided Reason: " . $reason;
            }
            $invoice->save();

            AuditLogger::log('invoice_voided', $invoice, ['status' => $oldStatus], ['status' => 'void'], $user);

            return $invoice;
        });
    }

    /**
     * Record payment against an invoice
     */
    public function recordPayment(int $tenantId, int $invoiceId, array $paymentData, ?User $user = null): Payment
    {
        return DB::transaction(function () use ($tenantId, $invoiceId, $paymentData, $user) {
            $invoice = Invoice::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($invoiceId);

            if ($invoice->status === 'void' || $invoice->status === 'cancelled') {
                throw ValidationException::withMessages(['invoice' => 'Cannot record payment against a voided or cancelled invoice.']);
            }

            if ($invoice->status === 'paid' || $invoice->outstanding_amount <= 0.001) {
                throw ValidationException::withMessages(['invoice' => 'Cannot record payment against an already settled invoice.']);
            }

            $amount = round((float) ($paymentData['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            if ($amount > round($invoice->outstanding_amount + 0.001, 2)) {
                throw ValidationException::withMessages(['amount' => 'Payment amount exceeds outstanding invoice balance.']);
            }

            $receiptNumber = $paymentData['receipt_number'] ?? $this->generateReceiptNumber($tenantId);

            $payment = Payment::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'receipt_number' => $receiptNumber,
                'amount' => $amount,
                'currency' => $paymentData['currency'] ?? $invoice->currency,
                'payment_date' => $paymentData['payment_date'] ?? Carbon::now()->toDateString(),
                'payment_method' => $paymentData['payment_method'] ?? 'bank_transfer',
                'payment_channel' => $paymentData['payment_channel'] ?? 'manual',
                'reference_number' => $paymentData['reference_number'] ?? null,
                'gateway_order_id' => $paymentData['gateway_order_id'] ?? null,
                'gateway_payment_id' => $paymentData['gateway_payment_id'] ?? null,
                'gateway_signature' => $paymentData['gateway_signature'] ?? null,
                'refunded_amount' => 0.0,
                'status' => 'successful',
                'user_id' => $user?->id ?? ($paymentData['user_id'] ?? null),
                'notes' => $paymentData['notes'] ?? null,
            ]);

            // Update Invoice paid and outstanding totals
            $invoice->paid_amount = round($invoice->paid_amount + $amount, 2);
            $invoice->outstanding_amount = max(0, round($invoice->total_amount - $invoice->paid_amount, 2));

            if ($invoice->outstanding_amount <= 0.001) {
                $invoice->status = 'paid';
                $invoice->paid_at = Carbon::now();
            } else {
                $invoice->status = 'partially_paid';
            }

            if (!$invoice->issued_at) {
                $invoice->issued_at = Carbon::now();
            }

            $invoice->save();

            AuditLogger::log('payment_recorded', $payment, null, $payment->toArray(), $user);

            return $payment->load(['invoice', 'customer', 'user']);
        });
    }

    /**
     * Process refund against a payment
     */
    public function processRefund(int $tenantId, int $paymentId, float $amount, string $reason, ?User $user = null): Refund
    {
        return DB::transaction(function () use ($tenantId, $paymentId, $amount, $reason, $user) {
            $payment = Payment::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($paymentId);
            $invoice = Invoice::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($payment->invoice_id);

            $amount = round($amount, 2);
            $availableToRefund = round($payment->amount - $payment->refunded_amount, 2);

            if ($amount <= 0 || $amount > $availableToRefund) {
                throw ValidationException::withMessages([
                    'amount' => "Invalid refund amount. Maximum refundable for this transaction is {$availableToRefund}.",
                ]);
            }

            $refundNumber = $this->generateRefundNumber($tenantId);

            $refund = Refund::create([
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'refund_number' => $refundNumber,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => 'processed',
                'user_id' => $user?->id,
                'notes' => "Refunded against receipt {$payment->receipt_number}",
            ]);

            // Update payment refunded amount
            $payment->refunded_amount = round($payment->refunded_amount + $amount, 2);
            $payment->save();

            // Reverse amount on invoice
            $invoice->paid_amount = max(0, round($invoice->paid_amount - $amount, 2));
            $invoice->outstanding_amount = round($invoice->total_amount - $invoice->paid_amount, 2);

            if ($invoice->paid_amount > 0) {
                $invoice->status = 'partially_paid';
            } else {
                $invoice->status = 'issued';
            }

            $invoice->paid_at = null;
            $invoice->save();

            AuditLogger::log('payment_refunded', $refund, null, $refund->toArray(), $user);

            return $refund->load(['invoice', 'payment']);
        });
    }

    /**
     * Comprehensive finance KPIs and receivables overview
     */
    public function getFinanceSummary(int $tenantId): array
    {
        $today = Carbon::today()->toDateString();

        $activeInvoices = Invoice::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['void', 'cancelled']);

        $totalInvoiced = (float) $activeInvoices->sum('total_amount');
        $totalPaid = (float) $activeInvoices->sum('paid_amount');
        $totalReceivables = (float) $activeInvoices->sum('outstanding_amount');

        $overdueQuery = Invoice::where('tenant_id', $tenantId)
            ->whereIn('status', ['issued', 'partially_paid'])
            ->where('due_date', '<', $today);

        $overdueAmount = (float) $overdueQuery->sum('outstanding_amount');
        $overdueCount = $overdueQuery->count();

        $recentPayments = Payment::where('tenant_id', $tenantId)
            ->with(['invoice', 'customer'])
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $countsByStatus = Invoice::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as count, sum(total_amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'total_invoiced' => round($totalInvoiced, 2),
            'total_collected' => round($totalPaid, 2),
            'total_receivables' => round($totalReceivables, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'overdue_count' => $overdueCount,
            'status_breakdown' => $countsByStatus,
            'recent_payments' => $recentPayments,
        ];
    }

    /**
     * Generate printable / viewable HTML for an Invoice or Quotation
     */
    public function generateDocumentHtml(Invoice|Quotation $doc): string
    {
        $isInvoice = ($doc instanceof Invoice);
        $title = $isInvoice ? "TAX INVOICE" : "QUOTATION";
        $docNumber = $isInvoice ? $doc->invoice_number : $doc->quotation_number;
        $docDate = $isInvoice ? ($doc->issued_at?->format('d M Y') ?? date('d M Y')) : ($doc->created_at?->format('d M Y') ?? date('d M Y'));
        $dueDate = $isInvoice ? ($doc->due_date?->format('d M Y') ?? '-') : ($doc->valid_until?->format('d M Y') ?? '-');
        $items = $doc->items ?? [];

        $customerName = $doc->billing_name ?? ($doc->customer?->name ?? 'Valued Customer');
        $customerEmail = $doc->billing_email ?? ($doc->customer?->email ?? '-');
        $customerPhone = $doc->billing_phone ?? ($doc->customer?->phone ?? '-');
        $customerAddress = $doc->billing_address ?? ($doc->customer?->address ?? '-');

        $itemRows = '';
        foreach ($items as $idx => $item) {
            $num = $idx + 1;
            $name = htmlspecialchars($item->item_name);
            $qty = $item->quantity;
            $rate = number_format($item->unit_price, 2);
            $tax = number_format($item->tax_amount, 2) . " (" . $item->tax_rate . "%)";
            $disc = number_format($item->discount_amount, 2);
            $tot = number_format($item->total_amount, 2);

            $itemRows .= "<tr>
                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: center;'>{$num}</td>
                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0;'><strong>{$name}</strong></td>
                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: center;'>{$qty}</td>
                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right;'>{$rate}</td>
                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right;'>{$tax}</td>
                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right;'>{$disc}</td>
                <td style='padding: 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: bold;'>{$tot}</td>
            </tr>";
        }

        $currency = htmlspecialchars($doc->currency ?? 'INR');
        $subtotal = number_format($doc->subtotal, 2);
        $taxTotal = number_format($doc->tax_amount, 2);
        $discount = number_format($doc->discount_amount, 2);
        $grandTotal = number_format($doc->total_amount, 2);

        $extraRows = '';
        if ($isInvoice) {
            $paid = number_format($doc->paid_amount, 2);
            $balance = number_format($doc->outstanding_amount, 2);
            $shipping = number_format($doc->shipping_amount, 2);

            $extraRows = "
            <tr>
                <td colspan='6' style='padding: 6px; text-align: right; color: #4a5568;'>Shipping:</td>
                <td style='padding: 6px; text-align: right;'>{$currency} {$shipping}</td>
            </tr>
            <tr style='background: #f7fafc; font-weight: bold;'>
                <td colspan='6' style='padding: 6px; text-align: right; color: #2b6cb0;'>Amount Paid:</td>
                <td style='padding: 6px; text-align: right; color: #2b6cb0;'>{$currency} {$paid}</td>
            </tr>
            <tr style='background: #fff5f5; font-weight: bold; font-size: 1.1em;'>
                <td colspan='6' style='padding: 8px; text-align: right; color: #c53030;'>Outstanding Balance:</td>
                <td style='padding: 8px; text-align: right; color: #c53030;'>{$currency} {$balance}</td>
            </tr>";
        }

        return "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>{$title} - {$docNumber}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #2d3748; margin: 0; padding: 24px; background: #fff; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #3182ce; padding-bottom: 16px; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: bold; color: #2b6cb0; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase; background: #ebf8ff; color: #2b6cb0; }
        .grid { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .box { width: 48%; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px; }
        th { background: #f7fafc; padding: 10px 8px; border-bottom: 2px solid #cbd5e0; color: #4a5568; }
        .notes { margin-top: 24px; padding: 12px; background: #f7fafc; border-radius: 6px; font-size: 13px; color: #718096; }
    </style>
</head>
<body>
    <div class='header'>
        <div>
            <div class='logo'>ENTERPRISE CRM</div>
            <div style='color: #718096; font-size: 13px; margin-top: 4px;'>Integrated Business Platform</div>
        </div>
        <div style='text-align: right;'>
            <h1 style='margin: 0; font-size: 22px; color: #2d3748;'>{$title}</h1>
            <div style='font-size: 14px; font-weight: bold; color: #4a5568;'>#{$docNumber}</div>
            <div class='badge' style='margin-top: 6px;'>Status: {$doc->status}</div>
        </div>
    </div>

    <div class='grid'>
        <div class='box'>
            <h4 style='margin: 0 0 6px 0; color: #4a5568; text-transform: uppercase; font-size: 12px;'>Billed To:</h4>
            <div style='font-weight: bold; font-size: 15px;'>{$customerName}</div>
            <div style='font-size: 13px; color: #4a5568;'>{$customerEmail} | {$customerPhone}</div>
            <div style='font-size: 13px; color: #4a5568;'>{$customerAddress}</div>
        </div>
        <div class='box' style='text-align: right;'>
            <div style='font-size: 13px; color: #718096;'>Date: <strong>{$docDate}</strong></div>
            <div style='font-size: 13px; color: #718096;'>Due / Expiry: <strong>{$dueDate}</strong></div>
            <div style='font-size: 13px; color: #718096;'>Currency: <strong>{$currency}</strong></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style='width: 5%;'>#</th>
                <th style='text-align: left;'>Item Description</th>
                <th style='width: 10%; text-align: center;'>Qty</th>
                <th style='width: 15%; text-align: right;'>Rate</th>
                <th style='width: 15%; text-align: right;'>Tax</th>
                <th style='width: 12%; text-align: right;'>Discount</th>
                <th style='width: 15%; text-align: right;'>Amount</th>
            </tr>
        </thead>
        <tbody>
            {$itemRows}
        </tbody>
        <tfoot>
            <tr>
                <td colspan='6' style='padding: 6px; text-align: right; color: #4a5568;'>Subtotal:</td>
                <td style='padding: 6px; text-align: right;'>{$currency} {$subtotal}</td>
            </tr>
            <tr>
                <td colspan='6' style='padding: 6px; text-align: right; color: #4a5568;'>Total Tax:</td>
                <td style='padding: 6px; text-align: right;'>{$currency} {$taxTotal}</td>
            </tr>
            <tr>
                <td colspan='6' style='padding: 6px; text-align: right; color: #4a5568;'>Discount:</td>
                <td style='padding: 6px; text-align: right;'>- {$currency} {$discount}</td>
            </tr>
            {$extraRows}
            <tr style='background: #edf2f7; font-weight: bold; font-size: 1.1em;'>
                <td colspan='6' style='padding: 10px 8px; text-align: right;'>Grand Total:</td>
                <td style='padding: 10px 8px; text-align: right;'>{$currency} {$grandTotal}</td>
            </tr>
        </tfoot>
    </table>

    <div class='notes'>
        <strong>Terms & Conditions:</strong>
        <p style='margin: 4px 0 0 0;'>Payment due according to specified terms. Computer-generated document, no signature required.</p>
    </div>
</body>
</html>";
    }
}
