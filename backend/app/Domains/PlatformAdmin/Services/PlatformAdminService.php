<?php

namespace App\Domains\PlatformAdmin\Services;

use App\Domains\Billing\Models\CreditLedgerEntry;
use App\Domains\Billing\Models\SubscriptionInvoice;
use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\Billing\Models\TenantCreditWallet;
use App\Domains\Billing\Models\TenantSubscription;
use App\Domains\Billing\Models\UsageRecord;
use App\Domains\Billing\Services\SubscriptionBillingService;
use App\Domains\Integrations\Models\IntegrationConnection;
use App\Domains\PlatformAdmin\Models\PlatformAuditLog;
use App\Domains\PlatformAdmin\Models\PlatformSupportAccessLog;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformAdminService
{
    public function __construct(
        protected SubscriptionBillingService $billingService
    ) {}

    /**
     * Platform Overview Metrics (Global Cross-Tenant Aggregation)
     */
    public function getOverviewMetrics(): array
    {
        // 1. Tenant counts
        $totalBusinesses = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $trialTenants = Tenant::where('status', 'trialing')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $cancelledTenants = Tenant::where('status', 'cancelled')->count();
        $totalUsers = User::count();

        // 2. Subscriptions & Revenue
        $activeSubs = TenantSubscription::withoutGlobalScopes()
            ->whereIn('status', ['active', 'trialing', 'grace_period'])
            ->with('plan')
            ->get();
        $activeSubsCount = $activeSubs->count();

        $monthlyMrr = 0.0;
        $annualArr = 0.0;

        foreach ($activeSubs as $sub) {
            $plan = $sub->plan;
            if ($plan && $sub->status === 'active') {
                if ($plan->billing_cycle === 'annual') {
                    $annualArr += (float) $plan->price;
                } else {
                    $monthlyMrr += (float) $plan->price;
                }
            }
        }

        $mrr = round($monthlyMrr + ($annualArr / 12), 2);
        $arr = round($mrr * 12, 2);

        // 3. Payment Collections
        $totalInvoiced = (float) SubscriptionInvoice::withoutGlobalScopes()->where('status', 'paid')->sum('total_amount');
        $totalRecharges = (float) CreditLedgerEntry::withoutGlobalScopes()->where('entry_type', 'recharge')->sum('amount');
        $totalCollections = round($totalInvoiced + $totalRecharges, 2);

        // 4. Usage, Provider Wholesale Costs & Gross Margin
        $totalRetailUsage = (float) UsageRecord::withoutGlobalScopes()->sum('tenant_charge_amount');
        $totalProviderCost = (float) UsageRecord::withoutGlobalScopes()->sum('provider_cost_amount');
        $grossProfit = round($totalRetailUsage - $totalProviderCost, 2);
        $grossMarginPercent = $totalRetailUsage > 0
            ? round(($grossProfit / $totalRetailUsage) * 100, 1)
            : 100.0;

        // 5. Consumption Volume by Meter Type
        $aiTokens = (int) UsageRecord::withoutGlobalScopes()->where('meter_type', 'llm_tokens')->sum('units');
        $callingMinutes = (float) UsageRecord::withoutGlobalScopes()->where('meter_type', 'ai_call_minutes')->sum('units')
            + (float) UsageRecord::withoutGlobalScopes()->where('meter_type', 'telephony_minutes')->sum('units');
        $whatsappMessages = (int) UsageRecord::withoutGlobalScopes()->where('meter_type', 'whatsapp_messages')->sum('units');
        $smsSegments = (int) UsageRecord::withoutGlobalScopes()->where('meter_type', 'sms_segments')->sum('units');
        $emailVolume = (int) UsageRecord::withoutGlobalScopes()->where('meter_type', 'emails')->sum('units');

        // 6. System & Infrastructure Health
        $failedIntegrationsCount = 0;
        try {
            $failedIntegrationsCount = IntegrationConnection::where('status', 'error')->count();
        } catch (\Throwable $e) {}

        $failedJobsCount = 0;
        try {
            $failedJobsCount = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {}

        // Health Checks
        $dbStatus = 'healthy';
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'unhealthy';
        }

        $cacheStatus = 'healthy';
        try {
            Cache::put('platform_health_test', 1, 10);
            if (Cache::get('platform_health_test') !== 1) {
                $cacheStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $cacheStatus = 'unhealthy';
        }

        return [
            'tenants' => [
                'total' => $totalBusinesses,
                'active' => $activeTenants,
                'trialing' => $trialTenants,
                'suspended' => $suspendedTenants,
                'cancelled' => $cancelledTenants,
                'total_users' => $totalUsers,
            ],
            'financials' => [
                'active_subscriptions' => $activeSubsCount,
                'mrr' => $mrr,
                'arr' => $arr,
                'total_collections' => $totalCollections,
                'retail_usage_billed' => round($totalRetailUsage, 2),
                'wholesale_provider_cost' => round($totalProviderCost, 2),
                'gross_profit' => $grossProfit,
                'gross_margin_percent' => $grossMarginPercent,
            ],
            'usage_volumes' => [
                'ai_tokens' => $aiTokens,
                'calling_minutes' => round($callingMinutes, 1),
                'whatsapp_messages' => $whatsappMessages,
                'sms_segments' => $smsSegments,
                'email_volume' => $emailVolume,
            ],
            'infrastructure' => [
                'failed_integrations' => $failedIntegrationsCount,
                'failed_jobs' => $failedJobsCount,
                'system_health' => [
                    'database' => $dbStatus,
                    'cache' => $cacheStatus,
                    'storage' => 'healthy',
                    'overall' => ($dbStatus === 'healthy' && $cacheStatus === 'healthy' && $failedJobsCount === 0) ? 'healthy' : 'degraded',
                ],
            ],
        ];
    }

    /**
     * Get Paginated Tenants Roster with Filters
     */
    public function getTenants(array $filters = [], int $perPage = 20)
    {
        $query = Tenant::with(['subscription.plan', 'wallet'])
            ->withCount('users');

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%")
                    ->orWhere('domain', 'like', "%{$s}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['tier'])) {
            $query->where('subscription_tier', $filters['tier']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get Full Tenant Detail with Metrics and Limit Overrides
     */
    public function getTenantDetails(int $tenantId): array
    {
        $tenant = Tenant::with([
            'subscription.plan',
            'wallet',
            'users' => fn($q) => $q->limit(10),
            'costProtectionRule',
        ])->findOrFail($tenantId);

        $recentLedger = CreditLedgerEntry::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentInvoices = SubscriptionInvoice::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $supportSessions = PlatformSupportAccessLog::where('tenant_id', $tenantId)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'tenant' => $tenant,
            'recent_ledger' => $recentLedger,
            'recent_invoices' => $recentInvoices,
            'support_sessions' => $supportSessions,
        ];
    }

    /**
     * Update Tenant Operational Status (Active, Suspended, Cancelled)
     */
    public function updateTenantStatus(int $tenantId, string $status, string $reason, User $admin, ?string $ip = null, ?string $userAgent = null): Tenant
    {
        if (!in_array($status, ['active', 'suspended', 'cancelled'])) {
            throw new \InvalidArgumentException('Invalid tenant status: ' . $status);
        }

        $tenant = Tenant::findOrFail($tenantId);
        $oldStatus = $tenant->status;
        $tenant->status = $status;
        $tenant->save();

        // Update active subscription state if suspending or cancelling
        if ($tenant->subscription) {
            if ($status === 'suspended') {
                $tenant->subscription->update(['status' => 'past_due']);
            } elseif ($status === 'cancelled') {
                $tenant->subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => Carbon::now(),
                    'cancellation_reason' => $reason,
                ]);
            } elseif ($status === 'active' && $oldStatus === 'suspended') {
                $tenant->subscription->update(['status' => 'active']);
            }
        }

        $this->logAudit(
            $admin,
            "tenant.{$status}",
            'Tenant',
            (string) $tenantId,
            [
                'previous_status' => $oldStatus,
                'new_status' => $status,
                'reason' => $reason,
            ],
            $ip,
            $userAgent
        );

        return $tenant;
    }

    /**
     * Change Tenant Subscription Plan from Platform Admin
     */
    public function changeTenantPlan(int $tenantId, int $newPlanId, bool $prorate, User $admin, ?string $ip = null, ?string $userAgent = null): TenantSubscription
    {
        $plan = SubscriptionPlan::findOrFail($newPlanId);
        $sub = $this->billingService->subscribePlan($tenantId, $newPlanId, 'monthly', $prorate);

        $this->logAudit(
            $admin,
            'tenant.plan_change',
            'Tenant',
            (string) $tenantId,
            [
                'plan_id' => $newPlanId,
                'plan_name' => $plan->name,
                'prorated' => $prorate,
            ],
            $ip,
            $userAgent
        );

        return $sub;
    }

    /**
     * Update Custom Platform Limits Overrides for Tenant
     */
    public function updateTenantLimits(int $tenantId, array $limits, User $admin, ?string $ip = null, ?string $userAgent = null): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);
        $settings = $tenant->settings ?? [];
        $settings['limits_override'] = array_merge($settings['limits_override'] ?? [], $limits);
        $tenant->settings = $settings;
        $tenant->save();

        $this->logAudit(
            $admin,
            'tenant.limits_override',
            'Tenant',
            (string) $tenantId,
            ['limits_override' => $limits],
            $ip,
            $userAgent
        );

        return $tenant;
    }

    /**
     * Create Scoped Breakglass Support Session (Restricted, Justified, Logged)
     */
    public function createBreakglassSupportSession(int $tenantId, string $justificationReason, User $admin, int $durationMinutes = 60, ?string $ip = null, ?string $userAgent = null): array
    {
        if (empty(trim($justificationReason))) {
            throw new \InvalidArgumentException('A mandatory justification reason is required to access customer tenant data.');
        }

        $tenant = Tenant::findOrFail($tenantId);
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $session = PlatformSupportAccessLog::create([
            'user_id' => $admin->id,
            'tenant_id' => $tenantId,
            'justification_reason' => $justificationReason,
            'token_hash' => $tokenHash,
            'status' => 'active',
            'expires_at' => Carbon::now()->addMinutes($durationMinutes),
            'actions_performed' => [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'initiated_at' => Carbon::now()->toIso8601String(),
            ],
        ]);

        $this->logAudit(
            $admin,
            'support.breakglass_start',
            'Tenant',
            (string) $tenantId,
            [
                'session_id' => $session->id,
                'justification' => $justificationReason,
                'expires_at' => $session->expires_at->toIso8601String(),
            ],
            $ip,
            $userAgent
        );

        return [
            'session_id' => $session->id,
            'tenant_id' => $tenantId,
            'tenant_name' => $tenant->name,
            'access_token' => $token,
            'expires_at' => $session->expires_at->toIso8601String(),
            'justification_reason' => $justificationReason,
        ];
    }

    /**
     * Revoke Active Breakglass Support Session
     */
    public function revokeBreakglassSupportSession(int $sessionId, User $admin, ?string $ip = null, ?string $userAgent = null): bool
    {
        $session = PlatformSupportAccessLog::findOrFail($sessionId);
        $session->update([
            'status' => 'revoked',
            'revoked_at' => Carbon::now(),
        ]);

        $this->logAudit(
            $admin,
            'support.breakglass_revoke',
            'PlatformSupportAccessLog',
            (string) $sessionId,
            ['tenant_id' => $session->tenant_id],
            $ip,
            $userAgent
        );

        return true;
    }

    /**
     * Record Platform Security Audit Log
     */
    public function logAudit(User $admin, string $action, ?string $targetType = null, ?string $targetId = null, array $details = [], ?string $ip = null, ?string $userAgent = null): PlatformAuditLog
    {
        return PlatformAuditLog::create([
            'user_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'details' => $details,
            'created_at' => Carbon::now(),
        ]);
    }
}
