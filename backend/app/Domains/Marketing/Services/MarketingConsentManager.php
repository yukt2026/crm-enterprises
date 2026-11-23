<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\MarketingConsent;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;
use Carbon\Carbon;

class MarketingConsentManager
{
    /**
     * Record explicit consent (opt-in or opt-out).
     */
    public function recordConsent(
        int $tenantId,
        string $entityType,
        int $entityId,
        string $channel,
        string $status,
        string $source = 'website_form',
        ?string $reason = null
    ): MarketingConsent {
        $data = [
            'consent_status' => $status,
            'source' => $source,
            'reason' => $reason,
            'opted_out_at' => $status === 'opted_out' ? now() : null,
        ];

        return MarketingConsent::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'channel' => $channel,
            ],
            $data
        );
    }

    /**
     * Check if a recipient has consented for a given channel.
     */
    public function hasConsent(int $tenantId, string $entityType, int $entityId, string $channel): bool
    {
        // 1. Check direct consent record in marketing_consents
        $consent = MarketingConsent::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereIn('channel', [$channel, 'all'])
            ->orderBy('id', 'desc')
            ->first();

        if ($consent && $consent->consent_status === 'opted_out') {
            return false;
        }

        // If explicitly opted in, true
        if ($consent && $consent->consent_status === 'opted_in') {
            return true;
        }

        // Default: If no explicit opt-out, allowed unless suppressed via DND/Unsubscribe
        return true;
    }

    /**
     * Handle STOP / Unsubscribe keyword from incoming webhook message.
     */
    public function handleUnsubscribeKeyword(int $tenantId, string $channel, string $address, ?int $entityId = null, string $entityType = 'contact'): void
    {
        $normalized = strtolower(trim($address));
        $cleanPhone = preg_replace('/[^0-9]/', '', $address);

        // 1. Add to omnichannel suppression table
        CommunicationSuppression::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'channel' => $channel,
                'identifier' => !empty($cleanPhone) && in_array($channel, ['sms', 'whatsapp']) ? $cleanPhone : $normalized,
            ],
            [
                'reason' => 'User requested STOP / Unsubscribe',
                'suppressed_at' => now(),
            ]
        );

        // 2. WhatsApp specific opt-out if applicable
        if ($channel === 'whatsapp' && !empty($cleanPhone)) {
            WhatsAppOptOut::updateOrCreate(
                ['tenant_id' => $tenantId, 'phone_number' => $cleanPhone],
                ['reason' => 'STOP keyword reply', 'opted_out_at' => now()]
            );
        }

        // 3. Update marketing_consents if entityId known
        if ($entityId) {
            $this->recordConsent($tenantId, $entityType, $entityId, $channel, 'opted_out', 'stop_reply', 'Unsubscribed via STOP keyword');
        }
    }
}
