<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /**
     * Handle an incoming request.
     * Restricts endpoint strictly to Platform Super Administrators.
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $user = $request->user();

        // 1. Must be authenticated and flagged as platform admin
        if (!$user || !$user->is_platform_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. This endpoint is strictly restricted to Platform Super Administrators.',
            ], 403);
        }

        // 2. Role-based sub-check if a specific platform role capability is required
        if ($requiredRole) {
            $userPlatformRole = strtolower((string) ($user->platform_role ?: $user->role));
            $isSuper = in_array($userPlatformRole, ['super_admin', 'super admin', 'platform super admin']);

            if (!$isSuper) {
                $allowed = match ($requiredRole) {
                    'billing' => in_array($userPlatformRole, ['billing_admin', 'billing admin']),
                    'provider' => in_array($userPlatformRole, ['platform_ops', 'platform ops', 'provider_admin']),
                    'ops' => in_array($userPlatformRole, ['platform_ops', 'platform ops']),
                    'support' => in_array($userPlatformRole, ['platform_support', 'platform support']),
                    default => false,
                };

                if (!$allowed) {
                    return response()->json([
                        'success' => false,
                        'message' => "Forbidden. Your platform role does not possess the '{$requiredRole}' administrative capability.",
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
