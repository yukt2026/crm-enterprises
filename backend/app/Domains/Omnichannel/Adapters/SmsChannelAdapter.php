<?php

namespace App\Domains\Omnichannel\Adapters;

use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Omnichannel\Models\SmsConfiguration;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Str;

class SmsChannelAdapter implements ChannelAdapterInterface
{
    public function send(WhatsAppConversation $conversation, array $payload): WhatsAppMessage
    {
        $tenantId = $conversation->tenant_id;
        $recipient = $payload['to'] ?? $conversation->phone_number;

        if (!$recipient || $recipient === 'N/A') {
            throw new \InvalidArgumentException('Recipient phone number is required.');
        }

        // Clean phone number
        $cleanPhone = preg_replace('/[^\+0-9]/', '', $recipient);

        // Check suppression
        $isSuppressed = CommunicationSuppression::where('tenant_id', $tenantId)
            ->whereIn('channel', ['sms', 'all'])
            ->where('identifier', $cleanPhone)
            ->exists();

        if ($isSuppressed) {
            throw new \RuntimeException("Recipient phone {$cleanPhone} has opted out of SMS messages.");
        }

        // Retrieve active SMS configuration
        $smsConfig = SmsConfiguration::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        $senderId = $smsConfig ? $smsConfig->sender_id : 'CRMTXN';
        $provider = $smsConfig ? $smsConfig->provider : 'twilio';
        $messageId = 'sms_' . Str::random(24);

        $content = $payload['content'] ?? '';
        $templateId = $payload['dlt_template_id'] ?? null;

        $message = WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'channel' => 'sms',
            'provider_message_id' => $messageId,
            'direction' => 'outbound',
            'message_type' => 'sms',
            'sender_actor' => 'human',
            'user_id' => $payload['user_id'] ?? auth()->id(),
            'sender_identity' => $senderId,
            'recipient_identity' => $cleanPhone,
            'content' => $content,
            'delivery_status' => 'sent',
            'sent_at' => now(),
            'channel_metadata' => [
                'provider' => $provider,
                'sender_id' => $senderId,
                'dlt_entity_id' => $smsConfig->dlt_entity_id ?? null,
                'dlt_template_id' => $templateId,
            ],
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($content, 120),
            'last_message_direction' => 'outbound',
        ]);

        return $message;
    }

    public function handleInbound(int $tenantId, array $payload): WhatsAppMessage
    {
        $senderPhone = preg_replace('/[^\+0-9]/', '', $payload['from'] ?? '');
        $recipientPhone = $payload['to'] ?? '';
        $content = $payload['body'] ?? ($payload['content'] ?? '');
        $providerMessageId = $payload['message_id'] ?? ('sms_in_' . Str::random(20));

        // Find existing SMS conversation
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)
            ->where('channel', 'sms')
            ->where('phone_number', $senderPhone)
            ->whereIn('status', ['open', 'pending'])
            ->latest('id')
            ->first();

        if (!$conversation) {
            $conversation = WhatsAppConversation::create([
                'tenant_id' => $tenantId,
                'channel' => 'sms',
                'phone_number' => $senderPhone,
                'customer_name' => $payload['sender_name'] ?? $senderPhone,
                'status' => 'open',
                'priority' => 'medium',
                'unread_count' => 1,
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($content, 120),
                'last_message_direction' => 'inbound',
            ]);
        } else {
            $conversation->increment('unread_count');
            $conversation->update([
                'status' => 'open',
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($content, 120),
                'last_message_direction' => 'inbound',
            ]);
        }

        return WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'channel' => 'sms',
            'provider_message_id' => $providerMessageId,
            'direction' => 'inbound',
            'message_type' => 'sms',
            'sender_actor' => 'customer',
            'sender_identity' => $senderPhone,
            'recipient_identity' => $recipientPhone,
            'content' => $content,
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'raw_payload' => $payload,
        ]);
    }
}
