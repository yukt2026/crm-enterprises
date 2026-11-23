<?php

namespace App\Domains\Voice\Providers;

use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\TelephonyProvider;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioProvider implements TelephonyProviderInterface
{
    protected TelephonyProvider $providerModel;

    public function __construct(TelephonyProvider $providerModel)
    {
        $this->providerModel = $providerModel;
    }

    public function getProviderName(): string
    {
        return 'twilio';
    }

    public function initiateOutboundCall(VoiceCall $call, VoiceAiAgent $agent): array
    {
        $sid = $this->providerModel->account_sid;
        $token = $this->providerModel->auth_token;
        $callerId = $this->providerModel->caller_id ?: $call->from_number;

        if (!$token || str_starts_with($token, 'mock_') || app()->environment('testing')) {
            $providerCallId = 'CA' . md5(uniqid());
            return [
                'success' => true,
                'provider_call_id' => $providerCallId,
                'status' => 'in-progress',
                'message' => 'Twilio outbound call simulated successfully.',
            ];
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json";

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout(15)
                ->post($url, [
                    'From' => $callerId,
                    'To' => $call->to_number,
                    'Url' => url("/api/v1/telephony/webhooks/twilio/voice?call_id={$call->id}"),
                    'StatusCallback' => url("/api/v1/telephony/webhooks/twilio/status?call_id={$call->id}"),
                    'Record' => $this->providerModel->supports_recordings ? 'true' : 'false',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'provider_call_id' => $data['sid'] ?? ('CA' . md5(uniqid())),
                    'status' => 'ringing',
                ];
            }

            Log::error("Twilio Call Error: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Twilio Call Exception: " . $e->getMessage());
        }

        return [
            'success' => false,
            'provider_call_id' => 'CA_failed_' . uniqid(),
            'status' => 'failed',
            'error' => 'Twilio network dispatch failure',
        ];
    }

    public function generateInboundResponse(TelephonyPhoneNumber $phoneNumber, VoiceAiAgent $agent, array $params = []): string
    {
        $opening = htmlspecialchars($agent->call_opening ?: 'Hello! Welcome to our AI voice support. This call is recorded for quality assurance.');
        $voice = str_contains(strtolower($agent->language), 'hi') ? 'Polly.Aditi' : 'alice';
        $maxSecs = $agent->max_call_duration_seconds ?: 600;
        $callbackUrl = htmlspecialchars(url('/api/v1/telephony/webhooks/twilio/record?agent_id=' . $agent->id));

        return <<<TWIML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say voice="{$voice}">{$opening}</Say>
    <Record action="{$callbackUrl}" maxLength="{$maxSecs}" trim="trim-silence" />
</Response>
TWIML;
    }

    public function parseWebhookPayload(array $payload): array
    {
        $statusRaw = strtolower($payload['CallStatus'] ?? '');
        $status = match ($statusRaw) {
            'completed' => 'completed',
            'busy' => 'busy',
            'no-answer' => 'no-answer',
            'failed' => 'failed',
            'in-progress' => 'in-progress',
            'ringing' => 'ringing',
            default => 'completed',
        };

        return [
            'provider_call_id' => $payload['CallSid'] ?? null,
            'status' => $status,
            'duration_seconds' => (int) ($payload['CallDuration'] ?? ($payload['RecordingDuration'] ?? 0)),
            'recording_url' => $payload['RecordingUrl'] ?? null,
            'from' => $payload['From'] ?? null,
            'to' => $payload['To'] ?? null,
        ];
    }
}
