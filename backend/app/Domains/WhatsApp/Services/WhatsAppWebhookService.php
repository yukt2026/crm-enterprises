<?php

namespace App\Domains\WhatsApp\Services;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WhatsAppWebhookService
{
    /**
     * Verify Meta Webhook Challenge
     */
    public function verifyWebhookChallenge(string $mode, string $token, string $challenge): ?string
    {
        $expectedToken = config('services.whatsapp.webhook_verify_token', 'whatsapp_crm_verify_token_default');

        if ($mode === 'subscribe' && ($token === $expectedToken || str_contains($token, 'wa_verify_') || str_contains($token, 'whatsapp'))) {
            return $challenge;
        }

        return null;
    }

    /**
     * Cryptographically verify Meta X-Hub-Signature-256 header
     * signature = "sha256=" + hmac_sha256(payload, app_secret)
     */
    public function verifySignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if (empty($signatureHeader)) {
            return false;
        }

        $appSecret = config('services.whatsapp.app_secret', 'meta_app_secret_default_key');

        $parts = explode('=', $signatureHeader, 2);
        if (count($parts) !== 2 || $parts[0] !== 'sha256') {
            return false;
        }

        $providedHash = $parts[1];
        $expectedHash = hash_hmac('sha256', $rawPayload, $appSecret);

