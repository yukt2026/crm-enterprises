<?php

namespace App\Http\Controllers\Api\V1\Webhook;

use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\Voice\Providers\TelephonyProviderManager;
use App\Domains\Voice\Services\VoiceCrmAutomationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TelephonyWebhookController extends Controller
{
    protected TelephonyProviderManager $providerManager;
    protected VoiceCrmAutomationService $crmAutomation;

    public function __construct(
        TelephonyProviderManager $providerManager,
        VoiceCrmAutomationService $crmAutomation
    ) {
        $this->providerManager = $providerManager;
        $this->crmAutomation = $crmAutomation;
    }

    /**
     * Exotel Inbound / Connect Voice Response (ExoML).
     */
    public function exotelConnect(Request $request): Response
    {
        $callId = $request->input('call_id');
        $call = $callId ? VoiceCall::find($callId) : null;
        $agent = $call?->agent ?: VoiceAiAgent::where('status', 'active')->first();

        $provider = $this->providerManager->resolveProvider($call?->tenant_id ?: 1, 'exotel');
        $phoneNumber = new TelephonyPhoneNumber(['phone_number' => $request->input('To', '+919876543210')]);

        $exoml = $provider->generateInboundResponse($phoneNumber, $agent ?: new VoiceAiAgent(), $request->all());

        return response($exoml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Exotel Status Callback (Reconciles duration, recording URL and triggers CRM sync).
     */
    public function exotelStatus(Request $request): Response
    {
        $payload = $request->all();
        $callId = $request->input('call_id');
        $callSid = $payload['CallSid'] ?? ($payload['Sid'] ?? null);

        $call = null;
        if ($callId) {
            $call = VoiceCall::find($callId);
        }
        if (!$call && $callSid) {
            $call = VoiceCall::where('provider_call_id', $callSid)->first();
        }

        if ($call) {
            $provider = $this->providerManager->resolveProvider($call->tenant_id, 'exotel');
            $parsed = $provider->parseWebhookPayload($payload);

            $call->status = $parsed['status'];
            $this->providerManager->reconcileCallCompletion($call, $parsed['duration_seconds'], $parsed['recording_url']);

            if (in_array($call->status, ['completed', 'busy', 'no-answer', 'failed'])) {
                $this->crmAutomation->processPostCallAutomation($call);
            }
        }

        return response('<Response/>', 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Twilio Inbound Voice Response (TwiML).
     */
    public function twilioVoice(Request $request): Response
    {
        $callId = $request->input('call_id');
        $call = $callId ? VoiceCall::find($callId) : null;
        $agent = $call?->agent ?: VoiceAiAgent::where('status', 'active')->first();

        $provider = $this->providerManager->resolveProvider($call?->tenant_id ?: 1, 'twilio');
        $phoneNumber = new TelephonyPhoneNumber(['phone_number' => $request->input('To', '+919876543210')]);

        $twiml = $provider->generateInboundResponse($phoneNumber, $agent ?: new VoiceAiAgent(), $request->all());

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Twilio Status Callback.
     */
    public function twilioStatus(Request $request): Response
    {
        $payload = $request->all();
        $callId = $request->input('call_id');
        $callSid = $payload['CallSid'] ?? null;

        $call = null;
        if ($callId) {
            $call = VoiceCall::find($callId);
        }
        if (!$call && $callSid) {
            $call = VoiceCall::where('provider_call_id', $callSid)->first();
        }

        if ($call) {
            $provider = $this->providerManager->resolveProvider($call->tenant_id, 'twilio');
            $parsed = $provider->parseWebhookPayload($payload);

            $call->status = $parsed['status'];
            $this->providerManager->reconcileCallCompletion($call, $parsed['duration_seconds'], $parsed['recording_url']);

            if (in_array($call->status, ['completed', 'busy', 'no-answer', 'failed'])) {
                $this->crmAutomation->processPostCallAutomation($call);
            }
        }

        return response('<Response/>', 200, ['Content-Type' => 'text/xml']);
    }
}
