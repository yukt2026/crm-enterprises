<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integrations\Services\OutboundWebhookService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutboundWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected OutboundWebhookService $webhookService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * GET /api/v1/crm/webhooks
     */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $webhooks = $this->webhookService->listWebhooks($tenantId);

        return $this->successResponse($webhooks, 'Outbound webhooks retrieved successfully');
    }

    /**
     * POST /api/v1/crm/webhooks
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'events' => 'required|array|min:1',
            'is_active' => 'nullable|boolean',
            'retry_limit' => 'nullable|integer|min:1|max:10',
            'timeout_seconds' => 'nullable|integer|min:3|max:30',
            'headers' => 'nullable|array',
        ]);

        try {
            $webhook = $this->webhookService->createWebhook($tenantId, $validated);
            return $this->successResponse($webhook, 'Outbound webhook endpoint registered successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/v1/crm/webhooks/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'url' => 'nullable|url',
            'events' => 'nullable|array|min:1',
            'is_active' => 'nullable|boolean',
            'retry_limit' => 'nullable|integer|min:1|max:10',
            'timeout_seconds' => 'nullable|integer|min:3|max:30',
            'headers' => 'nullable|array',
        ]);

        try {
            $webhook = $this->webhookService->updateWebhook($tenantId, $id, $validated);
            return $this->successResponse($webhook, 'Webhook updated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/v1/crm/webhooks/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->webhookService->deleteWebhook($tenantId, $id);

        return $this->successResponse(null, 'Webhook deleted successfully');
    }

    /**
     * POST /api/v1/crm/webhooks/{id}/test-ping
     * Send test ping event
     */
    public function testPing(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $webhook = \App\Domains\Integrations\Models\OutboundWebhook::where('tenant_id', $tenantId)->findOrFail($id);

        $log = $this->webhookService->deliverWebhook($webhook, 'ping.test', [
            'message' => 'Test ping event from CRM Enterprises Webhook Dispatcher',
            'test_mode' => true,
        ]);

        return $this->successResponse($log, 'Test ping dispatched to destination URL');
    }

    /**
     * GET /api/v1/crm/webhooks/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $webhookId = $request->query('webhook_id') ? (int) $request->query('webhook_id') : null;

        $logs = $this->webhookService->getDeliveryLogs($tenantId, $webhookId, 50);

        return $this->successResponse($logs, 'Webhook delivery logs retrieved successfully');
    }

    /**
     * POST /api/v1/crm/webhooks/logs/{id}/redeliver
     */
    public function redeliver(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        try {
            $log = $this->webhookService->redeliver($tenantId, $id);
            return $this->successResponse($log, 'Webhook event redelivery dispatched');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
