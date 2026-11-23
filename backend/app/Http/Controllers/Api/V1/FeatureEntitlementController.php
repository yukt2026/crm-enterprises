<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Services\FeatureEntitlementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureEntitlementController extends Controller
{
    public function __construct(
        protected FeatureEntitlementService $entitlementService
    ) {}

    /**
     * Get All Feature Entitlements for Current Tenant
     */
    public function index(Request $request): JsonResponse
    {
        return $this->getEntitlements($request);
    }

    public function getEntitlements(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant workspace found.',
            ], 400);
        }

        $entitlements = $this->entitlementService->getTenantEntitlements($tenant);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'plan_code' => $tenant->subscription_tier ?? 'pro',
                'subscription_tier' => $tenant->subscription_tier ?? 'pro',
                'is_suspended' => (bool) $tenant->suspended_at,
                'status' => $tenant->status ?? 'active',
                'entitlements' => $entitlements,
            ],
        ]);
    }

    /**
     * Verify Access for a Specific Feature
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'feature_key' => 'required|string',
        ]);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant workspace found.',
            ], 400);
        }

        $result = $this->entitlementService->checkEntitlement(
            $tenant,
            $request->feature_key,
            $request->user()
        );

        $result['permitted'] = (bool) ($result['allowed'] ?? false);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
