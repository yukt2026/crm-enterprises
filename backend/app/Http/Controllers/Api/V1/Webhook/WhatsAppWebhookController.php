<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Domains\WhatsApp\Services\WhatsAppWebhookService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WhatsAppWebhookService $webhookService
    ) {}

    /**
     * Handle Meta Webhook Verification Challenge (GET)
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $result = $this->webhookService->verifyWebhookChallenge($mode, $token, $challenge);

        if ($result !== null) {
            return response($result, 200, ['Content-Type' => 'text/plain']);
        }

        return response('Forbidden: Invalid verification token', 403);
    }

    /**
     * Handle Signed Meta Webhook Events (POST)
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Hub-Signature-256');
        $rawPayload = $request->getContent();

        // Validate cryptographic signature
        if (!$this->webhookService->verifySignature($rawPayload, $signature)) {
            Log::warning('WhatsApp Webhook rejected: Invalid cryptographic signature');
            return $this->errorResponse('Invalid webhook signature.', 400);
        }

        $payload = json_decode($rawPayload, true);
        if (!$payload) {
            return $this->errorResponse('Invalid JSON payload.', 400);
        }

        try {
            $result = $this->webhookService->processWebhook($payload);
            return $this->successResponse($result, 'WhatsApp events processed.');
        } catch (\Throwable $e) {
            Log::error('Error processing WhatsApp webhook: ' . $e->getMessage());
            return $this->errorResponse('Error processing webhook: ' . $e->getMessage(), 500);
        }
    }
}
