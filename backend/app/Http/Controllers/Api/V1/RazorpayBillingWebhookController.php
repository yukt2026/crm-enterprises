<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Services\RazorpayBillingWebhookService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayBillingWebhookController extends Controller
{
    public function __construct(
        protected RazorpayBillingWebhookService $webhookService
    ) {}

    /**
     * POST /api/v1/public/webhooks/razorpay/billing
     */
    public function handle(Request $request): JsonResponse
    {
        $payloadJson = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');
        $secret = config('services.razorpay.webhook_secret', 'rzp_whsec_test_secret_12345');

        try {
            $result = $this->webhookService->handleWebhook($payloadJson, $signature, $secret);
            return response()->json($result, 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal server error processing webhook: ' . $e->getMessage(),
            ], 500);
        }
    }
}
