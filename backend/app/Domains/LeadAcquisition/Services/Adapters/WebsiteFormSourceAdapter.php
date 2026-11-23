<?php

namespace App\Domains\LeadAcquisition\Services\Adapters;

use App\Domains\LeadAcquisition\Models\LeadSource;
use Illuminate\Support\Str;

class WebsiteFormSourceAdapter implements SourceAdapterInterface
{
    public function verifyAuthentication(LeadSource $source, array $payload, array $headers): bool
    {
        // 1. Spam honeypot protection: if hidden bot traps are filled, reject
        if (!empty($payload['_hp_check']) || !empty($payload['_honeypot'])) {
            return false;
        }

        // 2. Token verification if configured on source
        $credentials = $source->credentials ?? [];
        $apiToken = $credentials['public_api_key'] ?? $credentials['token'] ?? null;
        if (!empty($apiToken)) {
            $providedToken = $headers['x-api-key'][0] ?? $headers['authorization'][0] ?? $payload['api_key'] ?? null;
            if ($providedToken && str_starts_with($providedToken, 'Bearer ')) {
                $providedToken = substr($providedToken, 7);
            }
            if ($providedToken !== $apiToken) {
                return false;
            }
        }

        return true;
    }

    public function extractEventId(array $payload, array $headers): string
    {
        return (string) ($payload['event_id'] ?? $payload['submission_id'] ?? ('web_' . Str::uuid()));
    }

    public function normalizePayload(LeadSource $source, array $payload): array
    {
        $normalized = [
            'name' => trim($payload['name'] ?? $payload['full_name'] ?? (($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''))),
            'email' => isset($payload['email']) ? strtolower(trim($payload['email'])) : null,
            'phone' => isset($payload['phone']) ? trim($payload['phone']) : null,
            'company_name' => isset($payload['company_name']) ? trim($payload['company_name']) : ($payload['company'] ?? null),
            'requirement' => $payload['requirement'] ?? $payload['message'] ?? $payload['notes'] ?? null,
            'estimated_value' => isset($payload['estimated_value']) ? (float) $payload['estimated_value'] : null,
        ];

        return array_filter($normalized, fn($v) => !is_null($v) && $v !== '');
    }

    public function extractAttribution(LeadSource $source, array $payload): array
    {
        return [
            'source' => $payload['source'] ?? 'website',
            'source_account' => $source->name ?? 'Corporate Website',
            'landing_page_url' => $payload['landing_page_url'] ?? $payload['page_url'] ?? $payload['referrer'] ?? null,
            'campaign' => $payload['utm_campaign'] ?? 'Website Direct',
            'utm_source' => $payload['utm_source'] ?? 'organic',
            'utm_medium' => $payload['utm_medium'] ?? 'web',
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'utm_term' => $payload['utm_term'] ?? null,
            'utm_content' => $payload['utm_content'] ?? null,
            'referrer' => $payload['referrer'] ?? null,
            'gclid' => $payload['gclid'] ?? null,
            'fbclid' => $payload['fbclid'] ?? null,
            'submission_time' => now()->toIso8601String(),
        ];
    }
}
