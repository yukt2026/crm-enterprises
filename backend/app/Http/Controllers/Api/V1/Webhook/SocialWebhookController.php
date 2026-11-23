<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Domains\Omnichannel\Services\SocialMessagingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SocialWebhookController extends Controller
{
    public function __construct(
        protected SocialMessagingService $socialService
    ) {}

    /**
     * Meta Webhook Challenge Verification (GET)
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode && $token && $challenge) {
            $verifiedChallenge = $this->socialService->verifyWebhookChallenge((string) $mode, (string) $token, (string) $challenge);
            if ($verifiedChallenge !== null) {
                return response($verifiedChallenge, 200, ['Content-Type' => 'text/plain']);
            }
        }

        return response('Forbidden', 403);
    }

    /**
     * Meta Messenger & Instagram Direct Message Webhook Ingestion (POST)
     */
    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        // Verify cryptographic signature when present or in production
        if ($signature) {
            if (!$this->socialService->verifySignature($rawPayload, $signature)) {
                return response()->json(['error' => 'Invalid HMAC signature.'], 401);
            }
        } elseif (app()->environment('production') || config('services.meta.verify_signatures', false)) {
            return response()->json(['error' => 'Missing HMAC signature.'], 401);
        }

        $payload = $request->all();
        $result = $this->socialService->handleWebhookPayload($payload);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    /**
     * Telegram Inbound Webhook Handler (POST)
     */
    public function handleTelegram(Request $request, int $tenantId): JsonResponse
    {
        $payload = $request->all();
        $adapter = app(\App\Domains\Omnichannel\Adapters\TelegramChannelAdapter::class);
        $message = $adapter->handleInbound($tenantId, $payload);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message_id' => $message->id,
        ]);
    }
}
