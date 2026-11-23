<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AuditSecurity\Models\LoginHistory;
use App\Domains\Tenancy\Models\SystemSetting;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Domains\Tenancy\Models\WorkspaceInvitation;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    use ApiResponse;

    /**
     * List all workspaces the user belongs to
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Platform admins can view all or their own
        if ($user->is_platform_admin || $user->hasRole('Platform Super Admin')) {
            $workspaces = Tenant::with('systemSetting')->get()->map(function ($tenant) use ($user) {
                return [
                    'id' => (string) $tenant->id,
                    'uuid' => $tenant->uuid,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'subscriptionTier' => $tenant->subscription_tier,
                    'status' => $tenant->status,
                    'role' => 'Platform Super Admin',
                    'isOwner' => true,
                    'isCurrent' => $user->current_tenant_id === $tenant->id,
                    'logoUrl' => $tenant->systemSetting?->logo_url,
                ];
            });

            return $this->successResponse($workspaces, 'Workspaces retrieved.');
        }

        $memberships = TenantUser::with(['tenant.systemSetting'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $workspaces = $memberships->map(function ($m) use ($user) {
            $t = $m->tenant;
            return [
                'id' => (string) $t->id,
                'uuid' => $t->uuid,
                'name' => $t->name,
                'slug' => $t->slug,
                'subscriptionTier' => $t->subscription_tier,
                'status' => $t->status,
                'role' => $m->role,
                'isOwner' => (bool) $m->is_owner,
                'isCurrent' => $user->current_tenant_id === $t->id,
                'logoUrl' => $t->systemSetting?->logo_url,
            ];
        });

        return $this->successResponse($workspaces, 'Workspaces retrieved.');
    }

    /**
     * Get current active workspace details, branding & settings
     */
    public function current(Request $request): JsonResponse
    {
        $tenant = TenantContext::getTenant();

        if (!$tenant) {
            return $this->errorResponse('No active workspace context established.', 404);
        }

        $user = $request->user();
        $membership = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        $settings = SystemSetting::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'company_name' => $tenant->name,
                'company_email' => 'ops@' . ($tenant->slug ?? 'acme') . '.com',
                'company_phone' => '+1 (555) 019-2834',
                'tax_rate' => 15.00,
                'currency' => 'USD',
                'theme' => 'dark',
            ]
        );

        $role = $user->is_platform_admin ? 'Platform Super Admin' : ($membership?->role ?? 'Employee');

        return $this->successResponse([
            'workspace' => [
                'id' => (string) $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'subscriptionTier' => $tenant->subscription_tier,
                'status' => $tenant->status,
                'role' => $role,
                'isOwner' => (bool) ($membership?->is_owner ?? $user->is_platform_admin),
            ],
            'settings' => [
                'businessName' => $settings->company_name,
                'companyEmail' => $settings->company_email,
                'companyPhone' => $settings->company_phone,
                'logoUrl' => $settings->logo_url,
                'address' => $settings->address,
                'gstinTaxId' => $settings->gstin_tax_id,
                'timezone' => $settings->timezone ?? 'UTC',
                'locale' => $settings->locale ?? 'en',
                'currency' => $settings->currency ?? 'USD',
                'taxRate' => (float) $settings->tax_rate,
                'dateFormat' => $settings->date_format ?? 'YYYY-MM-DD',
                'timeFormat' => $settings->time_format ?? '24h',
                'workingDays' => $settings->working_days ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                'businessHoursStart' => $settings->business_hours_start ?? '09:00',
                'businessHoursEnd' => $settings->business_hours_end ?? '18:00',
                'defaultCountry' => $settings->default_country ?? 'US',
                'brandPrimaryColor' => $settings->brand_primary_color ?? '#10b981',
                'brandSecondaryColor' => $settings->brand_secondary_color ?? '#0f172a',
                'theme' => $settings->theme ?? 'dark',
            ],
        ], 'Active workspace profile.');
    }

    /**
     * Switch active workspace
     */
    public function switchWorkspace(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_id' => 'required',
        ]);

        $user = $request->user();
        $targetId = $request->workspace_id;

        $tenant = Tenant::where('id', $targetId)
            ->orWhere('uuid', $targetId)
            ->first();

        if (!$tenant) {
            return $this->errorResponse('Target workspace not found.', 404);
        }

        // Validate membership if not platform admin
        if (!$user->is_platform_admin && !$user->hasRole('Platform Super Admin')) {
            $membership = TenantUser::where('tenant_id', $tenant->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if (!$membership) {
                return $this->errorResponse('Unauthorized: You do not belong to this workspace.', 403);
            }
        }

        $user->update(['current_tenant_id' => $tenant->id]);
        TenantContext::setTenant($tenant);

        return $this->successResponse([
            'activeWorkspaceId' => (string) $tenant->id,
            'activeWorkspaceUuid' => $tenant->uuid,
            'name' => $tenant->name,
        ], "Switched active workspace to {$tenant->name}.");
    }

    /**
     * Register a new business workspace
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subscription_tier' => 'nullable|in:starter,pro,enterprise',
        ]);

        $user = $request->user();

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . Str::random(4),
            'subscription_tier' => $request->subscription_tier ?? 'starter',
            'status' => 'active',
        ]);

        // Establish user as Business Owner
        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'Business Owner',
            'status' => 'active',
            'is_owner' => true,
            'joined_at' => now(),
        ]);

        // Set as current workspace
        $user->update(['current_tenant_id' => $tenant->id]);
        TenantContext::setTenant($tenant);

        // Initialize default settings
        SystemSetting::create([
            'tenant_id' => $tenant->id,
            'company_name' => $tenant->name,
            'company_email' => $user->email,
            'company_phone' => '+1 (555) 019-2834',
            'tax_rate' => 15.00,
            'currency' => 'USD',
            'theme' => 'dark',
        ]);

        return $this->successResponse([
            'id' => (string) $tenant->id,
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'role' => 'Business Owner',
        ], 'Business workspace registered successfully.', 201);
    }

    /**
     * Update Business Workspace Settings (Branding, GSTIN, Timezone, Hours)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $tenant = TenantContext::getTenant();

        if (!$tenant) {
            return $this->errorResponse('No active workspace selected.', 404);
        }

        $validated = $request->validate([
            'businessName' => 'sometimes|string|max:255',
            'companyEmail' => 'sometimes|nullable|email|max:255',
            'companyPhone' => 'sometimes|nullable|string|max:50',
            'logoUrl' => 'sometimes|nullable|string|max:1000',
            'address' => 'sometimes|nullable|string',
            'gstinTaxId' => 'sometimes|nullable|string|max:50',
            'timezone' => 'sometimes|string|max:50',
            'locale' => 'sometimes|string|max:10',
            'currency' => 'sometimes|string|max:10',
            'taxRate' => 'sometimes|numeric|min:0',
            'dateFormat' => 'sometimes|string|max:20',
            'timeFormat' => 'sometimes|string|max:10',
            'workingDays' => 'sometimes|array',
            'businessHoursStart' => 'sometimes|string|max:10',
            'businessHoursEnd' => 'sometimes|string|max:10',
            'defaultCountry' => 'sometimes|string|max:10',
            'brandPrimaryColor' => 'sometimes|string|max:20',
            'brandSecondaryColor' => 'sometimes|string|max:20',
            'theme' => 'sometimes|in:light,dark',
        ]);

        $settings = SystemSetting::firstOrCreate(['tenant_id' => $tenant->id]);

        $data = [];
        if (isset($validated['businessName'])) {
            $data['company_name'] = $validated['businessName'];
            $tenant->update(['name' => $validated['businessName']]);
        }
        if (isset($validated['companyEmail'])) $data['company_email'] = $validated['companyEmail'];
        if (isset($validated['companyPhone'])) $data['company_phone'] = $validated['companyPhone'];
        if (isset($validated['logoUrl'])) $data['logo_url'] = $validated['logoUrl'];
        if (isset($validated['address'])) $data['address'] = $validated['address'];
        if (isset($validated['gstinTaxId'])) $data['gstin_tax_id'] = $validated['gstinTaxId'];
        if (isset($validated['timezone'])) $data['timezone'] = $validated['timezone'];
        if (isset($validated['locale'])) $data['locale'] = $validated['locale'];
        if (isset($validated['currency'])) $data['currency'] = $validated['currency'];
        if (isset($validated['taxRate'])) $data['tax_rate'] = $validated['taxRate'];
        if (isset($validated['dateFormat'])) $data['date_format'] = $validated['dateFormat'];
        if (isset($validated['timeFormat'])) $data['time_format'] = $validated['timeFormat'];
        if (isset($validated['workingDays'])) $data['working_days'] = $validated['workingDays'];
        if (isset($validated['businessHoursStart'])) $data['business_hours_start'] = $validated['businessHoursStart'];
        if (isset($validated['businessHoursEnd'])) $data['business_hours_end'] = $validated['businessHoursEnd'];
        if (isset($validated['defaultCountry'])) $data['default_country'] = $validated['defaultCountry'];
        if (isset($validated['brandPrimaryColor'])) $data['brand_primary_color'] = $validated['brandPrimaryColor'];
        if (isset($validated['brandSecondaryColor'])) $data['brand_secondary_color'] = $validated['brandSecondaryColor'];
        if (isset($validated['theme'])) $data['theme'] = $validated['theme'];

        $settings->update($data);

        return $this->successResponse($settings, 'Business settings updated successfully.');
    }

    /**
     * List team members in the active workspace
     */
    public function members(Request $request): JsonResponse
    {
        $tenant = TenantContext::getTenant();

        if (!$tenant) {
            return $this->errorResponse('No active workspace selected.', 404);
        }

        $members = TenantUser::with('user')
            ->where('tenant_id', $tenant->id)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => (string) $m->id,
                    'userId' => (string) $m->user_id,
                    'name' => $m->user?->name,
                    'email' => $m->user?->email,
                    'role' => $m->role,
                    'status' => $m->status,
                    'isOwner' => (bool) $m->is_owner,
                    'joinedAt' => $m->joined_at?->toIso8601String(),
                    'avatar' => $m->user?->avatar,
                ];
            });

        return $this->successResponse($members, 'Workspace members.');
    }

    /**
     * Invite a new team member
     */
    public function inviteMember(Request $request): JsonResponse
    {
        $tenant = TenantContext::getTenant();

        if (!$tenant) {
            return $this->errorResponse('No active workspace selected.', 404);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|string|max:50',
        ]);

        $user = $request->user();

        // Check if already an active member
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser) {
            $isMember = TenantUser::where('tenant_id', $tenant->id)
                ->where('user_id', $existingUser->id)
                ->where('status', 'active')
                ->exists();

            if ($isMember) {
                return $this->errorResponse('User is already an active member of this workspace.', 422);
            }
        }

        $token = Str::random(48);

        $invitation = WorkspaceInvitation::create([
            'tenant_id' => $tenant->id,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => $token,
            'status' => 'pending',
            'expires_at' => now()->addDays(7),
            'created_by' => $user->id,
        ]);

        return $this->successResponse([
            'invitationId' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'token' => $invitation->token,
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'inviteUrl' => url("/invitations/accept/{$token}"),
        ], 'Invitation generated successfully.', 201);
    }

    /**
     * Accept a workspace invitation
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::with('tenant')
            ->where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$invitation || $invitation->isExpired()) {
            return $this->errorResponse('Invitation is invalid or has expired.', 404);
        }

        $user = $request->user();

        // If not logged in, user must register or login first with matching email
        if (!$user) {
            return $this->errorResponse('Please authenticate to accept this workspace invitation.', 401);
        }

        // Add user to tenant_users
        TenantUser::updateOrCreate(
            [
                'tenant_id' => $invitation->tenant_id,
                'user_id' => $user->id,
            ],
            [
                'role' => $invitation->role,
                'status' => 'active',
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $invitation->update(['status' => 'accepted']);
        $user->update(['current_tenant_id' => $invitation->tenant_id]);

        return $this->successResponse([
            'workspaceId' => (string) $invitation->tenant_id,
            'workspaceName' => $invitation->tenant?->name,
            'role' => $invitation->role,
        ], 'Invitation accepted. Workspace membership activated.');
    }

    /**
     * User login history
     */
    public function loginHistory(Request $request): JsonResponse
    {
        $histories = LoginHistory::where('user_id', $request->user()->id)
            ->latest('created_at')
            ->take(15)
            ->get();

        return $this->successResponse($histories, 'Login history.');
    }
}
