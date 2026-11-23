<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\PlatformAdmin\Services\PlatformAdminService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformOverviewController extends Controller
{
    public function __construct(
        protected PlatformAdminService $adminService
    ) {}

    /**
     * GET /api/v1/platform/overview
     * Returns platform-wide KPIs: MRR, ARR, Tenants, Subscriptions, Volumes, Wholesale Costs, System Health
     */
    public function index(): JsonResponse
    {
        $metrics = $this->adminService->getOverviewMetrics();
        return response()->json([
            'success' => true,
            'data' => $metrics,
            'message' => 'Platform overview metrics retrieved successfully',
        ], 200);
    }
}
