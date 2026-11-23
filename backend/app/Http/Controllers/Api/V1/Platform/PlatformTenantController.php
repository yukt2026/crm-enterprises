<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\PlatformAdmin\Services\PlatformAdminService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformTenantController extends Controller
{
    public function __construct(
        protected PlatformAdminService $adminService
    ) {}

    /**
     * GET /api/v1/platform/tenants
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'tier']);
        $perPage = (int) $request->input('per_page', 20);

        $tenants = $this->adminService->getTenants($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $tenants->items(),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'total' => $tenants->total(),
            ],
            'message' => 'Tenants directory retrieved successfully',
        ], 200);
    }

    /**
     * GET /api/v1/platform/tenants/{id}
     */
    public function show(int $id): JsonResponse
    {
        $details = $this->adminService->getTenantDetails($id);
        return response()->json([
            'success' => true,
            'data' => $details,
            'message' => 'Tenant details retrieved successfully',
        ], 200);
    }

    /**
     * POST /api/v1/platform/tenants/{id}/status
     * Activate, suspend, or restore tenant
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,suspended,cancelled',
            'reason' => 'required|string|max:500',
        ]);

        $tenant = $this->adminService->updateTenantStatus(
            $id,
            $validated['status'],
            $validated['reason'],
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => "Tenant status updated to {$tenant->status} successfully",
        ], 200);
    }

    /**
     * POST /api/v1/platform/tenants/{id}/plan
     * Change tenant subscription plan
     */
    public function changePlan(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'prorate' => 'nullable|boolean',
        ]);

        $sub = $this->adminService->changeTenantPlan(
            $id,
            $validated['plan_id'],
            $validated['prorate'] ?? true,
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $sub,
            'message' => 'Tenant subscription plan changed successfully',
        ], 200);
    }

    /**
     * PUT /api/v1/platform/tenants/{id}/limits
     */
    public function updateLimits(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'limits' => 'required|array',
        ]);

        $tenant = $this->adminService->updateTenantLimits(
            $id,
            $validated['limits'],
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'message' => 'Tenant limit overrides saved successfully',
        ], 200);
    }

    /**
     * POST /api/v1/platform/tenants/{id}/support-access
     * Breakglass support session initialization (Requires mandatory justification)
     */
    public function breakglassSupportAccess(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'justification_reason' => 'required|string|min:10|max:1000',
            'duration_minutes' => 'nullable|integer|min:5|max:240',
        ]);

        $session = $this->adminService->createBreakglassSupportSession(
            $id,
            $validated['justification_reason'],
            $request->user(),
            $validated['duration_minutes'] ?? 60,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $session,
            'message' => 'Breakglass support session created and logged successfully',
        ], 200);
    }

    /**
     * POST /api/v1/platform/support-access/{sessionId}/revoke
     */
    public function revokeSupportAccess(Request $request, int $sessionId): JsonResponse
    {
        $this->adminService->revokeBreakglassSupportSession(
            $sessionId,
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'message' => 'Support access session revoked successfully',
        ], 200);
    }
}
