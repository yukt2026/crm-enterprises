<?php

namespace App\Domains\Finance\Services;

use App\Domains\AuditSecurity\Services\AuditLogger;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Finance\Models\QuotationItem;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        protected ?InvoiceBillingService $invoiceBillingService = null
    ) {}

    /**
     * List quotations with tenant isolation and filtering
     */
    public function listQuotations(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Quotation::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer', 'deal', 'contact', 'company', 'items.product'])
            ->orderBy('id', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
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
                $q->where('quote_number', 'like', $search)
                  ->orWhere('notes', 'like', $search);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Calculate line items and summary totals
     */
    public function calculateTotals(
        array $items,
        float $discountRate = 0.0,
        string $discountType = 'percentage',
        float $discountAmountInput = 0.0,
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
                'item_name' => $item['item_name'] ?? ($item['name'] ?? 'Custom Item'),
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

        if ($isTaxInclusive) {
            $totalAmount = round(array_sum(array_column($calculatedItems, 'total_amount')) - $docDiscount, 2);
        } else {
            $totalAmount = round($subtotal + $taxTotal - $docDiscount, 2);
        }

        return [
            'items' => $calculatedItems,
            'subtotal' => $subtotal,
            'tax_amount' => $taxTotal,
            'discount_amount' => $docDiscount,
            'total_amount' => max(0, $totalAmount),
        ];
    }

    /**
     * Generate standard sequential quotation number
     */
    public function generateQuotationNumber(int $tenantId): string
    {
        $prefix = 'QT-' . date('Ym') . '-';
        $count = Quotation::where('tenant_id', $tenantId)
            ->where('quote_number', 'like', $prefix . '%')
            ->count();

        return sprintf('%s%04d', $prefix, $count + 1);
    }

    /**
     * Create quotation
     */
    public function createQuotation(int $tenantId, array $data, ?User $user = null): Quotation
    {
        return DB::transaction(function () use ($tenantId, $data, $user) {
            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages(['items' => 'At least one line item is required.']);
            }

            $discountRate = (float) ($data['discount_rate'] ?? 0);
            $discountType = $data['discount_type'] ?? 'percentage';
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? false);

            $calculated = $this->calculateTotals($itemsData, $discountRate, $discountType, $discountAmount, $isTaxInclusive);

            $quotationNumber = $data['quotation_number'] ?? $this->generateQuotationNumber($tenantId);

            $quotation = Quotation::create([
                'tenant_id' => $tenantId,
                'customer_id' => $data['customer_id'] ?? null,
                'lead_id' => $data['lead_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'parent_quotation_id' => $data['parent_quotation_id'] ?? null,
                'version' => $data['version'] ?? 1,
                'quote_number' => $quotationNumber,
                'quotation_number' => $quotationNumber,
                'status' => $data['status'] ?? 'draft',
                'currency' => $data['currency'] ?? 'INR',
                'subtotal' => $calculated['subtotal'],
                'discount_type' => $discountType,
                'discount_rate' => $discountRate,
                'discount_amount' => $calculated['discount_amount'],
                'tax_amount' => $calculated['tax_amount'],
                'total_amount' => $calculated['total_amount'],
                'is_tax_inclusive' => $isTaxInclusive,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'notes' => $data['notes'] ?? null,
                'valid_until' => $data['valid_until'] ?? Carbon::now()->addDays(30)->toDateString(),
                'created_by' => $user?->id ?? ($data['created_by'] ?? null),
            ]);

            foreach ($calculated['items'] as $item) {
                QuotationItem::create(array_merge($item, [
                    'tenant_id' => $tenantId,
                    'quotation_id' => $quotation->id,
                ]));
            }

            AuditLogger::log('quotation_created', $quotation, null, $quotation->toArray(), $user);

            return $quotation->load(['items.product', 'customer', 'deal', 'contact', 'company']);
        });
    }

    /**
     * Create revision branching from existing quotation
     */
    public function createRevision(int $tenantId, int $quotationId, array $changes = [], ?User $user = null): Quotation
    {
        return DB::transaction(function () use ($tenantId, $quotationId, $changes, $user) {
            $parent = Quotation::where('tenant_id', $tenantId)->with('items')->findOrFail($quotationId);

            $rootId = $parent->parent_quotation_id ?? $parent->id;
            $latestVersion = Quotation::where('tenant_id', $tenantId)
                ->where(function ($q) use ($rootId) {
                    $q->where('id', $rootId)->orWhere('parent_quotation_id', $rootId);
                })
                ->max('version') ?? 1;

            $newVersion = $latestVersion + 1;
            $newNumber = $parent->quotation_number . '-R' . $newVersion;

            // Merge parent data with changes
            $itemsData = $changes['items'] ?? $parent->items->map(fn($it) => [
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

            $mergedData = array_merge([
                'customer_id' => $parent->customer_id,
                'lead_id' => $parent->lead_id,
                'contact_id' => $parent->contact_id,
                'company_id' => $parent->company_id,
                'deal_id' => $parent->deal_id,
                'currency' => $parent->currency,
                'discount_type' => $parent->discount_type,
                'discount_rate' => $parent->discount_rate,
                'discount_amount' => $parent->discount_amount,
                'is_tax_inclusive' => $parent->is_tax_inclusive,
                'terms_conditions' => $parent->terms_conditions,
                'notes' => $parent->notes,
                'valid_until' => $parent->valid_until,
            ], $changes, [
                'quote_number' => $newNumber,
                'quotation_number' => $newNumber,
                'parent_quotation_id' => $rootId,
                'version' => $newVersion,
                'status' => 'draft',
                'items' => $itemsData,
            ]);

            return $this->createQuotation($tenantId, $mergedData, $user);
        });
    }

    /**
     * Mark quotation status
     */
    public function markStatus(Quotation $quotation, string $status, ?User $user = null): Quotation
    {
        $oldStatus = $quotation->status;
        $quotation->status = $status;

        if ($status === 'accepted') {
            $quotation->accepted_at = Carbon::now();
        } elseif ($status === 'rejected') {
            $quotation->rejected_at = Carbon::now();
        } elseif ($status === 'viewed' && !$quotation->viewed_at) {
            $quotation->viewed_at = Carbon::now();
        }

        $quotation->save();

        AuditLogger::log('quotation_status_updated', $quotation, ['status' => $oldStatus], ['status' => $status], $user);

        return $quotation;
    }

    /**
     * Submit quotation for manager approval.
     */
    public function submitForApproval(Quotation $quotation, User $user, ?string $comments = null): Quotation
    {
        $quotation->update([
            'approval_status' => 'pending_approval',
            'rejection_reason' => null,
        ]);

        AuditLogger::log('quotation_submitted_approval', $quotation, ['approval_status' => 'not_submitted'], ['approval_status' => 'pending_approval', 'comments' => $comments], $user);

        return $quotation->fresh();
    }

    /**
     * Approve quotation.
     */
    public function approve(Quotation $quotation, User $approver, ?string $comments = null): Quotation
    {
        $quotation->update([
            'approval_status' => 'approved',
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        AuditLogger::log('quotation_approved', $quotation, ['approval_status' => 'pending_approval'], ['approval_status' => 'approved', 'comments' => $comments], $approver);

        return $quotation->fresh();
    }

    /**
     * Reject quotation.
     */
    public function reject(Quotation $quotation, User $approver, string $reason): Quotation
    {
        $quotation->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        AuditLogger::log('quotation_rejected', $quotation, ['approval_status' => 'pending_approval'], ['approval_status' => 'rejected', 'reason' => $reason], $approver);

        return $quotation->fresh();
    }

    /**
     * Check discount policy and record approval request if required.
     */
    public function checkDiscountApproval(Quotation $quotation, float $discountRate, User $user, ?string $reason = null): array
    {
        $tenantId = $quotation->tenant_id;
        $roleName = $user->role ?? 'Sales Executive';

        $rule = \App\Domains\Finance\Models\DiscountRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('role_name', $roleName)
            ->first();

        $maxSelfApproval = $rule ? (float) $rule->max_discount_percentage : 10.00; // default 10%

        $requiresApproval = $discountRate > $maxSelfApproval;
        $approvalRecord = null;

        if ($requiresApproval) {
            $approvalRecord = \App\Domains\Finance\Models\DiscountApproval::create([
                'tenant_id' => $tenantId,
                'quotation_id' => $quotation->id,
                'deal_id' => $quotation->deal_id,
                'requested_by_user_id' => $user->id,
                'discount_rate' => $discountRate,
                'discount_amount' => (float) $quotation->discount_amount,
                'reason' => $reason ?: "Discount of {$discountRate}% requested (exceeds {$maxSelfApproval}% limit).",
                'status' => 'pending',
            ]);

            $quotation->update(['approval_status' => 'pending_approval']);
        }

        return [
            'requires_approval' => $requiresApproval,
            'max_allowed_without_approval' => $maxSelfApproval,
            'approval' => $approvalRecord,
        ];
    }

    /**
     * Record customer acceptance or rejection with audit details.
     */
    public function recordCustomerAction(Quotation $quotation, string $action, string $ipAddress, ?string $notes = null): Quotation
    {
        if ($action === 'accept') {
            $quotation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'customer_action_ip' => $ipAddress,
                'customer_action_notes' => $notes ?: 'Customer approved quotation digitally.',
            ]);
        } elseif ($action === 'reject') {
            $quotation->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'customer_action_ip' => $ipAddress,
                'customer_action_notes' => $notes ?: 'Customer declined quotation.',
            ]);
        }

        return $quotation->fresh();
    }

    /**
     * Convert accepted quotation into an Invoice with duplicate prevention.
     */
    public function convertToInvoice(int $tenantId, int $quotationId, ?User $user = null): Invoice
    {
        $quotation = Quotation::where('tenant_id', $tenantId)->with('items')->findOrFail($quotationId);

        // Guard against duplicate invoice generation
        if ($quotation->converted_to_invoice_id) {
            $existingInvoice = Invoice::where('tenant_id', $tenantId)->find($quotation->converted_to_invoice_id);
            if ($existingInvoice) {
                return $existingInvoice;
            }
        }

        $this->markStatus($quotation, 'accepted', $user);

        if (!$this->invoiceBillingService) {
            $this->invoiceBillingService = app(InvoiceBillingService::class);
        }

        $invoice = $this->invoiceBillingService->createFromQuotation($quotation, $user);

        // Record conversion reference
        $quotation->update([
            'converted_to_invoice_id' => $invoice->id,
            'converted_to_invoice_at' => now(),
        ]);

        return $invoice;
    }
}
