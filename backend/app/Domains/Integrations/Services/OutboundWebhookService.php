<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\OutboundWebhook;
use App\Domains\Integrations\Models\WebhookDeliveryLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OutboundWebhookService
{
    /**
     * Create an outbound webhook subscription with strict HTTPS destination validation
     */
    public function createWebhook(int $tenantId, array $data): OutboundWebhook
    {
        $url = $data['url'] ?? '';

        // Validate HTTPS in production or enforce valid URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Destination URL must be a valid URL.');
        }

        if (app()->isProduction() && !str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('Webhook destination must be a secure HTTPS URL in production.');
        }

        $signingSecret = 'whsec_' . Str::random(32);

        return OutboundWebhook::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'] ?? 'Outbound Webhook',
            'url' => $url,
            'signing_secret' => $signingSecret,
            'events' => $data['events'] ?? ['lead.created', 'deal.won'],
            'is_active' => $data['is_active'] ?? true,
            'retry_limit' => $data['retry_limit'] ?? 5,
            'timeout_seconds' => $data['timeout_seconds'] ?? 10,
            'headers' => $data['headers'] ?? null,
        ]);
    }

    public function listWebhooks(int $tenantId)
    {
        return OutboundWebhook::where('tenant_id', $tenantId)
            ->withCount('deliveryLogs')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateWebhook(int $tenantId, int $id, array $data): OutboundWebhook
    {
        $webhook = OutboundWebhook::where('tenant_id', $tenantId)->findOrFail($id);

        if (isset($data['url'])) {
            if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Destination URL must be a valid URL.');
            }
            if (app()->isProduction() && !str_starts_with($data['url'], 'https://')) {
                throw new \InvalidArgumentException('Webhook destination must be a secure HTTPS URL in production.');
            }
        }

        $webhook->update($data);
        return $webhook;
    }

    public function deleteWebhook(int $tenantId, int $id): bool
    {
        $webhook = OutboundWebhook::where('tenant_id', $tenantId)->findOrFail($id);
        $webhook->delete();
        return true;
    }

    /**
     * Dispatch an event to all matching active outbound webhook subscribers
     */
    public function dispatchEvent(int $tenantId, string $eventType, array $payload): array
    {
        $webhooks = OutboundWebhook::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $dispatched = [];

        foreach ($webhooks as $webhook) {
            $events = $webhook->events ?? [];
            if (!in_array('*', $events) && !in_array($eventType, $events)) {
                continue;
            }

            $dispatched[] = $this->deliverWebhook($webhook, $eventType, $payload);
        }

        return $dispatched;
    }

    /**
     * Deliver webhook payload with HMAC-SHA256 signature and replay protection headers
     */
    public function deliverWebhook(OutboundWebhook $webhook, string $eventType, array $payload, int $attemptNumber = 1): WebhookDeliveryLog
    {
        $eventId = (string) Str::uuid();
        $timestamp = time();

        $envelope = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'timestamp' => $timestamp,
            'tenant_id' => $webhook->tenant_id,
            'data' => $payload,
        ];

        $payloadJson = json_encode($envelope);
        $signature = $this->computeSignature($timestamp, $payloadJson, $webhook->signing_secret);

        $deliveryLog = WebhookDeliveryLog::create([
            'tenant_id' => $webhook->tenant_id,
            'outbound_webhook_id' => $webhook->id,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $envelope,
            'destination_url' => $webhook->url,
            'attempt_number' => $attemptNumber,
            'status' => 'pending',
        ]);

        $headers = array_merge($webhook->headers ?? [], [
            'Content-Type' => 'application/json',
            'User-Agent' => 'CRM-Enterprises-Webhook-Dispatcher/2.0',
            'X-CRM-Signature' => "t={$timestamp},v1={$signature}",
            'X-CRM-Timestamp' => (string) $timestamp,
            'X-CRM-Event-Id' => $eventId,
            'X-CRM-Delivery-Attempt' => (string) $attemptNumber,
        ]);

        try {
            $response = Http::timeout($webhook->timeout_seconds ?: 10)
                ->withHeaders($headers)
                ->post($webhook->url, $envelope);

            $isSuccess = $response->successful();

            $deliveryLog->update([
                'status' => $isSuccess ? 'success' : 'failed',
                'response_status_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'delivered_at' => $isSuccess ? Carbon::now() : null,
                'next_retry_at' => !$isSuccess && $attemptNumber < $webhook->retry_limit
                    ? Carbon::now()->addSeconds(pow(2, $attemptNumber) * 15)
                    : null,
            ]);

            $webhook->update([
                'last_delivery_at' => Carbon::now(),
                'last_delivery_status' => $isSuccess ? 'success' : 'failed',
            ]);
        } catch (\Throwable $e) {
            $deliveryLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'next_retry_at' => $attemptNumber < $webhook->retry_limit
                    ? Carbon::now()->addSeconds(pow(2, $attemptNumber) * 15)
                    : null,
            ]);

            $webhook->update([
                'last_delivery_at' => Carbon::now(),
                'last_delivery_status' => 'failed',
            ]);
        }

        return $deliveryLog;
    }

    /**
     * Compute HMAC-SHA256 signature over timestamp and payload
     */
    public function computeSignature(int $timestamp, string $payloadJson, string $secret): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$payloadJson}", $secret);
    }

    /**
     * Verify incoming or outgoing webhook signature with replay protection
     */
    public function verifySignature(string $payloadJson, string $signatureHeader, string $secret, int $toleranceSeconds = 300): bool
    {
        // Parse t=timestamp,v1=signature
        $parts = [];
        foreach (explode(',', $signatureHeader) as $item) {
            $pair = explode('=', trim($item), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }

        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];
        $signature = $parts['v1'];

        // Replay attack protection: ensure timestamp is within acceptable window
        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expectedSignature = $this->computeSignature($timestamp, $payloadJson, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Redeliver a failed delivery log
     */
    public function redeliver(int $tenantId, int $deliveryLogId): WebhookDeliveryLog
    {
        $log = WebhookDeliveryLog::where('tenant_id', $tenantId)->findOrFail($deliveryLogId);
        $webhook = OutboundWebhook::where('tenant_id', $tenantId)->findOrFail($log->outbound_webhook_id);

        return $this->deliverWebhook($webhook, $log->event_type, $log->payload['data'] ?? [], $log->attempt_number + 1);
    }

    public function getDeliveryLogs(int $tenantId, ?int $webhookId = null, int $limit = 50)
    {
        $query = WebhookDeliveryLog::where('tenant_id', $tenantId)
            ->with('webhook:id,name,url')
            ->orderBy('created_at', 'desc');

        if ($webhookId) {
            $query->where('outbound_webhook_id', $webhookId);
        }

        return $query->take($limit)->get();
    }
}
