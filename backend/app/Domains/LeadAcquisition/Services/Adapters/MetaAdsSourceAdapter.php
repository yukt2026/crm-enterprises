<?php

namespace App\Domains\LeadAcquisition\Services\Adapters;

use App\Domains\LeadAcquisition\Models\LeadSource;
use Illuminate\Support\Str;

class MetaAdsSourceAdapter implements SourceAdapterInterface
{
    public function verifyAuthentication(LeadSource $source, array $payload, array $headers): bool
    {
        $credentials = $source->credentials ?? [];
        $appSecret = $credentials['app_secret'] ?? $credentials['client_secret'] ?? null;

        if (empty($appSecret)) {
            return true; // Unset secret in sandbox allows local inspection
        }

        $hubSignature = $headers['x-hub-signature-256'][0] ?? $headers['x-hub-signature'][0] ?? null;
        if (empty($hubSignature)) {
            return false;
        }

        $rawBody = is_string($payload['_raw_body'] ?? null)
            ? $payload['_raw_body']
            : json_encode($payload);

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expectedSignature, $hubSignature);
    }

    public function extractEventId(array $payload, array $headers): string
    {
        // Try extracting Meta leadgen_id
        if (isset($payload['entry'][0]['changes'][0]['value']['leadgen_id'])) {
            return (string) $payload['entry'][0]['changes'][0]['value']['leadgen_id'];
        }

        return (string) ($payload['leadgen_id'] ?? $payload['id'] ?? $payload['event_id'] ?? Str::uuid());
    }

    public function normalizePayload(LeadSource $source, array $payload): array
    {
        $normalized = [
            'name' => null,
            'email' => null,
            'phone' => null,
            'company_name' => null,
            'requirement' => null,
        ];

        // Format A: Standard Meta Graph API field_data array
        $fieldData = $payload['field_data'] ?? $payload['entry'][0]['changes'][0]['value']['field_data'] ?? null;
        if (is_array($fieldData)) {
            foreach ($fieldData as $field) {
                $fieldName = strtolower(trim($field['name'] ?? ''));
                $val = trim($field['values'][0] ?? '');

                if (in_array($fieldName, ['full_name', 'name'])) {
                    $normalized['name'] = $val;
                } elseif (in_array($fieldName, ['first_name'])) {
                    $normalized['first_name'] = $val;
                } elseif (in_array($fieldName, ['last_name'])) {
                    $normalized['last_name'] = $val;
                } elseif (in_array($fieldName, ['email', 'work_email'])) {
                    $normalized['email'] = strtolower($val);
                } elseif (in_array($fieldName, ['phone_number', 'phone', 'mobile_phone'])) {
                    $normalized['phone'] = $val;
                } elseif (in_array($fieldName, ['company_name', 'business_name', 'company'])) {
                    $normalized['company_name'] = $val;
                } elseif (in_array($fieldName, ['job_title', 'designation'])) {
                    $normalized['designation'] = $val;
                } else {
                    $normalized[$fieldName] = $val;
                }
            }
        }

        // Format B: Direct attributes
        if (empty($normalized['name'])) {
            $normalized['name'] = $payload['name'] ?? $payload['full_name'] ?? null;
            if (empty($normalized['name']) && (!empty($normalized['first_name']) || !empty($normalized['last_name']))) {
                $normalized['name'] = trim(($normalized['first_name'] ?? '') . ' ' . ($normalized['last_name'] ?? ''));
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
        $changeValue = $payload['entry'][0]['changes'][0]['value'] ?? $payload;

        return [
            'source' => 'meta_ads',
            'source_account' => $source->name ?? 'Facebook & Instagram Ads',
            'campaign' => $changeValue['campaign_name'] ?? ('Meta Campaign #' . ($changeValue['campaign_id'] ?? 'Direct')),
            'campaign_id' => (string) ($changeValue['campaign_id'] ?? ''),
            'ad_group_id' => (string) ($changeValue['adset_id'] ?? $changeValue['adgroup_id'] ?? ''),
            'ad_id' => (string) ($changeValue['ad_id'] ?? ''),
            'form_id' => (string) ($changeValue['form_id'] ?? ''),
            'fbclid' => $changeValue['fbclid'] ?? null,
            'utm_source' => $changeValue['utm_source'] ?? 'facebook',
            'utm_medium' => $changeValue['utm_medium'] ?? 'paid_social',
            'utm_campaign' => $changeValue['utm_campaign'] ?? ($changeValue['campaign_name'] ?? null),
            'submission_time' => isset($changeValue['created_time']) ? date('c', $changeValue['created_time']) : now()->toIso8601String(),
        ];
    }
}
