<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\CreditLedgerEntry;
use App\Domains\Billing\Models\TenantCostProtectionRule;
use App\Domains\Billing\Models\TenantCreditWallet;
use App\Domains\Billing\Models\TenantSubscription;
use App\Domains\Billing\Models\UsageRecord;
use App\Domains\Billing\Models\UsageReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UsageMeteringService
{
    public function __construct(
        protected CreditLedgerService $ledgerService
    ) {}

    /**
     * Stage 1 & 2: Pre-action check and reservation hold lifecycle
     */
    public function reserveUsage(int $tenantId, string $meterType, float $estimatedUnits): UsageReservation
    {
        if ($estimatedUnits <= 0) {
            throw new \InvalidArgumentException('Estimated units must be greater than zero.');
        }

        // 1. Cost Protection & Policy Checks
        $rules = TenantCostProtectionRule::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'daily_spending_cap' => 100.00,
                'monthly_spending_cap' => 2000.00,
                'ai_provider_spending_cap' => 500.00,
                'auto_pause_on_breach' => true,
                'low_balance_alert_threshold' => 20.00,
                'is_ai_calling_unlimited_allowed' => false,
                'is_paused' => false,
            ]
        );

        if ($rules->is_paused) {
            throw new \RuntimeException("Usage suspended: {$rules->pause_reason}");
        }

        // Enforce restriction: No unlimited AI calling without explicit rule
        if ($meterType === 'ai_call_minutes' && !$rules->is_ai_calling_unlimited_allowed && $estimatedUnits > 120) {
            throw new \RuntimeException('Single call duration exceeds authorized limit. Explicit platform authorization required for extended calling.');
        }

        // 2. Subscription & Allowance check
        $sub = TenantSubscription::where('tenant_id', $tenantId)
            ->with('plan')
            ->first();

        $plan = $sub?->plan;
        $unitRate = $this->resolveUnitRate($plan, $meterType);

        // Check if covered by included allowance
        $remainingAllowance = $this->getRemainingAllowance($tenantId, $sub, $meterType);
        $chargeableUnits = max(0.0, $estimatedUnits - $remainingAllowance);
        $requiredCreditHold = round($chargeableUnits * $unitRate, 4);

        $reservationId = (string) Str::uuid();

        return DB::transaction(function () use ($tenantId, $meterType, $estimatedUnits, $requiredCreditHold, $reservationId) {
            if ($requiredCreditHold > 0) {
                $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
                if (!$wallet) {
                    $wallet = $this->ledgerService->getOrCreateWallet($tenantId);
                    $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
                }

                $available = (float) $wallet->balance - (float) $wallet->reserved_balance;
                if ($available < $requiredCreditHold) {
                    throw new \RuntimeException("Insufficient credits: available \${$available}, required hold \${$requiredCreditHold}. Please recharge your credit wallet.");
                }

                // Increment reserved balance
                $wallet->update([
                    'reserved_balance' => round((float) $wallet->reserved_balance + $requiredCreditHold, 4),
                ]);
            }

            return UsageReservation::create([
                'reservation_id' => $reservationId,
                'tenant_id' => $tenantId,
                'meter_type' => $meterType,
                'estimated_units' => $estimatedUnits,
                'reserved_credit_amount' => $requiredCreditHold,
                'status' => 'reserved',
                'expires_at' => Carbon::now()->addMinutes(15),
            ]);
        });
    }

    /**
     * Stage 4 & 5: Settle actual usage, deduct charges, log provider cost separately, and release unused hold
     */
    public function settleUsage(string $reservationId, float $actualUnits, float $providerCost, array $metadata = []): array
    {
        return DB::transaction(function () use ($reservationId, $actualUnits, $providerCost, $metadata) {
            $reservation = UsageReservation::where('reservation_id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status !== 'reserved') {
                throw new \RuntimeException("Reservation {$reservationId} is already {$reservation->status}.");
            }

            $tenantId = $reservation->tenant_id;
            $meterType = $reservation->meter_type;

            $sub = TenantSubscription::where('tenant_id', $tenantId)->with('plan')->first();
            $plan = $sub?->plan;
            $unitRate = $this->resolveUnitRate($plan, $meterType);

            // Determine billable units vs remaining allowance
            $remainingAllowance = $this->getRemainingAllowance($tenantId, $sub, $meterType);
            $chargeableUnits = max(0.0, $actualUnits - $remainingAllowance);
            $actualChargeAmount = round($chargeableUnits * $unitRate, 4);

            $reservedHold = (float) $reservation->reserved_credit_amount;

            $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = $this->ledgerService->getOrCreateWallet($tenantId);
                $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
            }

            // Deduct actual charge from balance and release full hold from reserved_balance
            $newBalance = max(0.0, round((float) $wallet->balance - $actualChargeAmount, 4));
            $newReservedBalance = max(0.0, round((float) $wallet->reserved_balance - $reservedHold, 4));

            $wallet->update([
                'balance' => $newBalance,
                'reserved_balance' => $newReservedBalance,
            ]);

            // Immutable ledger entry for actual settlement
            if ($actualChargeAmount > 0) {
                CreditLedgerEntry::create([
                    'entry_id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'entry_type' => 'settlement',
                    'amount' => -$actualChargeAmount,
                    'balance_after' => $newBalance,
                    'reference_id' => $reservationId,
                    'description' => "Usage settlement for {$actualUnits} {$meterType}",
                    'metadata' => array_merge($metadata, [
                        'meter_type' => $meterType,
                        'units' => $actualUnits,
                        'provider_cost' => $providerCost,
                    ]),
                    'created_at' => Carbon::now(),
                ]);
            }

            // Create actual usage record (tracking provider cost separately!)
            $usageRecord = UsageRecord::create([
                'tenant_id' => $tenantId,
                'reservation_id' => $reservationId,
                'meter_type' => $meterType,
                'units' => $actualUnits,
                'tenant_charge_amount' => $actualChargeAmount,
                'provider_cost_amount' => round($providerCost, 4), // Separately recorded!
                'is_overage' => $chargeableUnits > 0,
                'billing_period_start' => $sub?->current_period_start ? $sub->current_period_start->toDateString() : Carbon::now()->startOfMonth()->toDateString(),
                'billing_period_end' => $sub?->current_period_end ? $sub->current_period_end->toDateString() : Carbon::now()->endOfMonth()->toDateString(),
                'metadata' => $metadata,
            ]);

            $reservation->update([
                'status' => 'settled',
                'settled_at' => Carbon::now(),
            ]);

            return [
                'success' => true,
                'reservation_id' => $reservationId,
                'actual_units' => $actualUnits,
                'tenant_charge' => $actualChargeAmount,
                'provider_cost' => round($providerCost, 4),
                'released_hold' => round(max(0.0, $reservedHold - $actualChargeAmount), 4),
                'wallet_balance' => $newBalance,
            ];
        });
    }

    /**
     * Stage 6: Release unused reservation hold back to wallet
     */
    public function releaseReservation(string $reservationId, string $reason = 'cancelled'): bool
    {
        return DB::transaction(function () use ($reservationId, $reason) {
            $reservation = UsageReservation::where('reservation_id', $reservationId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($reservation->status !== 'reserved') {
                return false;
            }

            $reservedHold = (float) $reservation->reserved_credit_amount;
            $tenantId = $reservation->tenant_id;

            if ($reservedHold > 0) {
                $wallet = TenantCreditWallet::where('tenant_id', $tenantId)->lockForUpdate()->first();
                if ($wallet) {
                    $newReserved = max(0.0, round((float) $wallet->reserved_balance - $reservedHold, 4));
                    $wallet->update(['reserved_balance' => $newReserved]);

                    CreditLedgerEntry::create([
                        'entry_id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'entry_type' => 'release',
                        'amount' => 0.0000,
                        'balance_after' => (float) $wallet->balance,
                        'reference_id' => $reservationId,
                        'description' => "Released hold of \${$reservedHold} for {$reservation->meter_type} ({$reason})",
                        'metadata' => ['reservation_id' => $reservationId, 'reason' => $reason],
                        'created_at' => Carbon::now(),
                    ]);
                }
            }

            $reservation->update(['status' => 'released']);
            return true;
        });
    }

    /**
     * Get detailed usage summary for the tenant dashboard
     */
    public function getUsageDashboard(int $tenantId): array
    {
        $sub = TenantSubscription::where('tenant_id', $tenantId)->with('plan')->first();
        $wallet = $this->ledgerService->getOrCreateWallet($tenantId);
        $rules = TenantCostProtectionRule::firstOrCreate(['tenant_id' => $tenantId]);

        $start = $sub?->current_period_start ?? Carbon::now()->startOfMonth();
        $end = $sub?->current_period_end ?? Carbon::now()->endOfMonth();

        $records = UsageRecord::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $allowances = $sub?->plan?->included_allowance ?? [];
        $overagePricing = $sub?->plan?->overage_pricing ?? [];

        $meters = [
            'llm_tokens' => 'LLM Tokens & Reasoning',
            'ai_call_minutes' => 'AI Calling Minutes',
            'telephony_minutes' => 'Telephony Minutes',
            'whatsapp_messages' => 'WhatsApp Messages',
            'sms_segments' => 'SMS Segments',
            'email_volume' => 'Email Broadcast Volume',
            'storage_mb' => 'Cloud Storage (MB)',
        ];

        $meterUsage = [];
        $totalTenantCharge = 0.0;
        $totalProviderCost = 0.0;

        foreach ($meters as $mKey => $mLabel) {
            $usedUnits = (float) $records->where('meter_type', $mKey)->sum('units');
            $tenantCharge = (float) $records->where('meter_type', $mKey)->sum('tenant_charge_amount');
            $providerCost = (float) $records->where('meter_type', $mKey)->sum('provider_cost_amount');
            $included = (float) ($allowances[$mKey] ?? 0);
            $overageUnits = max(0.0, $usedUnits - $included);

            $totalTenantCharge += $tenantCharge;
            $totalProviderCost += $providerCost;

            $meterUsage[$mKey] = [
                'label' => $mLabel,
                'used_units' => $usedUnits,
                'included_allowance' => $included,
                'overage_units' => $overageUnits,
                'tenant_charge' => round($tenantCharge, 2),
                'provider_cost' => round($providerCost, 2),
                'unit_rate' => $this->resolveUnitRate($sub?->plan, $mKey),
            ];
        }

        return [
            'subscription' => $sub ? [
                'plan_name' => $sub->plan?->name,
                'plan_code' => $sub->plan?->code,
                'status' => $sub->status,
                'renewal_date' => $sub->current_period_end ? $sub->current_period_end->toDateString() : null,
                'trial_ends_at' => $sub->trial_ends_at ? $sub->trial_ends_at->toDateString() : null,
                'billing_cycle' => $sub->plan?->billing_cycle,
                'price' => $sub->plan?->price,
            ] : null,
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'reserved_balance' => (float) $wallet->reserved_balance,
                'available_balance' => $wallet->available_balance,
                'currency' => $wallet->currency,
            ],
            'cost_protection' => [
                'daily_spending_cap' => (float) $rules->daily_spending_cap,
                'monthly_spending_cap' => (float) $rules->monthly_spending_cap,
                'ai_provider_spending_cap' => (float) $rules->ai_provider_spending_cap,
                'is_ai_calling_unlimited_allowed' => $rules->is_ai_calling_unlimited_allowed,
                'is_paused' => $rules->is_paused,
            ],
            'usage_by_category' => $meterUsage,
            'summary' => [
                'total_tenant_charge' => round($totalTenantCharge, 2),
                'total_provider_cost' => round($totalProviderCost, 2), // Authorized admin insight
                'gross_margin' => $totalTenantCharge > 0 ? round((($totalTenantCharge - $totalProviderCost) / $totalTenantCharge) * 100, 1) : 0,
            ],
        ];
    }

    protected function resolveUnitRate(?\App\Domains\Billing\Models\SubscriptionPlan $plan, string $meterType): float
    {
        $pricing = $plan?->overage_pricing ?? [];
        return match ($meterType) {
            'llm_tokens' => (float) ($pricing['llm_tokens_per_unit'] ?? 0.00002), // $0.02 per 1k tokens
            'ai_call_minutes' => (float) ($pricing['ai_call_minutes'] ?? 0.12), // $0.12 per minute
            'telephony_minutes' => (float) ($pricing['telephony_minutes'] ?? 0.04), // $0.04 per minute
            'whatsapp_messages' => (float) ($pricing['whatsapp_messages'] ?? 0.008), // $0.008 per msg
            'sms_segments' => (float) ($pricing['sms_segments'] ?? 0.015), // $0.015 per SMS
            'email_volume' => (float) ($pricing['email_volume'] ?? 0.0008), // $0.80 per 1k emails
            'storage_mb' => (float) ($pricing['storage_mb'] ?? 0.0001), // $0.10 per GB
            default => 0.05,
        };
    }

    protected function getRemainingAllowance(int $tenantId, ?TenantSubscription $sub, string $meterType): float
    {
        if (!$sub || !$sub->isActive()) {
            return 0.0;
        }

        $included = (float) ($sub->plan?->included_allowance[$meterType] ?? 0);
        if ($included <= 0) {
            return 0.0;
        }

        $start = $sub->current_period_start ?? Carbon::now()->startOfMonth();
        $end = $sub->current_period_end ?? Carbon::now()->endOfMonth();

        $consumed = (float) UsageRecord::where('tenant_id', $tenantId)
            ->where('meter_type', $meterType)
            ->whereBetween('created_at', [$start, $end])
            ->sum('units');

        return max(0.0, $included - $consumed);
    }
}
