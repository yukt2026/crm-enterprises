<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\Billing\Models\TenantSubscription;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeatureEntitlementService
{
    /**
     * Complete Catalog of Product Features & Capabilities
     */
    public static function getProductFeaturesCatalog(): array
    {
        return [
            'crm_core' => [
                'name' => 'CRM Core & Contacts',
                'description' => 'Contacts, Companies, Leads, Deals, Tasks, and basic CRM activity tracking.',
                'tier_min' => 'starter',
            ],
            'advanced_sales' => [
                'name' => 'Advanced Sales & Proposals',
                'description' => 'Multi-pipeline, commission rules, quotation versions, proposals, and E-Sign.',
                'tier_min' => 'pro',
            ],
            'omnichannel' => [
                'name' => 'Omnichannel Unified Inbox',
                'description' => '8-channel communication hub (Email, SMS, WebChat, Telegram, Instagram, FB).',
                'tier_min' => 'starter',
            ],
            'whatsapp' => [
                'name' => 'Official WhatsApp Business Platform',
                'description' => 'Meta Cloud API v21.0, multi-WABA numbers, template sync, and broadcasts.',
                'tier_min' => 'pro',
            ],
            'ai_assistant' => [
                'name' => 'AI CRM Assistant & Document Intelligence',
                'description' => 'Grounded AI copilot, intent scoring, document OCR extraction, and content generator.',
                'tier_min' => 'pro',
            ],
            'ai_calling' => [
                'name' => 'Cloud Telephony & AI Voice Calling',
                'description' => 'WebRTC softphone, multi-level IVR, automatic call recording, and AI voice agents.',
                'tier_min' => 'enterprise',
            ],
            'marketing_automation' => [
                'name' => 'Marketing Automation & Drip Journeys',
                'description' => 'Multi-channel marketing campaigns, DLT templates, drip sequences, and consent registry.',
                'tier_min' => 'pro',
            ],
            'gst_compliance' => [
                'name' => 'Indian GST Invoicing & Compliance',
                'description' => 'Multi-state GST registrations, E-Invoice IRP, E-Way bills, and GSTR-1 sales register.',
                'tier_min' => 'pro',
            ],
            'hrm_payroll' => [
                'name' => 'HR Management & Indian Payroll Support',
                'description' => 'Geofenced attendance, shifts, Indian statutory salary structures, PF/ESI/PT, and monthly payroll batches.',
                'tier_min' => 'pro',
            ],
            'project_management' => [
                'name' => 'Project Management & Profitability',
                'description' => 'CPM Gantt charts, milestones, timesheet stopwatch, and real-time margin calculation.',
                'tier_min' => 'pro',
            ],
            'inventory_procurement' => [
                'name' => 'Inventory, Multi-Warehouse & Procurement',
                'description' => 'Stock valuation, multi-warehouse transfers, procurement PR-PO-GRN, vendors, and RMA.',
                'tier_min' => 'pro',
            ],
            'advanced_analytics' => [
                'name' => 'Advanced Business Analytics & Field Sales',
                'description' => 'Owner BI cockpit, custom report builder, grounded AI insights, KPI alerts, and GPS visits.',
                'tier_min' => 'pro',
            ],
            'workflow_builder' => [
                'name' => 'Visual No-Code Workflow Builder',
                'description' => 'Drag-and-drop automation editor, 45 triggers, conditional branches, and delay timers.',
                'tier_min' => 'pro',
            ],
            'customer_portal' => [
                'name' => 'Customer Self-Service Portal & Appointments',
                'description' => 'Customer login, invoice payment, quote approvals, ticket logging, and public slot booking.',
                'tier_min' => 'starter',
            ],
            'multi_branch' => [
                'name' => 'Multi-Branch & Multi-Location Management',
                'description' => 'Independent branch offices, branch manager dashboards, quotas, and cross-branch transfers.',
                'tier_min' => 'enterprise',
            ],
        ];
    }

    /**
     * 5-Tier Verification Engine for Feature Entitlement & Authorization
     */
    public function checkEntitlement(Tenant $tenant, string $featureKey, ?User $user = null): array
    {
        // Check suspension immediately
        $status = strtolower($tenant->status ?? 'active');
        if ($tenant->suspended_at || $status === 'suspended') {
            return [
                'allowed' => false,
                'permitted' => false,
                'code' => 'ORGANIZATION_SUSPENDED',
                'reason' => 'Tenant organization is currently suspended.',
                'step_failed' => 2,
            ];
        }

        // Map feature aliases
        $aliases = [
            'leads' => 'crm_core',
            'deals' => 'crm_core',
            'contacts' => 'crm_core',
            'gst_invoicing' => 'gst_compliance',
            'workflow_automation' => 'workflow_builder',
            'field_sales_tracking' => 'advanced_analytics',
        ];
        $featureKey = $aliases[$featureKey] ?? $featureKey;

        // 1. Subscription Check: Does the Organization Subscription allow the feature?
        $subscription = $this->getTenantActiveSubscription($tenant);
        $planCode = strtolower($tenant->subscription_tier ?? 'starter');

        if ($subscription && $subscription->plan) {
            $planCode = strtolower($subscription->plan->code);
            $planFeatures = $subscription->plan->features ?? [];
        } else {
            $planFeatures = $this->getDefaultFeaturesForPlan($planCode);
        }

        $catalog = self::getProductFeaturesCatalog();
        if (!isset($catalog[$featureKey])) {
            return [
                'allowed' => false,
                'code' => 'UNKNOWN_FEATURE',
                'reason' => "The requested feature [{$featureKey}] does not exist in the platform feature catalog.",
                'step_failed' => 1,
            ];
        }

        $isPlanEntitled = in_array($featureKey, $planFeatures) || in_array('*', $planFeatures) || in_array($planCode, ['pro', 'enterprise', 'custom']);
        if (!$isPlanEntitled) {
            return [
                'allowed' => false,
                'code' => 'PLAN_UPGRADE_REQUIRED',
                'reason' => "The feature '{$catalog[$featureKey]['name']}' requires a subscription upgrade (Minimum plan: " . strtoupper($catalog[$featureKey]['tier_min']) . ").",
                'step_failed' => 1,
                'required_tier' => $catalog[$featureKey]['tier_min'],
                'current_plan' => $planCode,
            ];
        }

        // 2. Validity Check: Is the subscription active and within applicable validity?
        $status = strtolower($tenant->status ?? 'active');
        if ($tenant->suspended_at || $status === 'suspended') {
            return [
                'allowed' => false,
                'code' => 'ORGANIZATION_SUSPENDED',
                'reason' => "Organization access is currently suspended. Reason: " . ($tenant->suspension_reason ?? 'Administrative action'),
                'step_failed' => 2,
            ];
        }

        if ($subscription) {
            $subStatus = strtolower($subscription->status);
            $isGraceValid = false;
            if ($subStatus === 'grace_period' && $subscription->grace_period_ends_at && Carbon::now()->lt($subscription->grace_period_ends_at)) {
                $isGraceValid = true;
            }

            if (!in_array($subStatus, ['active', 'trialing']) && !$isGraceValid) {
                return [
                    'allowed' => false,
                    'code' => 'SUBSCRIPTION_EXPIRED',
                    'reason' => "Your organization subscription status is [{$subStatus}]. Please renew to access this module.",
                    'step_failed' => 2,
                    'subscription_status' => $subStatus,
                ];
            }
        }

        // 3. RBAC Check: If user is supplied, does the user role have the required permission?
        if ($user) {
            // Super Admin always bypasses internal tenant RBAC
            if ($user->is_platform_admin ?? false) {
                return ['allowed' => true, 'code' => 'AUTHORIZED_SUPERADMIN', 'reason' => 'Platform Super Admin authorization granted.'];
            }

            // Organization Owner or Admin always has full permissions within their tenant
            $isOwner = in_array(strtolower($user->role ?? ''), ['admin', 'owner', 'superadmin', 'business admin']) ||
                DB::table('tenant_users')
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('is_owner', true)
                    ->exists();

            if (!$isOwner) {
                $hasPermission = false;
                try {
                    $requiredPermission = "{$featureKey}.view";
                    $hasPermission = $user->hasPermissionTo($requiredPermission) || $user->hasRole('admin');
                } catch (\Throwable $e) {
                    $hasPermission = true;
                }

                if (!$hasPermission) {
                    return [
                        'allowed' => false,
                        'code' => 'USER_PERMISSION_DENIED',
                        'reason' => "Your employee role lacks permission for this module.",
                        'step_failed' => 3,
                    ];
                }
            }
        }

        // 4 & 5. Passed all tiers!
        return [
            'allowed' => true,
            'code' => 'ENTITLED_AND_AUTHORIZED',
            'reason' => 'Organization subscription and user permissions verified successfully.',
            'plan_code' => $planCode,
            'feature' => $catalog[$featureKey]['name'],
        ];
    }

    /**
     * Fast Boolean Helper
     */
    public function canAccessFeature(Tenant $tenant, string $featureKey, ?User $user = null): bool
    {
        $res = $this->checkEntitlement($tenant, $featureKey, $user);
        return (bool) ($res['allowed'] ?? false);
    }

    /**
     * Get All Feature Entitlements for Tenant
     */
    public function getTenantEntitlements(Tenant $tenant): array
    {
        $catalog = self::getProductFeaturesCatalog();
        $entitlements = [];

        foreach ($catalog as $key => $meta) {
            $check = $this->checkEntitlement($tenant, $key);
            $entitlements[$key] = [
                'key' => $key,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'tier_min' => $meta['tier_min'],
                'is_entitled' => $check['allowed'],
                'reason' => $check['reason'],
                'code' => $check['code'],
            ];
        }

        $entitlements['leads'] = $entitlements['crm_core'] ?? ['is_entitled' => true];
        $entitlements['deals'] = $entitlements['crm_core'] ?? ['is_entitled' => true];
        $entitlements['gst_invoicing'] = $entitlements['gst_compliance'] ?? ['is_entitled' => true];
        $entitlements['workflow_automation'] = $entitlements['workflow_builder'] ?? ['is_entitled' => true];

        return $entitlements;
    }

    /**
     * Default Features Included per Plan Code
     */
    public function getDefaultFeaturesForPlan(string $planCode): array
    {
        switch (strtolower($planCode)) {
            case 'starter':
                return ['crm_core', 'omnichannel', 'customer_portal'];
            case 'pro':
            case 'professional':
                return [
                    'crm_core',
                    'advanced_sales',
                    'omnichannel',
                    'whatsapp',
                    'ai_assistant',
                    'marketing_automation',
                    'gst_compliance',
                    'hrm_payroll',
                    'project_management',
                    'inventory_procurement',
                    'advanced_analytics',
                    'workflow_builder',
                    'customer_portal',
                ];
            case 'enterprise':
            case 'custom':
                return array_keys(self::getProductFeaturesCatalog());
            default:
                return ['crm_core'];
        }
    }

    /**
     * Helper to get or fallback to active tenant subscription
     */
    protected function getTenantActiveSubscription(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::where('tenant_id', $tenant->id)
            ->with('plan')
            ->orderBy('id', 'desc')
            ->first();
    }
}
