<?php

namespace App\Domains\Omnichannel\Adapters;

use App\Domains\Omnichannel\Models\TelegramAccount;
use App\Domains\Omnichannel\Services\IdentityResolutionService;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramChannelAdapter implements ChannelAdapterInterface
{
    public function __construct(
        protected IdentityResolutionService $identityService
    ) {}

    /**
     * Send an outbound message over Telegram Bot API.
     */
    public function send(WhatsAppConversation $conversation, array $payload): WhatsAppMessage
    {
        $tenantId = $conversation->tenant_id;
        $chatId = $conversation->phone_number; // In Telegram, phone_number stores the Telegram chat ID

        // Retrieve connected Telegram Bot account
        $telegramAccount = TelegramAccount::where('tenant_id', $tenantId)
            ->where('is_connected', true)
            ->first();

        $botToken = $telegramAccount?->bot_token ?? config('services.telegram.bot_token', 'mock_telegram_token');

        $text = $payload['content'] ?? '';
        $mediaUrl = $payload['media_url'] ?? null;
        $providerMessageId = 'tg_msg_' . Str::random(16);

        // In test or non-production environment, skip network call if mock token
        if ($botToken && !Str::startsWith($botToken, 'mock_')) {
            try {
                if ($mediaUrl) {
                    $res = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendPhoto", [
                        'chat_id' => $chatId,
                        'photo' => $mediaUrl,
                        'caption' => $text,
                    ]);
                } else {
                    $res = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $text,
                        'parse_mode' => 'HTML',
                    ]);
                }

                if ($res->successful() && isset($res->json()['result']['message_id'])) {
                    $providerMessageId = (string) $res->json()['result']['message_id'];
                }
            } catch (\Throwable $e) {
                Log::warning('Telegram message dispatch failed, falling back to simulated provider ID', [
                    'error' => $e->getMessage(),
                    'chat_id' => $chatId,
                ]);
            }
        }

        $message = WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'channel' => 'telegram',
            'message_type' => $mediaUrl ? 'image' : 'text',
            'direction' => 'outbound',
            'delivery_status' => 'sent',
            'content' => $text,
            'media_url' => $mediaUrl,
            'provider_message_id' => $providerMessageId,
            'user_id' => $payload['user_id'] ?? null,
            'sender_identity' => $telegramAccount?->bot_username ?? 'TelegramBot',
            'recipient_identity' => (string) $chatId,
            'raw_payload' => ['provider' => 'telegram', 'chat_id' => $chatId],
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'last_message_preview' => Str::limit($text, 100),
            'last_message_direction' => 'outbound',
            'last_actor_type' => $payload['sender_actor'] ?? 'human',
        ]);

        return $message;
    }

    /**
     * Process an inbound Telegram webhook payload into an Omnichannel message.
     */
    public function handleInbound(int $tenantId, array $payload): WhatsAppMessage
    {
        $messageData = $payload['message'] ?? $payload;
        $chatId = (string) ($messageData['chat']['id'] ?? ($messageData['from']['id'] ?? 'unknown'));
        $senderFirstName = $messageData['from']['first_name'] ?? 'Telegram User';
        $senderLastName = $messageData['from']['last_name'] ?? '';
        $senderUsername = $messageData['from']['username'] ?? null;
        $fullName = trim("{$senderFirstName} {$senderLastName}");
        if ($senderUsername) {
            $fullName .= " (@{$senderUsername})";
        }

        $text = $messageData['text'] ?? ($messageData['caption'] ?? '');
        $mediaUrl = null;
        $messageType = 'text';

        if (isset($messageData['photo'])) {
            $messageType = 'image';
            $text = $text ?: '[Telegram Photo]';
        } elseif (isset($messageData['voice'])) {
            $messageType = 'voice';
            $text = $text ?: '[Telegram Voice Message]';
        } elseif (isset($messageData['document'])) {
            $messageType = 'document';
            $text = $text ?: '[Telegram Document]';
        }

        $providerMessageId = (string) ($messageData['message_id'] ?? ('tg_in_' . Str::random(12)));

        // 1. Resolve or create conversation
        $conversation = WhatsAppConversation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('channel', 'telegram')
            ->where('phone_number', $chatId)
            ->first();

        if (!$conversation) {
            // Identity resolution
            $identity = $this->identityService->resolveExactIdentity($tenantId, null, null);

            $conversation = WhatsAppConversation::create([
                'tenant_id' => $tenantId,
                'channel' => 'telegram',
                'phone_number' => $chatId,
                'customer_name' => $fullName,
                'customer_id' => $identity['customer_id'] ?? null,
                'contact_id' => $identity['contact_id'] ?? null,
                'lead_id' => $identity['lead_id'] ?? null,
                'status' => 'open',
                'unread_count' => 1,
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($text, 100),
                'last_message_direction' => 'inbound',
                'last_actor_type' => 'customer',
            ]);
        } else {
            // Reopen if resolved on new customer inbound
            $isResolved = $conversation->status === 'resolved';
            $conversation->update([
                'status' => $isResolved ? 'open' : $conversation->status,
                'unread_count' => ($conversation->unread_count ?? 0) + 1,
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($text, 100),
                'last_message_direction' => 'inbound',
                'last_actor_type' => 'customer',
            ]);
        }

        // 2. Persist message idempotently
        $message = WhatsAppMessage::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider_message_id' => $providerMessageId,
            ],
            [
                'conversation_id' => $conversation->id,
                'channel' => 'telegram',
                'message_type' => $messageType,
                'direction' => 'inbound',
                'delivery_status' => 'delivered',
                'content' => $text,
                'media_url' => $mediaUrl,
                'sender_identity' => (string) $chatId,
                'recipient_identity' => 'Bot',
                'raw_payload' => $payload,
            ]
        );

        return $message;
    }
}