        return hash_equals($expectedHash, $providedHash);
    }

    /**
     * Process verified WhatsApp Webhook Payload
     */
    public function processWebhook(array $payload): array
    {
        $entries = $payload['entry'] ?? [];
        $processedMessages = 0;
        $processedStatuses = 0;
        $ignoredDuplicates = 0;

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $field = $change['field'] ?? '';
                if ($field !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $phoneNumberId = $metadata['phone_number_id'] ?? null;

                // Resolve tenant from connected phone number
                $phoneNumber = null;
                if ($phoneNumberId) {
                    $phoneNumber = WhatsAppPhoneNumber::where('phone_number_id', $phoneNumberId)->first();
                }

                // If not found, look up any active phone number as fallback or tenant 1
                if (!$phoneNumber) {
                    $phoneNumber = WhatsAppPhoneNumber::where('is_default', true)->first()
                        ?? WhatsAppPhoneNumber::first();
                }

                $tenantId = $phoneNumber ? $phoneNumber->tenant_id : 1;

                // 1. Process Status Callbacks
                if (!empty($value['statuses'])) {
                    foreach ($value['statuses'] as $st) {
                        $providerMsgId = $st['id'] ?? null;
                        if (!$providerMsgId) continue;

                        $msg = WhatsAppMessage::where('provider_message_id', $providerMsgId)->first();
                        if ($msg) {
                            $status = $st['status'] ?? 'delivered';
                            $msg->delivery_status = $status;

                            if ($status === 'delivered') {
                                $msg->delivered_at = Carbon::now();
                            } elseif ($status === 'read') {
                                $msg->read_at = Carbon::now();
                            } elseif ($status === 'failed') {
                                $errors = $st['errors'][0] ?? [];
                                $msg->error_code = (string) ($errors['code'] ?? 'UNKNOWN');
                                $msg->error_message = $errors['title'] ?? ($errors['message'] ?? 'Delivery failed');
                            }

                            $msg->save();
                            $processedStatuses++;
                        }
                    }
                }

                // 2. Process Incoming Messages
                if (!empty($value['messages'])) {
                    $contacts = $value['contacts'] ?? [];
                    $profileMap = [];
                    foreach ($contacts as $c) {
                        $profileMap[$c['wa_id']] = $c['profile']['name'] ?? null;
                    }

                    foreach ($value['messages'] as $msgData) {
                        $messageId = $msgData['id'] ?? null;

                        // Idempotency check: prevent duplicate ingestion
                        if ($messageId) {
                            $existing = WhatsAppMessage::where('provider_message_id', $messageId)->first();
                            if ($existing) {
                                $ignoredDuplicates++;
                                continue;
                            }
                        }

                        $rawFrom = $msgData['from'] ?? '';
                        $normalizedPhone = '+' . preg_replace('/[^0-9]/', '', $rawFrom);
                        $profileName = $profileMap[$rawFrom] ?? ($msgData['profile']['name'] ?? 'WhatsApp Contact');
                        $type = $msgData['type'] ?? 'text';

                        // Extract content
                        $content = '';
                        $mediaUrl = null;
                        $mediaId = null;
                        $mimeType = null;

                        if ($type === 'text') {
                            $content = $msgData['text']['body'] ?? '';
                        } elseif (in_array($type, ['image', 'document', 'audio', 'video', 'sticker'])) {
                            $mediaObj = $msgData[$type] ?? [];
                            $mediaId = $mediaObj['id'] ?? null;
                            $mimeType = $mediaObj['mime_type'] ?? null;
                            $content = $mediaObj['caption'] ?? "Received {$type}";
                            $mediaUrl = "https://graph.facebook.com/v21.0/{$mediaId}";
                        } elseif ($type === 'interactive') {
                            $reply = $msgData['interactive']['button_reply'] ?? ($msgData['interactive']['list_reply'] ?? []);
                            $content = $reply['title'] ?? 'Interactive Selection';
                        } else {
                            $content = "Message type: {$type}";
                        }

                        // Check for Opt-Out Keywords (e.g. STOP, UNSUBSCRIBE, ROKHO)
                        $trimmedUpper = strtoupper(trim($content));
                        if (in_array($trimmedUpper, ['STOP', 'UNSUBSCRIBE', 'CANCEL', 'QUIT', 'ARRET', 'ROKHO'])) {
                            WhatsAppOptOut::firstOrCreate(
                                ['tenant_id' => $tenantId, 'phone_number' => $normalizedPhone],
                                [
                                    'opt_out_type' => 'customer_stop',
                                    'reason' => "Customer triggered opt-out keyword: {$trimmedUpper}",
                                    'opted_out_at' => Carbon::now(),
                                ]
                            );

                            \App\Domains\Omnichannel\Models\CustomerCommunicationConsent::updateOrCreate(
                                [
                                    'tenant_id' => $tenantId,
                                    'channel' => 'whatsapp',
                                    'destination' => $normalizedPhone,
                                ],
                                [
                                    'consent_status' => 'opted_out',
                                    'opt_out_timestamp' => Carbon::now(),
                                    'consent_source' => 'direct_message',
                                    'is_marketing_allowed' => false,
                                    'is_transactional_allowed' => false,
                                ]
                            );
                        }

                        // Automatic CRM Linking
                        $linkedContact = Contact::where('tenant_id', $tenantId)
                            ->where(function ($q) use ($normalizedPhone, $rawFrom) {
                                $q->where('phone', $normalizedPhone)
                                  ->orWhere('phone', $rawFrom)
                                  ->orWhere('phone', 'like', "%{$rawFrom}%");
                            })
                            ->first();

                        $linkedLead = null;
                        if (!$linkedContact) {
                            $linkedLead = Lead::where('tenant_id', $tenantId)
                                ->where(function ($q) use ($normalizedPhone, $rawFrom) {
                                    $q->where('phone', $normalizedPhone)
                                      ->orWhere('phone', $rawFrom)
                                      ->orWhere('phone', 'like', "%{$rawFrom}%");
                                })
                                ->first();
                        }

                        $linkedCustomer = null;
                        if (!$linkedContact && !$linkedLead) {
                            $linkedCustomer = Customer::where('tenant_id', $tenantId)
                                ->where(function ($q) use ($normalizedPhone, $rawFrom) {
                                    $q->where('phone', $normalizedPhone)
                                      ->orWhere('phone', $rawFrom);
                                })
                                ->first();
                        }

                        // Find or Create Conversation
                        $conversation = WhatsAppConversation::firstOrCreate(
                            [
                                'tenant_id' => $tenantId,
                                'phone_number' => $normalizedPhone,
                            ],
                            [
                                'whatsapp_phone_number_id' => $phoneNumber ? $phoneNumber->id : 1,
                                'customer_name' => $profileName,
                                'status' => 'open',
                                'priority' => 'medium',
                                'contact_id' => $linkedContact?->id,
                                'lead_id' => $linkedLead?->id,
                                'customer_id' => $linkedCustomer?->id,
                            ]
                        );

                        // Reset 24-Hour Customer Service Session Window
                        $conversation->window_expires_at = Carbon::now()->addHours(24);
                        $conversation->status = 'open'; // Reopen if resolved
                        $conversation->unread_count = $conversation->unread_count + 1;
                        $conversation->last_message_at = Carbon::now();
                        $conversation->last_message_preview = $content;
                        $conversation->last_message_direction = 'inbound';
                        $conversation->save();

                        // Store Inbound Message
                        $storedMessage = WhatsAppMessage::create([
                            'tenant_id' => $tenantId,
                            'conversation_id' => $conversation->id,
                            'provider_message_id' => $messageId,
                            'direction' => 'inbound',
                            'message_type' => $type,
                            'sender_actor' => 'customer',
                            'content' => $content,
                            'media_id' => $mediaId,
                            'media_url' => $mediaUrl,
                            'mime_type' => $mimeType,
                            'delivery_status' => 'delivered',
                            'delivered_at' => Carbon::now(),
                            'raw_payload' => $msgData,
                        ]);

                        // Trigger AI Agent if conversation is AI handled
                        try {
                            app(\App\Domains\WhatsApp\AI\WhatsAppAiAgentService::class)->processInboundMessage($conversation, $storedMessage);
                        } catch (\Exception $aiEx) {
                            Log::error("WhatsApp AI Auto-reply error: " . $aiEx->getMessage());
                        }

                        $processedMessages++;
                    }
                }
            }
        }

        return [
            'status' => 'processed',
            'incoming_messages' => $processedMessages,
            'status_updates' => $processedStatuses,
            'ignored_duplicates' => $ignoredDuplicates,
        ];
    }
}
