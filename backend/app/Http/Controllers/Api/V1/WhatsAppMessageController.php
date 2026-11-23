<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\WhatsApp\Services\WhatsAppMessagingService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppMessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected WhatsAppMessagingService $messagingService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * Send freeform text message (enforces 24h window)
     */
    public function sendText(int $conversationId, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'text' => 'required|string|max:4096',
        ]);

        $message = $this->messagingService->sendTextMessage(
            $tenantId,
            $conversationId,
            $validated['text'],
            $request->user(),
            'human'
        );

        return $this->successResponse($message, 'Message dispatched successfully.', 201);
    }

    /**
     * Send pre-approved message template (allowed outside 24h window)
     */
    public function sendTemplate(int $conversationId, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'template_name' => 'required|string|max:100',
            'language' => 'nullable|string|max:20',
            'parameters' => 'nullable|array',
        ]);

        $message = $this->messagingService->sendTemplateMessage(
            $tenantId,
            $conversationId,
            $validated['template_name'],
            $validated['language'] ?? 'en_US',
            $validated['parameters'] ?? [],
            $request->user()
        );

        return $this->successResponse($message, 'Template message dispatched successfully.', 201);
    }

    /**
     * Send media message (image, video, document, audio)
     */
    public function sendMedia(int $conversationId, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'media_type' => 'required|string|in:image,document,audio,video',
            'media_url' => 'required|url|max:500',
            'caption' => 'nullable|string|max:1024',
        ]);

        $message = $this->messagingService->sendMediaMessage(
            $tenantId,
            $conversationId,
            $validated['media_type'],
            $validated['media_url'],
            $validated['caption'] ?? null,
            $request->user()
        );

        return $this->successResponse($message, 'Media message dispatched successfully.', 201);
    }

    /**
     * Send interactive button or list message
     */
    public function sendInteractive(int $conversationId, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'interactive' => 'required|array',
        ]);

        $message = $this->messagingService->sendInteractiveMessage(
            $tenantId,
            $conversationId,
            $validated['interactive'],
            $request->user()
        );

        return $this->successResponse($message, 'Interactive message dispatched successfully.', 201);
    }

    /**
     * Send location pin
     */
    public function sendLocation(int $conversationId, Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'name' => 'nullable|string|max:150',
            'address' => 'nullable|string|max:255',
        ]);

        $message = $this->messagingService->sendLocationMessage(
            $tenantId,
            $conversationId,
            $validated['latitude'],
            $validated['longitude'],
            $validated['name'] ?? null,
            $validated['address'] ?? null,
            $request->user()
        );

        return $this->successResponse($message, 'Location dispatched successfully.', 201);
    }
}
