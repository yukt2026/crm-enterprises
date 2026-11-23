<?php

namespace App\Domains\Omnichannel\Adapters;

use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Omnichannel\Models\EmailAccount;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Str;

class EmailChannelAdapter implements ChannelAdapterInterface
{
    public function send(WhatsAppConversation $conversation, array $payload): WhatsAppMessage
    {
        $tenantId = $conversation->tenant_id;
        $recipient = $payload['to'] ?? $conversation->customer_email;

        if (!$recipient) {
            throw new \InvalidArgumentException('Recipient email address is required.');
        }

        // Check suppression
        $isSuppressed = CommunicationSuppression::where('tenant_id', $tenantId)
            ->whereIn('channel', ['email', 'all'])
            ->where('identifier', strtolower(trim($recipient)))
            ->exists();

        if ($isSuppressed) {
            throw new \RuntimeException("Recipient email {$recipient} is on the suppression list (unsubscribed or bounced).");
        }

        // Find active email account or default
        $emailAccount = EmailAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        $senderEmail = $emailAccount ? $emailAccount->email_address : 'notifications@crm-enterprises.com';
        $messageId = '<' . Str::uuid() . '@' . parse_url(config('app.url', 'crm-enterprises.com'), PHP_URL_HOST) . '>';

        // Threading: In-Reply-To
        $lastMessage = $conversation->messages()->latest('id')->first();
        $inReplyTo = $payload['in_reply_to'] ?? ($lastMessage ? $lastMessage->provider_message_id : null);

        $subject = $payload['subject'] ?? ($conversation->subject ?? 'Re: Support Request');
        $content = $payload['content'] ?? '';
        $attachments = $payload['attachments'] ?? [];

        // Append signature if configured
        if ($emailAccount && !empty($emailAccount->signature_html)) {
            $content .= "\n\n" . $emailAccount->signature_html;
        }

        $message = WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'channel' => 'email',
            'provider_message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'direction' => 'outbound',
            'message_type' => 'email',
            'subject' => $subject,
            'sender_actor' => 'human',
            'user_id' => $payload['user_id'] ?? auth()->id(),
            'sender_identity' => $senderEmail,
            'recipient_identity' => $recipient,
            'content' => $content,
            'attachments' => $attachments,
            'delivery_status' => 'sent',
            'sent_at' => now(),
            'raw_payload' => [
                'provider' => $emailAccount->provider ?? 'smtp',
                'message_id' => $messageId,
                'in_reply_to' => $inReplyTo,
            ],
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($content, 120),
            'last_message_direction' => 'outbound',
            'subject' => $subject,
        ]);

        return $message;
    }

    public function handleInbound(int $tenantId, array $payload): WhatsAppMessage
    {
        $senderEmail = strtolower(trim($payload['from'] ?? 'unknown@domain.com'));
        $recipientEmail = strtolower(trim($payload['to'] ?? 'support@domain.com'));
        $subject = $payload['subject'] ?? 'New Email Inquiry';
        $content = $payload['body'] ?? ($payload['content'] ?? '');
        $inReplyTo = $payload['in_reply_to'] ?? null;
        $providerMessageId = $payload['message_id'] ?? ('<' . Str::uuid() . '@inbound>');
        $attachments = $payload['attachments'] ?? [];

        // Check if there's an existing conversation by in_reply_to
        $conversation = null;
        if ($inReplyTo) {
            $parentMsg = WhatsAppMessage::where('tenant_id', $tenantId)
                ->where('provider_message_id', $inReplyTo)
                ->first();
            if ($parentMsg) {
                $conversation = $parentMsg->conversation;
            }
        }

        // If not found by in_reply_to, match open email conversation with same sender
        if (!$conversation) {
            $conversation = WhatsAppConversation::where('tenant_id', $tenantId)
                ->where('channel', 'email')
                ->where('customer_email', $senderEmail)
                ->whereIn('status', ['open', 'pending'])
                ->latest('id')
                ->first();
        }

        // If still no conversation, create new conversation
        if (!$conversation) {
            $conversation = WhatsAppConversation::create([
                'tenant_id' => $tenantId,
                'channel' => 'email',
                'customer_email' => $senderEmail,
                'customer_name' => $payload['from_name'] ?? $senderEmail,
                'phone_number' => $payload['from_phone'] ?? 'N/A',
                'subject' => $subject,
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
            'channel' => 'email',
            'provider_message_id' => $providerMessageId,
            'in_reply_to' => $inReplyTo,
            'direction' => 'inbound',
            'message_type' => 'email',
            'subject' => $subject,
            'sender_actor' => 'customer',
            'sender_identity' => $senderEmail,
            'recipient_identity' => $recipientEmail,
            'content' => $content,
            'attachments' => $attachments,
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'raw_payload' => $payload,
        ]);
    }
}
