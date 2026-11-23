<?php

namespace App\Domains\LeadAcquisition\Services\Adapters;

use App\Domains\LeadAcquisition\Models\LeadSource;
use Illuminate\Support\Str;

class GoogleAdsSourceAdapter implements SourceAdapterInterface
{
    public function verifyAuthentication(LeadSource $source, array $payload, array $headers): bool
    {
        $credentials = $source->credentials ?? [];
        $expectedKey = $credentials['google_key'] ?? $credentials['webhook_key'] ?? null;

        // If no secret configured yet on source, allow sandbox/development testing if enabled
        if (empty($expectedKey)) {
            return true;
        }

        $providedKey = $payload['google_key'] ?? $headers['x-google-key'][0] ?? $headers['x-goog-signature'][0] ?? null;

        return hash_equals((string) $expectedKey, (string) $providedKey);
    }

    public function extractEventId(array $payload, array $headers): string
    {
        return (string) ($payload['lead_id'] ?? $payload['submission_id'] ?? $payload['event_id'] ?? Str::uuid());
    }

    public function normalizePayload(LeadSource $source, array $payload): array
    {
        $normalized = [
            'name' => null,
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'phone' => null,
            'company_name' => null,
            'requirement' => null,
        ];

        // Format A: Google Ads Lead Form column data format
        if (isset($payload['user_column_data']) && is_array($payload['user_column_data'])) {
            foreach ($payload['user_column_data'] as $col) {
                $colId = strtoupper($col['column_id'] ?? $col['string_value'] ?? '');
                $val = trim($col['string_value'] ?? '');

                if (str_contains($colId, 'FIRST_NAME')) {
                    $normalized['first_name'] = $val;
                } elseif (str_contains($colId, 'LAST_NAME')) {
                    $normalized['last_name'] = $val;
                } elseif (str_contains($colId, 'FULL_NAME')) {
                    $normalized['name'] = $val;
                } elseif (str_contains($colId, 'EMAIL')) {
                    $normalized['email'] = strtolower($val);
                } elseif (str_contains($colId, 'PHONE')) {
                    $normalized['phone'] = $val;
                } elseif (str_contains($colId, 'COMPANY_NAME')) {
                    $normalized['company_name'] = $val;
                } elseif (str_contains($colId, 'WORK_EMAIL')) {
                    $normalized['email'] = strtolower($val);
                } else {
                    $normalized[strtolower($colId)] = $val;
                }
            }
        }

        // Format B: Direct or flattened fields
        if (empty($normalized['name'])) {
            if (!empty($payload['name'])) {
                $normalized['name'] = $payload['name'];
            } elseif (!empty($normalized['first_name']) || !empty($normalized['last_name'])) {
                $normalized['name'] = trim(($normalized['first_name'] ?? '') . ' ' . ($normalized['last_name'] ?? ''));
            } elseif (!empty($payload['full_name'])) {
                $normalized['name'] = $payload['full_name'];
            }
        }

        if (empty($normalized['email']) && !empty($payload['email'])) {
            $normalized['email'] = strtolower(trim($payload['email']));
        }

        if (empty($normalized['phone']) && !empty($payload['phone'])) {
            $normalized['phone'] = trim($payload['phone']);
        }

        if (empty($normalized['company_name']) && !empty($payload['company_name'])) {
            $normalized['company_name'] = trim($payload['company_name']);
        }

        return array_filter($normalized, fn($v) => !is_null($v));
    }

    public function extractAttribution(LeadSource $source, array $payload): array
    {
        return [
            'source' => 'google_ads',
            'source_account' => $source->name ?? 'Google Ads',
            'campaign' => $payload['campaign_name'] ?? ('Google Campaign #' . ($payload['campaign_id'] ?? 'Direct')),
            'campaign_id' => (string) ($payload['campaign_id'] ?? ''),
            'ad_group_id' => (string) ($payload['ad_group_id'] ?? ''),
            'ad_id' => (string) ($payload['ad_id'] ?? $payload['creative_id'] ?? ''),
            'form_id' => (string) ($payload['form_id'] ?? ''),
            'gclid' => $payload['gclid'] ?? null,
            'utm_source' => $payload['utm_source'] ?? 'google',
            'utm_medium' => $payload['utm_medium'] ?? 'cpc',
            'utm_campaign' => $payload['utm_campaign'] ?? ($payload['campaign_name'] ?? null),
            'utm_term' => $payload['utm_term'] ?? null,
            'utm_content' => $payload['utm_content'] ?? null,
            'submission_time' => $payload['lead_submitted_timestamp'] ?? now()->toIso8601String(),
        ];
    }
}
