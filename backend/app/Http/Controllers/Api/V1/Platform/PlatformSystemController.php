<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\PlatformAdmin\Services\PlatformSystemService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSystemController extends Controller
{
    public function __construct(
        protected PlatformSystemService $systemService
    ) {}

    /**
     * GET /api/v1/platform/system/queues
     */
    public function queues(): JsonResponse
    {
        $stats = $this->systemService->getQueueStats();
        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Queue statistics retrieved',
        ], 200);
    }

    /**
     * POST /api/v1/platform/system/queues/{id}/retry
     */
    public function retryQueueJob(Request $request, string $id): JsonResponse
    {
        $this->systemService->retryFailedJob(
            $id,
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'message' => "Job {$id} queued for retry",
        ], 200);
    }

    /**
     * POST /api/v1/platform/system/queues/purge
     */
    public function purgeQueueJobs(Request $request): JsonResponse
    {
        $purged = $this->systemService->purgeFailedJobs(
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'purged_count' => $purged,
            'message' => "Purged {$purged} failed queue jobs",
        ], 200);
    }

    /**
     * GET /api/v1/platform/system/feature-flags
     */
    public function featureFlags(): JsonResponse
    {
        $flags = $this->systemService->getFeatureFlags();
        return response()->json([
            'success' => true,
            'data' => $flags,
            'message' => 'Feature flags retrieved',
        ], 200);
    }

    /**
     * PUT /api/v1/platform/system/feature-flags/{key}
     */
    public function updateFeatureFlag(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'rules' => 'nullable|array',
        ]);

        $flag = $this->systemService->updateFeatureFlag(
            $key,
            $validated,
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $flag,
            'message' => "Feature flag '{$key}' updated",
        ], 200);
    }

    /**
     * GET /api/v1/platform/system/maintenance
     */
    public function maintenanceMode(): JsonResponse
    {
        $mode = $this->systemService->getMaintenanceMode();
        return response()->json([
            'success' => true,
            'data' => $mode,
            'message' => 'Maintenance mode status retrieved',
        ], 200);
    }

    /**
     * POST /api/v1/platform/system/maintenance
     */
    public function setMaintenanceMode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => 'required|boolean',
            'message' => 'nullable|string|max:500',
            'bypass_secret' => 'nullable|string|max:64',
        ]);

        $mode = $this->systemService->setMaintenanceMode(
            $validated['is_enabled'],
            $validated['message'] ?? null,
            $validated['bypass_secret'] ?? null,
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $mode,
            'message' => $validated['is_enabled'] ? 'Maintenance mode enabled' : 'Maintenance mode disabled',
        ], 200);
    }

    /**
     * GET /api/v1/platform/system/audit-logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['action', 'admin_id', 'target_type']);
        $perPage = (int) $request->input('per_page', 50);

        $logs = $this->systemService->getAuditLogs($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
            'message' => 'Platform audit logs retrieved',
        ], 200);
    }
}
