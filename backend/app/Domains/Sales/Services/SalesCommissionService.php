<?php

namespace App\Domains\Sales\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Payment;
use App\Domains\Sales\Models\CommissionPlan;
use App\Domains\Sales\Models\SalesCommission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesCommissionService
{
    /**
     * Calculate and record commission for a won deal.
     */
    public function calculateDealWonCommission(Deal $deal): ?SalesCommission
    {
        $tenantId = $deal->tenant_id;
        $userId = $deal->assigned_user_id;

        if (!$userId) {
            return null;
        }

        // Idempotency: Prevent duplicate commission for same won deal
        $existing = SalesCommission::where('tenant_id', $tenantId)
            ->where('deal_id', $deal->id)
            ->where('calculation_basis', 'deal_won')
            ->whereIn('status', ['pending_approval', 'approved', 'paid'])
            ->first();

        if ($existing) {
            return $existing;
        }

        // Find active plan for tenant
        $plan = CommissionPlan::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('calculation_basis', 'deal_won')
            ->first();

        $dealValue = (float) $deal->value;
        $commissionAmount = 0.0;
        $commissionRate = 0.0;

        if ($plan) {
            if ($plan->plan_type === 'fixed') {
                $commissionAmount = (float) $plan->fixed_amount;
                $commissionRate = $dealValue > 0 ? round(($commissionAmount / $dealValue) * 100, 2) : 0;
            } elseif ($plan->plan_type === 'tiered' && !empty($plan->tier_rules)) {
                // Tiered rules e.g. [{"min": 0, "max": 100000, "rate": 5}, {"min": 100000, "rate": 8}]
                $commissionRate = (float) $plan->rate_percentage;
                foreach ($plan->tier_rules as $tier) {
                    $min = (float) ($tier['min'] ?? 0);
                    $max = isset($tier['max']) ? (float) $tier['max'] : null;
                    if ($dealValue >= $min && (is_null($max) || $dealValue < $max)) {
                        $commissionRate = (float) ($tier['rate'] ?? $plan->rate_percentage);
                        break;
                    }
                }
                $commissionAmount = round(($dealValue * $commissionRate) / 100, 2);
            } else {
                // Standard percentage
                $commissionRate = (float) $plan->rate_percentage;
                $commissionAmount = round(($dealValue * $commissionRate) / 100, 2);
            }
        } else {
            // Default baseline: 5%
            $commissionRate = 5.00;
            $commissionAmount = round(($dealValue * $commissionRate) / 100, 2);
        }

        return SalesCommission::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'commission_plan_id' => $plan?->id,
            'deal_id' => $deal->id,
            'base_amount' => $dealValue,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'calculation_basis' => 'deal_won',
            'status' => 'pending_approval',
            'notes' => "Commission generated for Won Deal #{$deal->id} ({$deal->name}).",
        ]);
    }

    /**
     * Calculate and record commission on payment collected.
     */
    public function calculatePaymentCommission(Payment $payment): ?SalesCommission
    {
        $tenantId = $payment->tenant_id;
        $invoice = $payment->invoice;
        $deal = $invoice?->deal;
        $userId = $deal?->assigned_user_id ?? $payment->created_by_user_id;

        if (!$userId) {
            return null;
        }

        // Idempotency: Avoid double payout on the same payment
        $existing = SalesCommission::where('tenant_id', $tenantId)
            ->where('payment_id', $payment->id)
            ->where('calculation_basis', 'payment_collected')
            ->first();

        if ($existing) {
            return $existing;
        }

        $paymentAmount = (float) $payment->amount;

        $plan = CommissionPlan::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('calculation_basis', 'payment_collected')
            ->first();

        $rate = $plan ? (float) $plan->rate_percentage : 3.00; // default 3% on collection
        $commissionAmount = round(($paymentAmount * $rate) / 100, 2);

        return SalesCommission::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'commission_plan_id' => $plan?->id,
            'deal_id' => $deal?->id,
            'invoice_id' => $invoice?->id,
            'payment_id' => $payment->id,
            'base_amount' => $paymentAmount,
            'commission_rate' => $rate,
            'commission_amount' => $commissionAmount,
            'calculation_basis' => 'payment_collected',
            'status' => 'pending_approval',
            'notes' => "Commission earned on Payment #{$payment->id} (Invoice: {$invoice?->invoice_number}).",
        ]);
    }

    /**
     * Approve commission payout.
     */
    public function approveCommission(SalesCommission $commission, User $approver): SalesCommission
    {
        if ($commission->status !== 'pending_approval') {
            throw new InvalidArgumentException("Commission is not currently pending approval.");
        }

        $commission->update([
            'status' => 'approved',
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        return $commission->fresh();
    }

    /**
     * Mark commission as paid.
     */
    public function markAsPaid(SalesCommission $commission, ?string $payoutDate = null): SalesCommission
    {
        if (!in_array($commission->status, ['pending_approval', 'approved'])) {
            throw new InvalidArgumentException("Only approved commissions can be paid.");
        }

        $commission->update([
            'status' => 'paid',
            'payout_date' => $payoutDate ?: now()->toDateString(),
        ]);

        return $commission->fresh();
    }

    /**
     * Reverse / clawback commission on refund or deal cancellation.
     */
    public function reverseCommission(SalesCommission $commission, string $reason): SalesCommission
    {
        if ($commission->status === 'reversed') {
            return $commission;
        }

        $commission->update([
            'status' => 'reversed',
            'reversal_reason' => $reason,
            'reversed_at' => now(),
        ]);

        return $commission->fresh();
    }

    /**
     * Aggregate commission reports.
     */
    public function getCommissionReport(int $tenantId, ?int $userId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = SalesCommission::where('tenant_id', $tenantId)->with(['user:id,name,email', 'deal:id,name', 'commissionPlan:id,name']);

        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $commissions = $query->orderByDesc('id')->get();

        $totalEarned = $commissions->where('status', '!=', 'reversed')->sum('commission_amount');
        $totalPaid = $commissions->where('status', 'paid')->sum('commission_amount');
        $totalPending = $commissions->where('status', 'pending_approval')->sum('commission_amount');
        $totalApproved = $commissions->where('status', 'approved')->sum('commission_amount');
        $totalReversed = $commissions->where('status', 'reversed')->sum('commission_amount');

        $byEmployee = [];
        foreach ($commissions as $comm) {
            $emp = $comm->user?->name ?? 'Unknown';
            if (!isset($byEmployee[$emp])) {
                $byEmployee[$emp] = ['count' => 0, 'earned' => 0.0, 'paid' => 0.0, 'pending' => 0.0];
            }
            $byEmployee[$emp]['count']++;
            if ($comm->status !== 'reversed') {
                $byEmployee[$emp]['earned'] += (float) $comm->commission_amount;
                if ($comm->status === 'paid') {
                    $byEmployee[$emp]['paid'] += (float) $comm->commission_amount;
                } elseif ($comm->status === 'pending_approval') {
                    $byEmployee[$emp]['pending'] += (float) $comm->commission_amount;
                }
            }
        }

        return [
            'total_commission_calculated' => round((float) $totalEarned, 2),
            'summary' => [
                'total_commissions_count' => $commissions->count(),
                'total_commission_calculated' => round((float) $totalEarned, 2),
                'total_earned' => round((float) $totalEarned, 2),
                'total_paid' => round((float) $totalPaid, 2),
                'total_pending' => round((float) $totalPending, 2),
                'total_approved' => round((float) $totalApproved, 2),
                'total_reversed' => round((float) $totalReversed, 2),
            ],
            'by_employee' => $byEmployee,
            'recent_commissions' => $commissions->take(50),
        ];
    }
}
