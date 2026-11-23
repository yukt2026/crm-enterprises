<?php

namespace App\Domains\Omnichannel\Adapters;

use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;

interface ChannelAdapterInterface
{
    /**
     * Send an outbound message/email/SMS over this channel.
     */
    public function send(WhatsAppConversation $conversation, array $payload): WhatsAppMessage;

    /**
     * Process an inbound webhook or event payload into a message.
     */
    public function handleInbound(int $tenantId, array $payload): WhatsAppMessage;
}
