<?php

namespace App\Http\Middleware;

use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Domains\Tenancy\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::clear();

        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $isPlatformAdmin = (bool) ($user->is_platform_admin || $user->hasRole('Platform Super Admin'));
        TenantContext::setPlatformAdmin($isPlatformAdmin);

        // 1. Check for requested workspace via header or fallback to current_tenant_id
        $requestedWorkspace = $request->header('X-Workspace-Id');

        $tenant = null;

        if ($requestedWorkspace) {
            $tenant = Tenant::where('id', $requestedWorkspace)
                ->orWhere('uuid', $requestedWorkspace)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'status_code' => 404,
                    'message' => 'Requested workspace does not exist.',
                ], 404);
            }
        } elseif ($user->current_tenant_id) {
            $tenant = Tenant::find($user->current_tenant_id);
        } elseif ($user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
        }

        // If still null, try finding user's first active workspace membership
        if (!$tenant) {
            $firstMembership = TenantUser::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($firstMembership) {
                $tenant = Tenant::find($firstMembership->tenant_id);
            }
        }

        // 2. Validate membership for non-platform admins
        if ($tenant) {
            if (!$isPlatformAdmin) {
                $hasAccess = TenantUser::where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->exists();

                if (!$hasAccess) {
                    return response()->json([
                        'success' => false,
                        'status_code' => 403,
                        'message' => 'Unauthorized workspace access: cross-tenant access is strictly prohibited.',
                    ], 403);
                }
            }

            // Set active tenant in context
            TenantContext::setTenant($tenant);

            // Sync current_tenant_id on user record if changed
            if ($user->current_tenant_id !== $tenant->id) {
                $user->update(['current_tenant_id' => $tenant->id]);
            }
        }

        return $next($request);
    }
}
