<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\FinanceTransaction;
use App\Domains\Finance\Models\VendorBill;
use App\Domains\Finance\Models\VendorPayment;
use App\Domains\Inventory\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PayableService
{
    /**
     * Create vendor bill.
     */
    public function createBill(int $tenantId, array $data, ?User $user = null): VendorBill
    {
        $supplier = null;
        if (!empty($data['supplier_id'])) {
            $supplier = Supplier::where('tenant_id', $tenantId)->find($data['supplier_id']);
        }
        if (!$supplier) {
            $supplier = Supplier::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => 'Default Enterprise Supplier'],
                ['status' => 'active', 'currency' => $data['currency'] ?? 'INR']
            );
        }
        $totalAmount = round((float) ($data['total_amount'] ?? 0), 2);
        $subtotal = round((float) ($data['subtotal'] ?? $totalAmount), 2);
        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);

        $billNo = $data['bill_number'] ?? ('BILL-' . date('Ym') . '-' . strtoupper(Str::random(6)));

        return VendorBill::create([
            'tenant_id' => $tenantId,
            'bill_number' => $billNo,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'bill_date' => $data['bill_date'] ?? Carbon::now()->toDateString(),
            'due_date' => $data['due_date'] ?? Carbon::now()->addDays(30)->toDateString(),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => 0.0,
            'outstanding_amount' => $totalAmount,
            'currency' => $data['currency'] ?? 'INR',
            'status' => $data['status'] ?? 'pending_approval',
            'notes' => $data['notes'] ?? null,
            'file_attachment' => $data['file_attachment'] ?? null,
        ]);
    }

    /**
     * Approve vendor bill for disbursement.
     */
    public function approveBill(int $tenantId, int $billId, ?User $approver = null): VendorBill
    {
        $bill = VendorBill::where('tenant_id', $tenantId)->findOrFail($billId);
        $bill->update([
            'status' => 'approved',
            'approved_by_user_id' => $approver?->id,
            'approved_at' => Carbon::now(),
        ]);

        return $bill->fresh(['supplier', 'approvedByUser']);
    }

    /**
     * Record outgoing payment to vendor against bill.
     */
    public function recordPayment(int $tenantId, array $data, ?User $user = null): VendorPayment
    {
        return DB::transaction(function () use ($tenantId, $data, $user) {
            $bill = null;
            if (!empty($data['vendor_bill_id'])) {
                $bill = VendorBill::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($data['vendor_bill_id']);
            }

            $supplierId = $data['supplier_id'] ?? $bill?->supplier_id;
            if (!$supplierId || !Supplier::where('tenant_id', $tenantId)->find($supplierId)) {
                $supplier = Supplier::firstOrCreate(
                    ['tenant_id' => $tenantId, 'name' => 'Default Enterprise Supplier'],
                    ['status' => 'active', 'currency' => 'INR']
                );
                $supplierId = $supplier->id;
            }

            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            if ($bill && $amount > round($bill->outstanding_amount + 0.001, 2)) {
                throw ValidationException::withMessages([
                    'amount' => "Payment amount ({$amount}) exceeds bill outstanding balance ({$bill->outstanding_amount}).",
                ]);
            }

            $paymentNo = 'VPAY-' . date('Ym') . '-' . strtoupper(Str::random(6));

            $vendorPayment = VendorPayment::create([
                'tenant_id' => $tenantId,
                'payment_number' => $paymentNo,
                'supplier_id' => $supplierId,
                'vendor_bill_id' => $bill?->id,
                'amount' => $amount,
                'currency' => $data['currency'] ?? $bill?->currency ?? 'INR',
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'payment_date' => $data['payment_date'] ?? Carbon::now()->toDateString(),
                'reference_number' => $data['reference_number'] ?? ('UTR' . strtoupper(Str::random(10))),
                'status' => 'disbursed',
                'approved_by_user_id' => $user?->id,
                'disbursed_by_user_id' => $user?->id,
                'notes' => $data['notes'] ?? 'Disbursed to vendor',
            ]);

            if ($bill) {
                $bill->paid_amount = round($bill->paid_amount + $amount, 2);
                $bill->outstanding_amount = max(0, round($bill->total_amount - $bill->paid_amount, 2));
                $bill->status = $bill->outstanding_amount <= 0.001 ? 'paid' : 'partially_paid';
                $bill->save();
            }

            // Create ledger entry for cash outflow
            FinanceTransaction::create([
                'tenant_id' => $tenantId,
                'transaction_number' => 'TXN-OUT-' . strtoupper(Str::random(8)),
                'internal_transaction_id' => (string) Str::uuid(),
                'provider_transaction_id' => $vendorPayment->reference_number,
                'gateway' => 'bank_transfer',
                'payment_method' => $vendorPayment->payment_method,
                'amount' => -$amount, // negative cash outflow
                'currency' => $vendorPayment->currency,
                'fee_amount' => 0.0,
                'tax_on_fee' => 0.0,
                'net_amount' => -$amount,
                'transaction_date' => $vendorPayment->payment_date,
                'provider_status' => 'captured',
                'internal_status' => 'posted',
                'reconciliation_status' => 'matched',
                'reconciled_at' => Carbon::now(),
                'is_posted' => true,
                'notes' => "Vendor payment {$vendorPayment->payment_number} to supplier #{$supplierId}",
            ]);

            return $vendorPayment->load(['supplier', 'vendorBill']);
        });
    }

    /**
     * Accounts Payable (AP) Ageing Report.
     */
    public function getPayableAgeingReport(int $tenantId, ?int $supplierId = null): array
    {
        $today = Carbon::today();

        $query = VendorBill::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['cancelled', 'paid'])
            ->where('outstanding_amount', '>', 0.001);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $bills = $query->with('supplier')->get();

        $totalPayables = 0.0;
        $totalCurrent = 0.0;
        $bucket0to30 = 0.0;
        $bucket31to45 = 0.0;
        $bucket46to90 = 0.0;
        $bucketOver90 = 0.0;

        $vendorBreakdown = [];

        foreach ($bills as $bill) {
            $due = $bill->due_date ? Carbon::parse($bill->due_date) : $bill->created_at;
            $diffDays = $today->diffInDays($due, false);
            $outstanding = (float) $bill->outstanding_amount;
            $totalPayables += $outstanding;

            if ($diffDays >= 0) {
                $totalCurrent += $outstanding;
            } else {
                $overdue = abs($diffDays);
                if ($overdue <= 30) {
                    $bucket0to30 += $outstanding;
                } elseif ($overdue <= 45) {
                    $bucket31to45 += $outstanding;
                } elseif ($overdue <= 90) {
                    $bucket46to90 += $outstanding;
                } else {
                    $bucketOver90 += $outstanding;
                }
            }

            $vName = $bill->supplier?->name ?? 'Unknown Vendor';
            if (!isset($vendorBreakdown[$vName])) {
                $vendorBreakdown[$vName] = [
                    'supplier_id' => $bill->supplier_id,
                    'vendor_name' => $vName,
                    'total_outstanding' => 0.0,
                    'bills_count' => 0,
                ];
            }
            $vendorBreakdown[$vName]['total_outstanding'] += $outstanding;
            $vendorBreakdown[$vName]['bills_count']++;
        }

        return [
            'summary' => [
                'total_payables' => round($totalPayables, 2),
                'not_due_current' => round($totalCurrent, 2),
                'total_overdue' => round($bucket0to30 + $bucket31to45 + $bucket46to90 + $bucketOver90, 2),
                'bucket_0_30' => round($bucket0to30, 2),
                'bucket_31_45' => round($bucket31to45, 2),
                'bucket_46_90' => round($bucket46to90, 2),
                'bucket_90_plus' => round($bucketOver90, 2),
                'bills_count' => $bills->count(),
            ],
            'vendor_breakdown' => array_values($vendorBreakdown),
            'bills' => $bills,
        ];
    }
}
