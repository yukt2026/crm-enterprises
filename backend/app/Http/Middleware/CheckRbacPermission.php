<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Services\RbacService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRbacPermission
{
    public function __construct(
        protected RbacService $rbacService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'status_code' => 401,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$this->rbacService->can($user, $module, $action)) {
            return response()->json([
                'success' => false,
                'status_code' => 403,
                'message' => "Unauthorized access: Missing required permission for module '{$module}' and action '{$action}'.",
                'required_permission' => [
                    'module' => $module,
                    'action' => $action,
                ],
            ], 403);
        }

        return $next($request);
    }
}
