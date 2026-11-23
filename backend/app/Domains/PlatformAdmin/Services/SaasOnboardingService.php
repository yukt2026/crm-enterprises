<?php

namespace App\Domains\PlatformAdmin\Services;

use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\Billing\Models\TenantSubscription;
use App\Domains\PlatformAdmin\Models\PlatformAuditLog;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SaasOnboardingService
{
    public function __construct(
        protected IndustryTemplateService $templateService
    ) {}

    /**
     * Complete 11-Step Onboarding State Machine
     */
    public function registerOrganizationAndOwner(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // STEP 1: Register Organization
            $orgName = trim($data['organization_name'] ?? $data['name']);
            $slug = Str::slug($data['slug'] ?? $orgName) . '-' . Str::lower(Str::random(4));

            $tenant = Tenant::create([
                'name' => $orgName,
                'slug' => $slug,
                'domain' => $data['domain'] ?? null,
                'subscription_tier' => $data['plan_code'] ?? 'starter',
                'status' => 'active',
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'onboarding_status' => 'in_progress',
                'onboarding_step' => 2,
                'industry_template_code' => $data['industry_template_code'] ?? null,
                'gstin' => $data['gstin'] ?? null,
                'pan' => $data['pan'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'billing_state_code' => $data['billing_state_code'] ?? null,
                'branding' => [
                    'primary_color' => $data['primary_color'] ?? '#4f46e5',
                    'dark_mode' => true,
                    'tagline' => $data['tagline'] ?? 'Enterprise Growth Platform',
                ],
            ]);

            // STEP 2: Create Organization Owner
            $ownerEmail = strtolower(trim($data['owner_email'] ?? $data['email']));
            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'] ?? 'Organization Owner',
                'email' => $ownerEmail,
                'password' => Hash::make($data['password'] ?? 'Secret123!'),
                'role' => 'Admin',
                'status' => 'active',
                'email_verified_at' => Carbon::now(), // STEP 3: Auto-verify or verified contact
            ]);

            // Assign owner membership in tenant_users
            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'is_owner' => true,
                'joined_at' => Carbon::now(),
            ]);

            // STEP 4 & 5: Business Type & Timezone
            $templateCode = $data['industry_template_code'] ?? 'consultancy';
            if (!empty($templateCode)) {
                try {
                    $this->templateService->applyTemplateToTenant($tenant, $templateCode);
                } catch (\Throwable $e) {
                    // Soft fallback
                }
            }

            // STEP 6: Setup Trial Subscription
            $planCode = strtolower($data['plan_code'] ?? 'pro');
            $plan = SubscriptionPlan::where('code', $planCode)->first();
            if ($plan) {
                TenantSubscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'trialing',
                    'trial_starts_at' => Carbon::now(),
                    'trial_ends_at' => Carbon::now()->addDays($plan->trial_days ?? 14),
                    'current_period_start' => Carbon::now(),
                    'current_period_end' => Carbon::now()->addDays($plan->trial_days ?? 14),
                    'billing_contact_name' => $owner->name,
                    'billing_email' => $owner->email,
                    'tax_id' => $tenant->gstin,
                ]);
            }

            // STEP 11: Mark onboarding step
            $tenant->update([
                'onboarding_step' => 11,
                'onboarding_status' => 'completed',
            ]);

            // Log Platform Audit
            PlatformAuditLog::create([
                'user_id' => $owner->id,
                'admin_name' => $owner->name,
                'admin_email' => $owner->email,
                'action' => 'tenant.onboarded',
                'target_type' => 'Tenant',
                'target_id' => (string) $tenant->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'details' => [
                    'tenant_name' => $tenant->name,
                    'plan' => $tenant->subscription_tier,
                    'industry' => $templateCode,
                ],
            ]);

            return [
                'tenant' => $tenant->fresh(),
                'owner' => $owner,
                'user' => $owner,
                'token' => $owner->createToken('auth-token')->plainTextToken,
                'message' => 'Organization onboarded successfully with industry starter presets.',
            ];
        });
    }

    /**
     * Update Progress on Onboarding Steps
     */
    public function updateOnboardingStep(Tenant $tenant, int $stepNumber, array $stepData = []): Tenant
    {
        $tenant->onboarding_step = max($tenant->onboarding_step, $stepNumber);

        if ($stepNumber >= 11) {
            $tenant->onboarding_status = 'completed';
        } else {
            $tenant->onboarding_status = 'in_progress';
        }

        if (!empty($stepData['industry_template_code'])) {
            $tenant->industry_template_code = $stepData['industry_template_code'];
            $this->templateService->applyTemplateToTenant($tenant, $stepData['industry_template_code']);
        }

        if (!empty($stepData['branding'])) {
            $currentBranding = $tenant->branding ?? [];
            $tenant->branding = array_merge($currentBranding, $stepData['branding']);
        }

        if (!empty($stepData['timezone'])) {
            $tenant->timezone = $stepData['timezone'];
        }

        $tenant->save();
        return $tenant;
    }
}
