<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;

class CampaignConsentService
{
    /**
     * Check whether communication is legally and technically permissible.
     */
    public function checkPermission(int $tenantId, string $channel, string $address): array
    {
        $normalizedAddress = strtolower(trim($address));
        $cleanPhone = preg_replace('/[^0-9]/', '', $address);

        // 1. Suppression Check (DND, Unsubscribe, Spam Complaint)
        $isSuppressed = CommunicationSuppression::where('tenant_id', $tenantId)
            ->whereIn('channel', [$channel, 'all'])
            ->where(function ($q) use ($normalizedAddress, $cleanPhone) {
                $q->where('identifier', $normalizedAddress);
                if (!empty($cleanPhone)) {
                    $q->orWhere('identifier', $cleanPhone);
                }
            })
            ->exists();

        if ($isSuppressed) {
            return [
                'allowed' => false,
                'reason' => 'suppressed_opt_out',
                'message' => 'Recipient is on the communication suppression / unsubscribe list.',
            ];
        }

        // 2. WhatsApp Opt-out check
        if ($channel === 'whatsapp') {
            $isOptedOut = WhatsAppOptOut::where('tenant_id', $tenantId)
                ->where(function ($q) use ($cleanPhone, $normalizedAddress) {
                    $q->where('phone_number', $cleanPhone)
                      ->orWhere('phone_number', $normalizedAddress);
                })
                ->exists();

            if ($isOptedOut) {
                return [
                    'allowed' => false,
                    'reason' => 'whatsapp_opt_out',
                    'message' => 'Recipient previously opted out of WhatsApp messages.',
                ];
            }
        }

        // 3. Format Validation
        if ($channel === 'email' && !filter_var($normalizedAddress, FILTER_VALIDATE_EMAIL)) {
            return [
                'allowed' => false,
                'reason' => 'invalid_email_format',
                'message' => 'Invalid email address syntax.',
            ];
        }

        if (($channel === 'whatsapp' || $channel === 'sms') && strlen($cleanPhone) < 7) {
            return [
                'allowed' => false,
                'reason' => 'invalid_phone_number',
                'message' => 'Invalid phone number format.',
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'message' => 'Recipient is eligible for communication.',
        ];
    }
}
