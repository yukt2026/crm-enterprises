<?php

namespace App\Domains\LeadAcquisition\Services\Adapters;

use App\Domains\LeadAcquisition\Models\LeadSource;
use Illuminate\Support\Str;

class WhatsAppEnquirySourceAdapter implements SourceAdapterInterface
{
    public function verifyAuthentication(LeadSource $source, array $payload, array $headers): bool
    {
        $credentials = $source->credentials ?? [];
        $webhookSecret = $credentials['webhook_secret'] ?? $credentials['verify_token'] ?? null;

        if (empty($webhookSecret)) {
            return true;
        }

        $hubSignature = $headers['x-hub-signature-256'][0] ?? null;
        if (!empty($hubSignature)) {
            $rawBody = is_string($payload['_raw_body'] ?? null) ? $payload['_raw_body'] : json_encode($payload);
            return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $webhookSecret), $hubSignature);
        }

        $providedToken = $headers['x-whatsapp-token'][0] ?? $payload['verify_token'] ?? null;
        if (!empty($providedToken)) {
            return hash_equals((string) $webhookSecret, (string) $providedToken);
        }

        return true;
    }

    public function extractEventId(array $payload, array $headers): string
    {
        return (string) ($payload['messages'][0]['id'] ?? $payload['id'] ?? $payload['message_id'] ?? ('wa_' . Str::uuid()));
    }

    public function normalizePayload(LeadSource $source, array $payload): array
    {
        $message = $payload['messages'][0] ?? $payload;
        $contact = $payload['contacts'][0] ?? [];

        $senderPhone = $message['from'] ?? $payload['from'] ?? $payload['phone'] ?? null;
        $senderName = $contact['profile']['name'] ?? $payload['name'] ?? $payload['profile_name'] ?? ('WhatsApp Lead ' . substr($senderPhone ?? 'User', -4));
        $textBody = $message['text']['body'] ?? $payload['text'] ?? $payload['body'] ?? $payload['message'] ?? 'Inbound WhatsApp enquiry';

        $normalized = [
            'name' => $senderName,
            'phone' => $senderPhone,
            'email' => $payload['email'] ?? null,
            'requirement' => $textBody,
        ];

        return array_filter($normalized, fn($v) => !is_null($v) && $v !== '');
    }

    public function extractAttribution(LeadSource $source, array $payload): array
    {
        $message = $payload['messages'][0] ?? $payload;
        $referral = $message['referral'] ?? $payload['referral'] ?? [];

        $isClickToWhatsApp = !empty($referral);

        return [
            'source' => 'whatsapp',
            'source_account' => $source->name ?? 'WhatsApp Business',
            'campaign' => $referral['headline'] ?? ($isClickToWhatsApp ? 'Click-to-WhatsApp Campaign' : 'Direct WhatsApp Inbound'),
            'ad_id' => (string) ($referral['source_id'] ?? ''),
            'utm_source' => $isClickToWhatsApp ? 'meta_click_to_whatsapp' : 'whatsapp',
            'utm_medium' => $isClickToWhatsApp ? 'paid_chat' : 'messaging',
            'utm_campaign' => $referral['headline'] ?? null,
            'ad_details' => $referral['body'] ?? null,
            'submission_time' => now()->toIso8601String(),
        ];
    }
}
