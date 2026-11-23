<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\SubscriptionInvoice;
use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\Billing\Models\TenantSubscription;
use App\Domains\PlatformAdmin\Models\PlatformAuditLog;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaasSubscriptionBillingService
{
    /**
     * Create or Update Commercial Subscription Plan
     */
    public function savePlan(array $data, ?int $planId = null): SubscriptionPlan
    {
        $code = Str::slug($data['code'] ?? $data['name']);
        
        $attributes = [
            'name' => $data['name'],
            'code' => $code,
            'description' => $data['description'] ?? null,
            'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
            'price' => (float) ($data['price'] ?? 0.00),
            'currency' => $data['currency'] ?? 'INR',
            'trial_days' => (int) ($data['trial_days'] ?? 14),
            'grace_period_days' => (int) ($data['grace_period_days'] ?? 7),
            'features' => $data['features'] ?? ['crm_core'],
            'limits' => $data['limits'] ?? [
                'users' => 5,
                'storage_mb' => 1024,
                'workflows' => 20,
                'ai_credits' => 500,
            ],
            'included_allowance' => $data['included_allowance'] ?? [],
            'overage_pricing' => $data['overage_pricing'] ?? [],
            'is_active' => $data['is_active'] ?? true,
            'is_public' => $data['is_public'] ?? true,
        ];

        if ($planId) {
            $plan = SubscriptionPlan::findOrFail($planId);
            $plan->update($attributes);
            return $plan;
        }

        return SubscriptionPlan::create($attributes);
    }

    /**
     * Upgrade or Change Tenant Subscription
     */
    public function changeSubscription(Tenant $tenant, SubscriptionPlan $newPlan, string $cycle = 'monthly', ?User $adminUser = null): array
    {
        return DB::transaction(function () use ($tenant, $newPlan, $cycle, $adminUser) {
            $currentSub = TenantSubscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trialing', 'grace_period', 'past_due'])
                ->first();

            $price = (float) $newPlan->price;
            if ($cycle === 'annual' && $price > 0) {
                $price = $price * 10; // 2 months free annual discount
            }

            // Calculate Indian GST (18%)
            $taxRate = 0.18;
            $taxAmount = round($price * $taxRate, 2);
            $totalAmount = round($price + $taxAmount, 2);

            $periodStart = Carbon::now();
            $periodEnd = $cycle === 'annual' ? Carbon::now()->addYear() : Carbon::now()->addMonth();

            if ($currentSub) {
                $currentSub->update([
                    'plan_id' => $newPlan->id,
                    'status' => 'active',
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'grace_period_ends_at' => null,
                ]);
                $subscription = $currentSub;
            } else {
                $subscription = TenantSubscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $newPlan->id,
                    'status' => 'active',
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                    'billing_contact_name' => $tenant->name,
                    'tax_id' => $tenant->gstin,
                ]);
            }

            // Update tenant tier
            $tenant->update([
                'subscription_tier' => $newPlan->code,
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            // Generate GST Subscription Invoice
            $invoiceNumber = 'INV-SAAS-' . Carbon::now()->format('Ymd') . '-' . strtoupper(Str::random(5));
            $isInterState = !empty($tenant->billing_state_code) && $tenant->billing_state_code !== '27'; // 27 = Maharashtra

            $lineItems = [
                [
                    'description' => "{$newPlan->name} Subscription Plan ({$cycle})",
                    'period' => "{$periodStart->toDateString()} to {$periodEnd->toDateString()}",
                    'base_amount' => $price,
                    'hsn_sac' => '998313', // Information technology software services
                    'tax_rate_percent' => 18,
                    'cgst' => $isInterState ? 0 : round($taxAmount / 2, 2),
                    'sgst' => $isInterState ? 0 : round($taxAmount / 2, 2),
                    'igst' => $isInterState ? $taxAmount : 0,
                    'total_line_amount' => $totalAmount,
                ]
            ];

            $invoice = SubscriptionInvoice::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $price,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'paid',
                'due_date' => Carbon::now()->addDays(7),
                'paid_at' => Carbon::now(),
                'razorpay_payment_id' => 'pay_saas_' . Str::random(12),
                'line_items' => $lineItems,
            ]);

            // Audit
            PlatformAuditLog::create([
                'user_id' => $adminUser ? $adminUser->id : null,
                'admin_name' => $adminUser ? $adminUser->name : 'System',
                'admin_email' => $adminUser ? $adminUser->email : 'system@platform.saas',
                'action' => 'subscription.plan_changed',
                'target_type' => 'Tenant',
                'target_id' => (string) $tenant->id,
                'details' => [
                    'new_plan' => $newPlan->code,
                    'amount' => $totalAmount,
                    'invoice_number' => $invoiceNumber,
                ],
            ]);

            return [
                'subscription' => $subscription->fresh(['plan']),
                'invoice' => $invoice,
                'tenant' => $tenant->fresh(),
            ];
        });
    }

    /**
     * Suspend Organization Access
     */
    public function suspendTenant(Tenant $tenant, string $reason, ?User $adminUser = null): Tenant
    {
        $tenant->update([
            'status' => 'suspended',
            'suspended_at' => Carbon::now(),
            'suspension_reason' => $reason,
        ]);

        TenantSubscription::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->update(['status' => 'past_due']);

        PlatformAuditLog::create([
            'user_id' => $adminUser ? $adminUser->id : null,
            'admin_name' => $adminUser ? $adminUser->name : 'Platform Admin',
            'admin_email' => $adminUser ? $adminUser->email : 'admin@platform.saas',
            'action' => 'tenant.suspended',
            'target_type' => 'Tenant',
            'target_id' => (string) $tenant->id,
            'details' => ['reason' => $reason],
        ]);

        return $tenant;
    }

    /**
     * Reactivate Suspended Organization
     */
    public function reactivateTenant(Tenant $tenant, ?User $adminUser = null): Tenant
    {
        $tenant->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        TenantSubscription::where('tenant_id', $tenant->id)
            ->where('status', 'past_due')
            ->update(['status' => 'active']);

        PlatformAuditLog::create([
            'user_id' => $adminUser ? $adminUser->id : null,
            'admin_name' => $adminUser ? $adminUser->name : 'Platform Admin',
            'admin_email' => $adminUser ? $adminUser->email : 'admin@platform.saas',
            'action' => 'tenant.reactivated',
            'target_type' => 'Tenant',
            'target_id' => (string) $tenant->id,
            'details' => ['status' => 'active'],
        ]);

        return $tenant;
    }

    /**
     * Get Aggregated SaaS Financial Metrics (MRR, ARR, Invoices, Churn)
     */
    public function getPlatformRevenueMetrics(): array
    {
        $activeSubs = TenantSubscription::where('status', 'active')->with('plan')->get();

        $mrr = 0.0;
        foreach ($activeSubs as $sub) {
            $planPrice = (float) ($sub->plan->price ?? 0);
            if ($sub->plan && $sub->plan->billing_cycle === 'annual') {
                $mrr += ($planPrice / 12);
            } else {
                $mrr += $planPrice;
            }
        }

        $arr = $mrr * 12;

        $invoices = SubscriptionInvoice::all();
        $totalCollected = (float) $invoices->where('status', 'paid')->sum('total_amount');
        $totalTax = (float) $invoices->where('status', 'paid')->sum('tax_amount');
        $failedCount = $invoices->where('status', 'failed')->count();

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'active_subscriptions_count' => $activeSubs->count(),
            'total_invoiced_collections' => round($totalCollected, 2),
            'total_gst_collected' => round($totalTax, 2),
            'failed_invoices_count' => $failedCount,
            'recent_invoices' => SubscriptionInvoice::with('tenant')->latest()->take(10)->get(),
        ];
    }
}
