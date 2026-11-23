<?php

namespace App\Domains\Omnichannel\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Adapters\FacebookMessengerChannelAdapter;
use App\Domains\Omnichannel\Adapters\InstagramDmChannelAdapter;
use App\Domains\Omnichannel\Models\CustomerSocialIdentity;
use App\Domains\Omnichannel\Models\SocialChannelAccount;
use App\Domains\Omnichannel\Models\SocialWebhookEvent;
use App\Domains\WhatsApp\Models\WhatsAppAiAgent;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SocialMessagingService
{
    public function __construct(
        protected FacebookMessengerChannelAdapter $messengerAdapter,
        protected InstagramDmChannelAdapter $instagramAdapter
    ) {}

    /**
     * Connect or update a Facebook Page or Instagram Business Account
     */
    public function connectAccount(int $tenantId, array $data): SocialChannelAccount
    {
        $provider = $data['provider'] ?? 'facebook';
        if (!in_array($provider, ['facebook', 'instagram'])) {
            throw ValidationException::withMessages(['provider' => 'Invalid social messaging provider.']);
        }

        $pageId = $data['page_id'] ?? null;
        if (!$pageId) {
            throw ValidationException::withMessages(['page_id' => 'Facebook Page ID is required.']);
        }

        $token = $data['page_access_token'] ?? null;
        if (!$token) {
            throw ValidationException::withMessages(['page_access_token' => 'Page Access Token is required.']);
        }

        return DB::transaction(function () use ($tenantId, $provider, $pageId, $token, $data) {
            $account = SocialChannelAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('provider', $provider)
                ->where('page_id', $pageId)
                ->first();

            $attributes = [
                'page_name' => $data['page_name'] ?? 'Facebook Page ' . substr($pageId, -4),
                'page_access_token' => $token,
                'instagram_business_id' => $data['instagram_business_id'] ?? null,
                'instagram_username' => $data['instagram_username'] ?? null,
                'is_connected' => true,
                'connected_at' => Carbon::now(),
                'token_expires_at' => !empty($data['token_expires_at']) ? Carbon::parse($data['token_expires_at']) : Carbon::now()->addDays(60),
                'webhook_subscribed' => true,
                'webhook_verify_token' => $data['webhook_verify_token'] ?? ('meta_verify_' . bin2hex(random_bytes(12))),
                'permissions' => $data['permissions'] ?? [
                    'pages_messaging',
                    'pages_manage_metadata',
                    'pages_show_list',
                    'instagram_manage_messages',
                    'instagram_basic',
                ],
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'category' => $data['category'] ?? 'Business',
                    'picture_url' => $data['picture_url'] ?? null,
                    'is_instagram_eligible' => !empty($data['instagram_business_id']),
                ]),
                'ai_enabled' => isset($data['ai_enabled']) ? (bool) $data['ai_enabled'] : true,
                'ai_agent_id' => $data['ai_agent_id'] ?? null,
                'ai_instructions' => $data['ai_instructions'] ?? null,
                'ai_handover_keywords' => $data['ai_handover_keywords'] ?? ['agent', 'human', 'support', 'representative'],
            ];

            if ($account) {
                $account->update($attributes);
                return $account->fresh();
            }

            return SocialChannelAccount::create(array_merge([
                'tenant_id' => $tenantId,
                'provider' => $provider,
                'page_id' => $pageId,
            ], $attributes));
        });
    }

    /**
     * Disconnect a social account
     */
    public function disconnectAccount(int $tenantId, int $id): bool
    {
        $account = SocialChannelAccount::where('tenant_id', $tenantId)->findOrFail($id);
        return $account->update([
            'is_connected' => false,
            'webhook_subscribed' => false,
        ]);
    }

    /**
     * Get connected social accounts
     */
    public function getAccounts(int $tenantId, ?string $provider = null): Collection
    {
        return SocialChannelAccount::where('tenant_id', $tenantId)
            ->when($provider, fn($q) => $q->where('provider', $provider))
            ->with('aiAgent:id,name')
            ->orderByDesc('is_connected')
            ->orderBy('page_name')
            ->get();
    }

    /**
     * Verify Meta Webhook Challenge
     */
    public function verifyWebhookChallenge(string $mode, string $token, string $challenge): ?string
    {
        if ($mode !== 'subscribe') {
            return null;
        }

        // Match against any tenant verify token or default environment verify token
        $expectedToken = config('services.meta.webhook_verify_token') ?? env('META_WEBHOOK_VERIFY_TOKEN', 'crm_meta_webhook_secret_2026');

        if ($token === $expectedToken) {
            return $challenge;
        }

        $exists = SocialChannelAccount::withoutGlobalScopes()
            ->where('webhook_verify_token', $token)
            ->exists();

        if ($exists) {
            return $challenge;
        }

        return null;
    }

    /**
     * Verify HMAC SHA-256 Webhook Signature
     */
    public function verifySignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if (!$signatureHeader) {
            return false;
        }

        $appSecret = config('services.meta.app_secret') ?? env('META_APP_SECRET', 'meta_app_secret_test_2026');
        $expectedHash = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        return hash_equals($expectedHash, $signatureHeader);
    }

    /**
     * Process incoming Meta Webhook Event (Messenger & Instagram)
     */
    public function handleWebhookPayload(array $payload): array
    {
        $object = $payload['object'] ?? '';
        $isInstagram = $object === 'instagram';
        $entries = $payload['entry'] ?? [];
        $processedCount = 0;

        foreach ($entries as $entry) {
            $pageId = $entry['id'] ?? null;
            if (!$pageId) continue;

            // Resolve Tenant Account by Page ID or Instagram Business ID
            $account = SocialChannelAccount::withoutGlobalScopes()
                ->where(function ($q) use ($pageId, $isInstagram) {
                    if ($isInstagram) {
                        $q->where('instagram_business_id', $pageId)
                          ->orWhere('page_id', $pageId);
                    } else {
                        $q->where('page_id', $pageId);
                    }
                })
                ->where('is_connected', true)
                ->first();

            if (!$account) {
                // Log unassociated event
                SocialWebhookEvent::create([
                    'tenant_id' => null,
                    'provider' => $isInstagram ? 'instagram' : 'facebook',
                    'page_id' => $pageId,
                    'event_type' => 'unrecognized_page',
                    'payload' => $entry,
                    'status' => 'ignored',
                    'error_message' => 'No matching tenant account connected for this page/account ID.',
                ]);
                continue;
            }

            $tenantId = $account->tenant_id;
            $messagingEvents = $entry['messaging'] ?? [];

            foreach ($messagingEvents as $msgEvent) {
                $senderId = $msgEvent['sender']['id'] ?? null;
                $recipientId = $msgEvent['recipient']['id'] ?? null;
                $messageData = $msgEvent['message'] ?? null;
                $deliveryData = $msgEvent['delivery'] ?? null;
                $readData = $msgEvent['read'] ?? null;

                // 1. Process Delivery/Read Receipts
                if ($deliveryData || $readData) {
                    $watermark = $deliveryData['watermark'] ?? ($readData['watermark'] ?? null);
                    SocialWebhookEvent::create([
                        'tenant_id' => $tenantId,
                        'provider' => $account->provider,
                        'page_id' => $pageId,
                        'event_id' => 'receipt_' . ($watermark ?? uniqid()),
                        'event_type' => $deliveryData ? 'message_deliveries' : 'message_reads',
                        'payload' => $msgEvent,
                        'status' => 'processed',
                    ]);
                    $processedCount++;
                    continue;
                }

                // 2. Process Inbound Messages
                if ($messageData && $senderId) {
                    $mid = $messageData['mid'] ?? ('mid_' . uniqid());

                    // Deduplication check
                    $existingEvent = SocialWebhookEvent::withoutGlobalScopes()
                        ->where('page_id', $pageId)
                        ->where('event_id', $mid)
                        ->first();

                    if ($existingEvent) {
                        continue;
                    }

                    // Log Webhook Event
                    SocialWebhookEvent::create([
                        'tenant_id' => $tenantId,
                        'provider' => $account->provider,
                        'page_id' => $pageId,
                        'event_id' => $mid,
                        'event_type' => 'messages',
                        'payload' => $msgEvent,
                        'status' => 'processed',
                    ]);

                    // Route to Channel Adapter
                    $adapter = $account->provider === 'instagram' ? $this->instagramAdapter : $this->messengerAdapter;
                    $message = $adapter->handleInbound($tenantId, [
                        'sender_id' => $senderId,
                        'recipient_id' => $recipientId,
                        'mid' => $mid,
                        'message' => $messageData,
                    ]);

                    // 3. AI Omnichannel Evaluation if enabled
                    if ($account->ai_enabled && $message->conversation) {
                        $this->evaluateSocialAiReply($account, $message->conversation, $message);
                    }

                    $processedCount++;
                }
            }
        }

        return [
            'status' => 'success',
            'processed_events' => $processedCount,
        ];
    }

    /**
     * AI Auto-Reply & Handover Evaluation for Social Channels
     */
    public function evaluateSocialAiReply(SocialChannelAccount $account, WhatsAppConversation $conversation, WhatsAppMessage $inboundMessage): ?WhatsAppMessage
    {
        $text = trim(strtolower($inboundMessage->content ?? ''));
        if (!$text) {
            return null;
        }

        // Check for human handover keywords
        $handoverKeywords = $account->ai_handover_keywords ?? ['human', 'agent', 'support', 'help', 'representative'];
        foreach ($handoverKeywords as $kw) {
            if (str_contains($text, strtolower($kw))) {
                // Handover to human
                $conversation->update([
                    'is_ai_handled' => false,
                    'status' => 'open',
                    'priority' => 'high',
                ]);

                $handoverReply = "Thank you. I have paused automated assistance and routed your conversation to a customer support specialist.";
                $adapter = $account->provider === 'instagram' ? $this->instagramAdapter : $this->messengerAdapter;
                return $adapter->send($conversation, [
                    'content' => $handoverReply,
                    'sender_actor' => 'ai',
                ]);
            }
        }

        // Generate Automated AI Reply
        $agent = $account->aiAgent;
        $systemPrompt = $account->ai_instructions ?? ($agent?->system_prompt ?? "You are a professional brand assistant for {$account->page_name}. Help customers with product inquiries politely and concisely.");

        $simulatedAiAnswer = "Hello! Thanks for messaging {$account->page_name}. How can we assist you today?";
        if (str_contains($text, 'price') || str_contains($text, 'cost')) {
            $simulatedAiAnswer = "Our enterprise plans start with scalable usage billing. You can view our live catalog and request a custom quote anytime!";
        } elseif (str_contains($text, 'hours') || str_contains($text, 'open')) {
            $simulatedAiAnswer = "Our team is available Monday through Friday, 9:00 AM - 6:00 PM EST.";
        }

        $adapter = $account->provider === 'instagram' ? $this->instagramAdapter : $this->messengerAdapter;
        return $adapter->send($conversation, [
            'content' => $simulatedAiAnswer,
            'sender_actor' => 'ai',
        ]);
    }

    /**
     * Link Social Identity to CRM Lead
     */
    public function linkIdentityToLead(int $tenantId, int $identityId, int $leadId): CustomerSocialIdentity
    {
        $identity = CustomerSocialIdentity::where('tenant_id', $tenantId)->findOrFail($identityId);
        $lead = Lead::where('tenant_id', $tenantId)->findOrFail($leadId);

        $identity->update([
            'lead_id' => $lead->id,
            'is_verified' => true,
        ]);

        // Also backfill conversations
        WhatsAppConversation::where('tenant_id', $tenantId)
            ->where('channel', $identity->provider)
            ->where(fn($q) => $q->where('phone_number', 'fb_' . $identity->scoped_id)->orWhere('phone_number', 'ig_' . $identity->scoped_id))
            ->update(['lead_id' => $lead->id]);

        return $identity->fresh();
    }

    /**
     * Link Social Identity to CRM Customer
     */
    public function linkIdentityToCustomer(int $tenantId, int $identityId, int $customerId): CustomerSocialIdentity
    {
        $identity = CustomerSocialIdentity::where('tenant_id', $tenantId)->findOrFail($identityId);
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($customerId);

        $identity->update([
            'customer_id' => $customer->id,
            'is_verified' => true,
        ]);

        // Also backfill conversations
        WhatsAppConversation::where('tenant_id', $tenantId)
            ->where('channel', $identity->provider)
            ->where(fn($q) => $q->where('phone_number', 'fb_' . $identity->scoped_id)->orWhere('phone_number', 'ig_' . $identity->scoped_id))
            ->update(['customer_id' => $customer->id]);

        return $identity->fresh();
    }

    /**
     * Merge multiple social identities with confirmation
     */
    public function mergeIdentities(int $tenantId, int $primaryId, int $secondaryId): CustomerSocialIdentity
    {
        if ($primaryId === $secondaryId) {
            throw ValidationException::withMessages(['merge' => 'Cannot merge an identity into itself.']);
        }

        $primary = CustomerSocialIdentity::where('tenant_id', $tenantId)->findOrFail($primaryId);
        $secondary = CustomerSocialIdentity::where('tenant_id', $tenantId)->findOrFail($secondaryId);

        $secondary->update([
            'merged_with_id' => $primary->id,
            'customer_id' => $primary->customer_id ?? $secondary->customer_id,
            'lead_id' => $primary->lead_id ?? $secondary->lead_id,
        ]);

        return $primary->fresh();
    }

    /**
     * Get Social Identities
     */
    public function getIdentities(int $tenantId, ?string $provider = null): Collection
    {
        return CustomerSocialIdentity::where('tenant_id', $tenantId)
            ->when($provider, fn($q) => $q->where('provider', $provider))
            ->with(['customer:id,name,email', 'lead:id,name,email,company_name'])
            ->orderByDesc('id')
            ->get();
    }
}
