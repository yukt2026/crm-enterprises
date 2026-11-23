<?php

namespace App\Domains\Voice\Providers;

use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;

interface TelephonyProviderInterface
{
    /**
     * Initiate an outbound call to customer via provider REST API.
     */
    public function initiateOutboundCall(VoiceCall $call, VoiceAiAgent $agent): array;

    /**
     * Generate response markup (ExoML or TwiML) for inbound or connected call leg.
     */
    public function generateInboundResponse(TelephonyPhoneNumber $phoneNumber, VoiceAiAgent $agent, array $params = []): string;

    /**
     * Parse provider webhook payload for call events (answered, duration, recording).
     */
    public function parseWebhookPayload(array $payload): array;

    public function getProviderName(): string;
}
