<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integrations\Models\IntegrationSyncLog;
use App\Domains\Integrations\Services\IntegrationService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected IntegrationService $integrationService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * GET /api/v1/crm/integrations
     * Get directory of all integrations with status
     */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $directory = $this->integrationService->getDirectory($tenantId);

        return $this->successResponse($directory, 'Integrations directory retrieved successfully');
    }

    /**
     * POST /api/v1/crm/integrations/oauth-url
     * Generate OAuth authorization URL
     */
    public function oauthUrl(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = auth()->id() ?? 1;

        $validated = $request->validate([
            'provider' => 'required|string',
            'redirect_uri' => 'required|url',
        ]);

        try {
            $data = $this->integrationService->generateOAuthUrl(
                $tenantId,
                $userId,
                $validated['provider'],
                $validated['redirect_uri']
            );

            return $this->successResponse($data, 'OAuth authorization URL generated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/crm/integrations/oauth-callback
     * Exchange OAuth code for tokens and encrypt credentials
     */
    public function oauthCallback(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = auth()->id() ?? 1;

        $validated = $request->validate([
            'provider' => 'required|string',
            'code' => 'required|string',
            'state' => 'required|string',
            'redirect_uri' => 'required|url',
        ]);

        try {
            $integration = $this->integrationService->handleOAuthCallback(
                $tenantId,
                $userId,
                $validated['provider'],
                $validated['code'],
                $validated['state'],
                $validated['redirect_uri']
            );

            return $this->successResponse($integration, 'Integration connected successfully via OAuth');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/crm/integrations/connect-credentials
     * Connect using direct API keys / credentials
     */
    public function connectCredentials(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = auth()->id() ?? 1;

        $validated = $request->validate([
            'provider' => 'required|string',
            'name' => 'nullable|string|max:255',
            'credentials' => 'required|array',
            'metadata' => 'nullable|array',
        ]);

        try {
            $integration = $this->integrationService->connectWithCredentials(
                $tenantId,
                $userId,
                $validated['provider'],
                $validated['name'] ?? '',
                $validated['credentials'],
                $validated['metadata'] ?? []
            );

            return $this->successResponse($integration, 'Integration connected successfully with credentials', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/crm/integrations/{id}/disconnect
     */
    public function disconnect(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        try {
            $this->integrationService->disconnect($tenantId, $id);
            return $this->successResponse(null, 'Integration disconnected successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/crm/integrations/{id}/test
     */
    public function test(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $result = $this->integrationService->testConnectedIntegration($tenantId, $id);
        return $this->successResponse($result, 'Connection diagnostics completed');
    }

    /**
     * POST /api/v1/crm/integrations/{id}/sync
     */
    public function sync(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        try {
            $result = $this->integrationService->syncIntegration($tenantId, $id, 'manual');
            return $this->successResponse($result, 'Data synchronization initiated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/v1/crm/integrations/{id}/sync-logs
     */
    public function syncLogs(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $logs = IntegrationSyncLog::where('tenant_id', $tenantId)
            ->where('integration_id', $id)
            ->orderBy('started_at', 'desc')
            ->take(30)
            ->get();

        return $this->successResponse($logs, 'Sync logs retrieved successfully');
    }
}
