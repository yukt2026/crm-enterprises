<?php

namespace App\Domains\Omnichannel\Adapters;

use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Models\WebsiteChatWidget;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Str;

class WebChatChannelAdapter implements ChannelAdapterInterface
{
    public function send(WhatsAppConversation $conversation, array $payload): WhatsAppMessage
    {
        $tenantId = $conversation->tenant_id;
        $content = $payload['content'] ?? '';
        $messageId = 'wc_msg_' . Str::random(24);

        $message = WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'channel' => 'webchat',
            'provider_message_id' => $messageId,
            'direction' => 'outbound',
            'message_type' => 'text',
            'sender_actor' => $payload['sender_actor'] ?? 'human',
            'user_id' => $payload['user_id'] ?? auth()->id(),
            'sender_identity' => auth()->user()?->name ?? 'Support Agent',
            'recipient_identity' => $conversation->customer_name ?? 'Website Visitor',
            'content' => $content,
            'delivery_status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
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
        $widgetKey = $payload['widget_key'] ?? null;
        $widget = WebsiteChatWidget::where('tenant_id', $tenantId)
            ->where('widget_key', $widgetKey)
            ->where('is_active', true)
            ->first();

        $visitorId = $payload['visitor_id'] ?? ('vis_' . Str::random(16));
        $visitorName = $payload['name'] ?? 'Website Visitor';
        $visitorEmail = $payload['email'] ?? null;
        $visitorPhone = $payload['phone'] ?? 'N/A';
        $content = $payload['content'] ?? '';
        $requirement = $payload['requirement'] ?? null;

        // Auto-create or link CRM Lead if visitor provided contact info
        $lead = null;
        if ($visitorEmail || ($visitorPhone && $visitorPhone !== 'N/A')) {
            $lead = Lead::where('tenant_id', $tenantId)
                ->where(function ($q) use ($visitorEmail, $visitorPhone) {
                    if ($visitorEmail) {
                        $q->where('email', $visitorEmail);
                    }
                    if ($visitorPhone && $visitorPhone !== 'N/A') {
                        $q->orWhere('phone', $visitorPhone);
                    }
                })
                ->first();

            if (!$lead) {
                $lead = Lead::create([
                    'tenant_id' => $tenantId,
                    'name' => $visitorName,
                    'email' => $visitorEmail,
                    'phone' => $visitorPhone !== 'N/A' ? $visitorPhone : null,
                    'source' => 'Website Live Chat',
                    'status' => 'new',
                    'requirement' => $requirement ?? 'Live chat inquiry: ' . Str::limit($content, 80),
                ]);
            }
        }

        // Find or create webchat conversation
        $conversation = WhatsAppConversation::where('tenant_id', $tenantId)
            ->where('channel', 'webchat')
            ->where('channel_account_id', $visitorId)
            ->whereIn('status', ['open', 'pending'])
            ->latest('id')
            ->first();

        if (!$conversation) {
            $conversation = WhatsAppConversation::create([
                'tenant_id' => $tenantId,
                'channel' => 'webchat',
                'channel_account_id' => $visitorId,
                'customer_name' => $visitorName,
                'customer_email' => $visitorEmail,
                'phone_number' => $visitorPhone,
                'lead_id' => $lead ? $lead->id : null,
                'status' => 'open',
                'priority' => 'medium',
                'unread_count' => 1,
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($content, 120),
                'last_message_direction' => 'inbound',
                'metadata' => [
                    'widget_id' => $widget?->id,
                    'widget_name' => $widget?->name,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            ]);
        } else {
            $conversation->increment('unread_count');
            $conversation->update([
                'status' => 'open',
                'lead_id' => $lead ? $lead->id : $conversation->lead_id,
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($content, 120),
                'last_message_direction' => 'inbound',
            ]);
        }

        return WhatsAppMessage::create([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversation->id,
            'channel' => 'webchat',
            'provider_message_id' => 'wc_in_' . Str::random(20),
            'direction' => 'inbound',
            'message_type' => 'text',
            'sender_actor' => 'customer',
            'sender_identity' => $visitorName,
            'recipient_identity' => 'Support Desk',
            'content' => $content,
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'raw_payload' => $payload,
        ]);
    }
}
