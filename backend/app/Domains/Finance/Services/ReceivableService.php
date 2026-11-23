<?php

namespace App\Domains\Finance\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ReceivableService
{
    /**
     * Get Accounts Receivable Ageing Report with configurable buckets.
     * Standard Indian enterprise buckets: 0-30, 31-45, 46-90, 90+ days overdue.
     */
    public function getAgeingReport(int $tenantId, ?int $customerId = null): array
    {
        $today = Carbon::today();

        $query = Invoice::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['void', 'cancelled', 'paid'])
            ->where('outstanding_amount', '>', 0.001);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        $invoices = $query->with(['customer', 'collectionUser'])->get();

        $totalReceivables = 0.0;
        $totalCurrent = 0.0;
        $bucket0to30 = 0.0;
        $bucket31to45 = 0.0;
        $bucket46to90 = 0.0;
        $bucketOver90 = 0.0;
        $disputedTotal = 0.0;

        $customerBreakdown = [];
        $detailedInvoices = [];

        foreach ($invoices as $inv) {
            $due = $inv->due_date ? Carbon::parse($inv->due_date) : $inv->created_at;
            $daysOverdue = $today->diffInDays($due, false); // negative means overdue
            $outstanding = (float) $inv->outstanding_amount;
            $totalReceivables += $outstanding;

            if ($inv->is_disputed) {
                $disputedTotal += $outstanding;
            }

            $bucket = 'current';
            if ($daysOverdue >= 0) {
                // Not yet due
                $totalCurrent += $outstanding;
                $bucket = 'not_due';
            } else {
                $overdueDays = abs($daysOverdue);
                if ($overdueDays <= 30) {
                    $bucket0to30 += $outstanding;
                    $bucket = '0-30';
                } elseif ($overdueDays <= 45) {
                    $bucket31to45 += $outstanding;
                    $bucket = '31-45';
                } elseif ($overdueDays <= 90) {
                    $bucket46to90 += $outstanding;
                    $bucket = '46-90';
                } else {
                    $bucketOver90 += $outstanding;
                    $bucket = '90+';
                }
            }

            $custName = $inv->customer?->name ?? 'Unknown Customer';
            if (!isset($customerBreakdown[$custName])) {
                $customerBreakdown[$custName] = [
                    'customer_id' => $inv->customer_id,
                    'customer_name' => $custName,
                    'total_outstanding' => 0.0,
                    'current' => 0.0,
                    'bucket_0_30' => 0.0,
                    'bucket_31_45' => 0.0,
                    'bucket_46_90' => 0.0,
                    'bucket_90_plus' => 0.0,
                    'invoices_count' => 0,
                ];
            }
            $customerBreakdown[$custName]['total_outstanding'] += $outstanding;
            $customerBreakdown[$custName]['invoices_count']++;
            if ($bucket === 'not_due') {
                $customerBreakdown[$custName]['current'] += $outstanding;
            } elseif ($bucket === '0-30') {
                $customerBreakdown[$custName]['bucket_0_30'] += $outstanding;
            } elseif ($bucket === '31-45') {
                $customerBreakdown[$custName]['bucket_31_45'] += $outstanding;
            } elseif ($bucket === '46-90') {
                $customerBreakdown[$custName]['bucket_46_90'] += $outstanding;
            } elseif ($bucket === '90+') {
                $customerBreakdown[$custName]['bucket_90_plus'] += $outstanding;
            }

            $detailedInvoices[] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $custName,
                'total_amount' => (float) $inv->total_amount,
                'paid_amount' => (float) $inv->paid_amount,
                'outstanding_amount' => $outstanding,
                'due_date' => $inv->due_date?->format('Y-m-d'),
                'days_overdue' => $daysOverdue < 0 ? abs($daysOverdue) : 0,
                'bucket' => $bucket,
                'promise_to_pay_date' => $inv->promise_to_pay_date?->format('Y-m-d'),
                'recovery_status' => $inv->recovery_status,
                'is_disputed' => (bool) $inv->is_disputed,
                'dispute_reason' => $inv->dispute_reason,
            ];
        }

        return [
            'summary' => [
                'total_receivables' => round($totalReceivables, 2),
                'not_due_current' => round($totalCurrent, 2),
                'total_overdue' => round($bucket0to30 + $bucket31to45 + $bucket46to90 + $bucketOver90, 2),
                'bucket_0_30' => round($bucket0to30, 2),
                'bucket_31_45' => round($bucket31to45, 2),
                'bucket_46_90' => round($bucket46to90, 2),
                'bucket_90_plus' => round($bucketOver90, 2),
                'disputed_amount' => round($disputedTotal, 2),
                'total_invoices_count' => count($detailedInvoices),
            ],
            'customer_breakdown' => array_values($customerBreakdown),
            'invoices' => $detailedInvoices,
        ];
    }

    /**
     * Record customer promise to pay date and recovery commitment notes.
     */
    public function updatePromiseToPay(int $tenantId, int $invoiceId, string $date, ?string $notes = null): Invoice
    {
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($invoiceId);
        $invoice->update([
            'promise_to_pay_date' => Carbon::parse($date),
            'customer_commitment_notes' => $notes,
            'recovery_status' => 'promise_to_pay',
        ]);

        return $invoice->fresh(['customer', 'collectionUser']);
    }

    /**
     * Flag invoice as disputed or resolve dispute.
     */
    public function setDisputeStatus(int $tenantId, int $invoiceId, bool $isDisputed, ?string $reason = null, ?string $status = null): Invoice
    {
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($invoiceId);
        $invoice->update([
            'is_disputed' => $isDisputed,
            'dispute_reason' => $isDisputed ? $reason : null,
            'dispute_status' => $isDisputed ? ($status ?? 'under_review') : 'resolved',
            'recovery_status' => $isDisputed ? 'disputed' : 'follow_up_needed',
        ]);

        return $invoice->fresh(['customer', 'collectionUser']);
    }

    /**
     * Reassign collection officer and set recovery escalation status.
     */
    public function assignCollectionAgent(int $tenantId, int $invoiceId, ?int $userId, string $recoveryStatus = 'follow_up_needed'): Invoice
    {
        $invoice = Invoice::where('tenant_id', $tenantId)->findOrFail($invoiceId);
        $invoice->update([
            'collection_user_id' => $userId,
            'recovery_status' => $recoveryStatus,
        ]);

        return $invoice->fresh(['customer', 'collectionUser']);
    }
}
