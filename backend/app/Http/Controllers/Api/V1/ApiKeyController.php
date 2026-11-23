<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integrations\Services\ApiKeyService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected ApiKeyService $apiKeyService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * GET /api/v1/crm/api-keys
     */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $keys = $this->apiKeyService->listApiKeys($tenantId);

        return $this->successResponse($keys, 'API keys retrieved successfully');
    }

    /**
     * POST /api/v1/crm/api-keys
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = auth()->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'scopes' => 'nullable|array',
            'allowed_ips' => 'nullable|array',
            'rate_limit_per_minute' => 'nullable|integer|min:10|max:1000',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $result = $this->apiKeyService->createApiKey($tenantId, $userId, $validated);

        return $this->successResponse($result, 'API key generated successfully. Copy your secret key now.', 201);
    }

    /**
     * DELETE /api/v1/crm/api-keys/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->apiKeyService->revokeApiKey($tenantId, $id);

        return $this->successResponse(null, 'API key revoked successfully');
    }

    /**
     * POST /api/v1/crm/api-keys/{id}/rotate
     */
    public function rotate(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = auth()->id() ?? 1;

        $result = $this->apiKeyService->rotateApiKey($tenantId, $userId, $id);

        return $this->successResponse($result, 'API key rotated successfully. A new secret key has been generated.');
    }

    /**
     * GET /api/v1/crm/api-keys/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $keyId = $request->query('api_key_id') ? (int) $request->query('api_key_id') : null;

        $logs = $this->apiKeyService->getRequestLogs($tenantId, $keyId, 50);

        return $this->successResponse($logs, 'API request audit logs retrieved successfully');
    }
}
