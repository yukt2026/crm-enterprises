<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Domains\Finance\Services\PaymentGatewayService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaymentGatewayService $paymentGatewayService
    ) {}

    /**
     * Handle incoming cryptographically signed Razorpay webhook
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature') ?? '';
        $rawPayload = $request->getContent();

        if (empty($signature)) {
            Log::warning('Razorpay webhook missing X-Razorpay-Signature header');
            return $this->errorResponse('Missing signature header', 400);
        }

        try {
            $result = $this->paymentGatewayService->processWebhook($rawPayload, $signature);
            return $this->successResponse($result, 'Webhook processed.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 400, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Razorpay webhook processing failed: ' . $e->getMessage());
            return $this->errorResponse('Webhook processing error: ' . $e->getMessage(), 500);
        }
    }
}
