<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\CreditLedgerEntry;
use App\Domains\Billing\Models\SubscriptionInvoice;
use App\Domains\Billing\Models\TenantSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RazorpayBillingWebhookService
{
    public function __construct(
        protected SubscriptionBillingService $billingService,
        protected CreditLedgerService $creditLedgerService
    ) {}

    /**
     * Process Razorpay billing webhook with HMAC signature verification and idempotency
     */
    public function handleWebhook(string $payloadJson, string $signatureHeader, string $secret): array
    {
        // 1. Verify cryptographic signature
        $expectedSignature = hash_hmac('sha256', $payloadJson, $secret);
        if (!hash_equals($expectedSignature, $signatureHeader)) {
            throw new \InvalidArgumentException('Invalid Razorpay webhook cryptographic signature.');
        }

        $payload = json_decode($payloadJson, true);
        if (!$payload || !isset($payload['event'])) {
            throw new \InvalidArgumentException('Invalid JSON payload or missing webhook event name.');
        }

        $event = $payload['event'];
        $eventData = $payload['payload'] ?? [];

        Log::info("Processing Razorpay webhook event: {$event}");

        // 2. Dispatch event handlers safely with idempotency
        return match ($event) {
            'payment.captured', 'subscription.charged' => $this->handlePaymentCaptured($eventData),
            'payment.failed' => $this->handlePaymentFailed($eventData),
            'subscription.cancelled' => $this->handleSubscriptionCancelled($eventData),
            default => [
                'success' => true,
                'message' => "Ignored unhandled event: {$event}",
            ],
        };
    }

    protected function handlePaymentCaptured(array $data): array
    {
        $payment = $data['payment']['entity'] ?? [];
        $paymentId = $payment['id'] ?? null;
        $notes = $payment['notes'] ?? [];
        $amount = isset($payment['amount']) ? ((float) $payment['amount']) / 100 : 0.0; // Razorpay amounts in paise

        if (!$paymentId) {
            return ['success' => false, 'message' => 'Missing payment entity id'];
        }

        // Idempotency check: verify if payment ID already processed
        $existingInvoice = SubscriptionInvoice::where('razorpay_payment_id', $paymentId)->first();
        if ($existingInvoice) {
            return [
                'success' => true,
                'message' => "Payment {$paymentId} already settled (Idempotent ignore).",
                'invoice_id' => $existingInvoice->id,
            ];
        }

        $existingLedger = CreditLedgerEntry::where('reference_id', $paymentId)->first();
        if ($existingLedger) {
            return [
                'success' => true,
                'message' => "Payment {$paymentId} already credited to ledger (Idempotent ignore).",
                'ledger_entry_id' => $existingLedger->entry_id,
            ];
        }

        // If this is a credit wallet recharge
        if (isset($notes['type']) && $notes['type'] === 'credit_recharge' && isset($notes['tenant_id'])) {
            $tenantId = (int) $notes['tenant_id'];
            $entry = $this->creditLedgerService->rechargeCredits(
                $tenantId,
                $amount,
                $paymentId,
                'Razorpay Credit Recharge',
                ['razorpay_payment_id' => $paymentId]
            );

            return [
                'success' => true,
                'message' => "Credits recharged for tenant #{$tenantId}",
                'ledger_entry_id' => $entry->entry_id,
            ];
        }

        // If subscription renewal or upgrade
        $subscriptionId = $notes['tenant_id'] ?? null;
        if ($subscriptionId) {
            $sub = $this->billingService->renewSubscription((int) $subscriptionId, $paymentId);
            return [
                'success' => true,
                'message' => "Subscription renewed for tenant #{$subscriptionId}",
            ];
        }

        return ['success' => true, 'message' => 'Payment recorded without matching subscription note'];
    }

    protected function handlePaymentFailed(array $data): array
    {
        $payment = $data['payment']['entity'] ?? [];
        $notes = $payment['notes'] ?? [];
        $tenantId = isset($notes['tenant_id']) ? (int) $notes['tenant_id'] : null;

        if ($tenantId) {
            $this->billingService->handleFailedPayment($tenantId);
            return [
                'success' => true,
                'message' => "Tenant #{$tenantId} subscription placed in grace period due to failed payment.",
            ];
        }

        return ['success' => true, 'message' => 'Payment failure logged'];
    }

    protected function handleSubscriptionCancelled(array $data): array
    {
        $subEntity = $data['subscription']['entity'] ?? [];
        $notes = $subEntity['notes'] ?? [];
        $tenantId = isset($notes['tenant_id']) ? (int) $notes['tenant_id'] : null;

        if ($tenantId) {
            $this->billingService->cancelSubscription($tenantId, 'Razorpay portal cancellation');
            return [
                'success' => true,
                'message' => "Subscription cancelled for tenant #{$tenantId}",
            ];
        }

        return ['success' => true, 'message' => 'Subscription cancellation recorded'];
    }
}
