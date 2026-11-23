<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\SubscriptionInvoice;
use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\Billing\Models\TenantSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionBillingService
{
    public function __construct(
        protected CreditLedgerService $ledgerService
    ) {}

    /**
     * Get all active subscription plans or seed default tiers if table is empty
     */
    public function getPlans()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('price', 'asc')
            ->get();

        if ($plans->isEmpty()) {
            $this->seedDefaultPlans();
            $plans = SubscriptionPlan::where('is_active', true)->where('is_public', true)->orderBy('price', 'asc')->get();
        }

        return $plans;
    }

    /**
     * Seed initial production-grade SaaS pricing tiers
     */
    public function seedDefaultPlans(): void
    {
        SubscriptionPlan::create([
            'name' => 'Starter Growth',
            'code' => 'starter',
            'description' => 'Essential CRM, contact management, and basic WhatsApp integration for small sales teams.',
            'billing_cycle' => 'monthly',
            'price' => 49.00,
            'currency' => 'USD',
            'trial_days' => 14,
            'grace_period_days' => 7,
            'features' => ['leads', 'deals', 'contacts', 'whatsapp_inbox', 'basic_reporting'],
            'limits' => [
                'seats' => 3,
                'leads' => 2000,
                'whatsapp_numbers' => 1,
                'ai_agents' => 1,
                'workflows' => 5,
                'storage_mb' => 5120,
                'api_rpm' => 60,
            ],
            'included_allowance' => [
                'llm_tokens' => 100000,
                'ai_call_minutes' => 30,
                'telephony_minutes' => 100,
                'whatsapp_messages' => 1000,
                'sms_segments' => 500,
                'email_volume' => 5000,
            ],
            'overage_pricing' => [
                'llm_tokens_per_unit' => 0.00002,
                'ai_call_minutes' => 0.12,
                'telephony_minutes' => 0.04,
                'whatsapp_messages' => 0.008,
                'sms_segments' => 0.015,
                'email_volume' => 0.0008,
            ],
            'is_active' => true,
            'is_public' => true,
        ]);

        SubscriptionPlan::create([
            'name' => 'Professional Scale',
            'code' => 'pro',
            'description' => 'Advanced AI call assistants, visual workflow automations, and multi-channel attribution for growing teams.',
            'billing_cycle' => 'monthly',
            'price' => 149.00,
            'currency' => 'USD',
            'trial_days' => 14,
            'grace_period_days' => 7,
            'features' => ['leads', 'deals', 'contacts', 'whatsapp_inbox', 'whatsapp_ai', 'voice_telephony', 'workflows', 'landing_pages', 'multi_touch_attribution'],
            'limits' => [
                'seats' => 10,
                'leads' => 15000,
                'whatsapp_numbers' => 3,
                'ai_agents' => 5,
                'workflows' => 25,
                'storage_mb' => 25600,
                'api_rpm' => 180,
            ],
            'included_allowance' => [
                'llm_tokens' => 500000,
                'ai_call_minutes' => 150,
                'telephony_minutes' => 500,
                'whatsapp_messages' => 5000,
                'sms_segments' => 2500,
                'email_volume' => 25000,
            ],
            'overage_pricing' => [
                'llm_tokens_per_unit' => 0.000015,
                'ai_call_minutes' => 0.10,
                'telephony_minutes' => 0.035,
                'whatsapp_messages' => 0.007,
                'sms_segments' => 0.012,
                'email_volume' => 0.0006,
            ],
            'is_active' => true,
            'is_public' => true,
        ]);

        SubscriptionPlan::create([
            'name' => 'Enterprise Unlimited',
            'code' => 'enterprise',
            'description' => 'Dedicated telephony infrastructure, unlimited custom integrations, highest API quotas, and priority SLAs.',
            'billing_cycle' => 'monthly',
            'price' => 399.00,
            'currency' => 'USD',
            'trial_days' => 14,
            'grace_period_days' => 7,
            'features' => ['*'],
            'limits' => [
                'seats' => 50,
                'leads' => 100000,
                'whatsapp_numbers' => 10,
                'ai_agents' => 20,
                'workflows' => 100,
                'storage_mb' => 102400,
                'api_rpm' => 600,
            ],
            'included_allowance' => [
                'llm_tokens' => 2000000,
                'ai_call_minutes' => 600,
                'telephony_minutes' => 2000,
                'whatsapp_messages' => 25000,
                'sms_segments' => 10000,
                'email_volume' => 100000,
            ],
            'overage_pricing' => [
                'llm_tokens_per_unit' => 0.00001,
                'ai_call_minutes' => 0.08,
                'telephony_minutes' => 0.025,
                'whatsapp_messages' => 0.006,
                'sms_segments' => 0.01,
                'email_volume' => 0.0005,
            ],
            'is_active' => true,
            'is_public' => true,
        ]);
    }

    /**
     * Retrieve active tenant subscription and plan limits
     */
    public function getTenantSubscription(int $tenantId): ?TenantSubscription
    {
        return TenantSubscription::where('tenant_id', $tenantId)
            ->with(['plan', 'invoices' => fn($q) => $q->orderBy('created_at', 'desc')->take(10)])
            ->first();
    }

    /**
     * Start a free trial subscription
     */
    public function startTrial(int $tenantId, int $planId, array $billingDetails = []): TenantSubscription
    {
        $plan = SubscriptionPlan::findOrFail($planId);
        $trialDays = $plan->trial_days ?: 14;

        return TenantSubscription::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'plan_id' => $planId,
                'status' => 'trialing',
                'trial_starts_at' => Carbon::now(),
                'trial_ends_at' => Carbon::now()->addDays($trialDays),
                'current_period_start' => Carbon::now(),
                'current_period_end' => Carbon::now()->addDays($trialDays),
                'billing_contact_name' => $billingDetails['billing_contact_name'] ?? null,
                'billing_email' => $billingDetails['billing_email'] ?? null,
                'billing_address' => $billingDetails['billing_address'] ?? null,
                'tax_id' => $billingDetails['tax_id'] ?? null,
                'auto_renew' => true,
            ]
        );
    }

    /**
     * Change plan with proration calculation
     */
    public function changePlan(int $tenantId, int $newPlanId, bool $prorate = true): array
    {
        $newPlan = SubscriptionPlan::findOrFail($newPlanId);
        $currentSub = TenantSubscription::where('tenant_id', $tenantId)->with('plan')->first();

        if (!$currentSub) {
            $sub = $this->startTrial($tenantId, $newPlanId);
            return [
                'subscription' => $sub,
                'prorated_credit' => 0.0,
                'net_amount_due' => (float) $newPlan->price,
            ];
        }

        $now = Carbon::now();
        $proratedCredit = 0.0;

        if ($prorate && $currentSub->plan && $currentSub->current_period_end && $currentSub->current_period_end->isFuture()) {
            $totalDays = max(1, $currentSub->current_period_start->diffInDays($currentSub->current_period_end));
            $remainingDays = max(0, $now->diffInDays($currentSub->current_period_end));
            $oldPlanPrice = (float) $currentSub->plan->price;
            $proratedCredit = round(($oldPlanPrice / $totalDays) * $remainingDays, 2);
        }

        $netAmountDue = max(0.0, round((float) $newPlan->price - $proratedCredit, 2));

        $currentSub->update([
            'plan_id' => $newPlanId,
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => $now,
            'current_period_end' => $now->copy()->addMonth(),
            'grace_period_ends_at' => null,
        ]);

        // Generate subscription invoice
        $invoice = SubscriptionInvoice::create([
            'tenant_id' => $tenantId,
            'subscription_id' => $currentSub->id,
            'invoice_number' => 'INV-SUB-' . strtoupper(Str::random(8)),
            'amount' => (float) $newPlan->price,
            'tax_amount' => 0.00,
            'total_amount' => $netAmountDue,
            'status' => $netAmountDue === 0.0 ? 'paid' : 'pending',
            'due_date' => $now->toDateString(),
            'paid_at' => $netAmountDue === 0.0 ? $now : null,
            'line_items' => [
                [
                    'description' => "Subscription to {$newPlan->name}",
                    'amount' => (float) $newPlan->price,
                ],
                [
                    'description' => "Prorated credit from {$currentSub->plan?->name}",
                    'amount' => -$proratedCredit,
                ],
            ],
        ]);

        return [
            'subscription' => $currentSub->fresh('plan'),
            'prorated_credit' => $proratedCredit,
            'net_amount_due' => $netAmountDue,
            'invoice' => $invoice,
        ];
    }

    /**
     * Renew subscription period and generate renewal invoice
     */
    public function renewSubscription(int $tenantId, ?string $razorpayPaymentId = null): TenantSubscription
    {
        $sub = TenantSubscription::where('tenant_id', $tenantId)->with('plan')->firstOrFail();

        $start = Carbon::now();
        $cycle = $sub->plan?->billing_cycle === 'annual' ? 'addYear' : 'addMonth';
        $end = $start->copy()->$cycle();

        $sub->update([
            'status' => 'active',
            'current_period_start' => $start,
            'current_period_end' => $end,
            'grace_period_ends_at' => null,
        ]);

        SubscriptionInvoice::create([
            'tenant_id' => $tenantId,
            'subscription_id' => $sub->id,
            'invoice_number' => 'INV-REN-' . strtoupper(Str::random(8)),
            'amount' => (float) $sub->plan?->price,
            'tax_amount' => 0.00,
            'total_amount' => (float) $sub->plan?->price,
            'status' => 'paid',
            'due_date' => $start->toDateString(),
            'paid_at' => $start,
            'razorpay_payment_id' => $razorpayPaymentId,
            'line_items' => [
                ['description' => "Renewal for {$sub->plan?->name}", 'amount' => (float) $sub->plan?->price],
            ],
        ]);

        return $sub;
    }

    /**
     * Handle payment failure: transition to past_due and activate grace period (7 days)
     */
    public function handleFailedPayment(int $tenantId): TenantSubscription
    {
        $sub = TenantSubscription::where('tenant_id', $tenantId)->with('plan')->firstOrFail();
        $graceDays = $sub->plan?->grace_period_days ?: 7;

        $sub->update([
            'status' => 'past_due',
            'grace_period_ends_at' => Carbon::now()->addDays($graceDays),
        ]);

        return $sub;
    }

    /**
     * Cancel subscription at period end
     */
    public function cancelSubscription(int $tenantId, string $reason = 'Customer cancellation'): TenantSubscription
    {
        $sub = TenantSubscription::where('tenant_id', $tenantId)->firstOrFail();
        $sub->update([
            'auto_renew' => false,
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
        ]);

        return $sub;
    }

    /**
     * Update billing details
     */
    public function updateBillingDetails(int $tenantId, array $data): TenantSubscription
    {
        $sub = TenantSubscription::where('tenant_id', $tenantId)->firstOrFail();
        $sub->update($data);
        return $sub;
    }

    public function getInvoices(int $tenantId)
    {
        return SubscriptionInvoice::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
