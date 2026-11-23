<?php

namespace App\Domains\LeadAcquisition\Services\Adapters;

use App\Domains\LeadAcquisition\Models\LeadSource;
use Illuminate\Support\Str;

class GenericWebhookSourceAdapter implements SourceAdapterInterface
{
    public function verifyAuthentication(LeadSource $source, array $payload, array $headers): bool
    {
        $credentials = $source->credentials ?? [];
        $secret = $credentials['webhook_secret'] ?? $credentials['token'] ?? null;

        if (empty($secret)) {
            return true;
        }

        $providedSecret = $headers['x-webhook-secret'][0] ?? $headers['x-api-key'][0] ?? $payload['_token'] ?? null;
        if (!empty($providedSecret)) {
            return hash_equals((string) $secret, (string) $providedSecret);
        }

        $signature = $headers['x-signature'][0] ?? null;
        if (!empty($signature)) {
            $rawBody = is_string($payload['_raw_body'] ?? null) ? $payload['_raw_body'] : json_encode($payload);
            return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
        }

        return false;
    }

    public function extractEventId(array $payload, array $headers): string
    {
        return (string) ($payload['event_id'] ?? $payload['id'] ?? $payload['submission_id'] ?? Str::uuid());
    }

    public function normalizePayload(LeadSource $source, array $payload): array
    {
        $normalized = [
            'name' => trim($payload['name'] ?? $payload['full_name'] ?? (($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''))),
            'email' => isset($payload['email']) ? strtolower(trim($payload['email'])) : null,
            'phone' => isset($payload['phone']) ? trim($payload['phone']) : ($payload['telephone'] ?? null),
            'company_name' => $payload['company'] ?? $payload['company_name'] ?? $payload['organization'] ?? null,
            'requirement' => $payload['requirement'] ?? $payload['message'] ?? $payload['notes'] ?? null,
            'estimated_value' => isset($payload['budget']) || isset($payload['estimated_value']) ? (float) ($payload['budget'] ?? $payload['estimated_value']) : null,
        ];

        return array_filter($normalized, fn($v) => !is_null($v) && $v !== '');
    }

    public function extractAttribution(LeadSource $source, array $payload): array
    {
        return [
            'source' => $payload['source'] ?? $source->provider ?? 'webhook',
            'source_account' => $source->name ?? 'External Webhook',
            'campaign' => $payload['campaign'] ?? $payload['utm_campaign'] ?? 'Third-Party Integration',
            'utm_source' => $payload['utm_source'] ?? 'webhook',
            'utm_medium' => $payload['utm_medium'] ?? 'api',
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'submission_time' => now()->toIso8601String(),
        ];
    }
}
