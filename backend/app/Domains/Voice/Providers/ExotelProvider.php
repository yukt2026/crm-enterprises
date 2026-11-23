<?php

namespace App\Domains\Voice\Providers;

use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\TelephonyProvider;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExotelProvider implements TelephonyProviderInterface
{
    protected TelephonyProvider $providerModel;

    public function __construct(TelephonyProvider $providerModel)
    {
        $this->providerModel = $providerModel;
    }

    public function getProviderName(): string
    {
        return 'exotel';
    }

    public function initiateOutboundCall(VoiceCall $call, VoiceAiAgent $agent): array
    {
        $sid = $this->providerModel->account_sid;
        $token = $this->providerModel->auth_token;
        $apiKey = $this->providerModel->api_key;
        $subdomain = $this->providerModel->subdomain ?: 'api.exotel.com';
        $callerId = $this->providerModel->caller_id ?: $call->from_number;

        // In test environment or mock keys, simulate telephony provider dispatch
        if (!$token || str_starts_with($token, 'mock_') || app()->environment('testing')) {
            $providerCallId = 'exo_call_' . uniqid();
            return [
                'success' => true,
                'provider_call_id' => $providerCallId,
                'status' => 'in-progress',
                'message' => 'Exotel outbound call simulated successfully.',
            ];
        }

        $url = "https://{$apiKey}:{$token}@{$subdomain}/v1/Accounts/{$sid}/Calls/connect.json";

        try {
            $response = Http::asForm()->timeout(15)->post($url, [
                'From' => $callerId,
                'To' => $call->to_number,
                'CallerId' => $callerId,
                'Url' => url("/api/v1/telephony/webhooks/exotel/connect?call_id={$call->id}"),
                'StatusCallback' => url("/api/v1/telephony/webhooks/exotel/status?call_id={$call->id}"),
                'Record' => $this->providerModel->supports_recordings ? 'true' : 'false',
            ]);

            if ($response->successful()) {
                $data = $response->json('Call') ?? [];
                return [
                    'success' => true,
                    'provider_call_id' => $data['Sid'] ?? ('exo_' . uniqid()),
                    'status' => 'ringing',
                ];
            }

            Log::error("Exotel Call Error: " . $response->body());
        } catch (\Exception $e) {
            Log::error("Exotel Call Exception: " . $e->getMessage());
        }

        return [
            'success' => false,
            'provider_call_id' => 'exo_failed_' . uniqid(),
            'status' => 'failed',
            'error' => 'Exotel network dispatch failure',
        ];
    }

    public function generateInboundResponse(TelephonyPhoneNumber $phoneNumber, VoiceAiAgent $agent, array $params = []): string
    {
        $opening = htmlspecialchars($agent->call_opening ?: 'Namaste! Welcome to our AI voice support. This call is recorded for quality purposes.');
        $maxSecs = $agent->max_call_duration_seconds ?: 600;
        $callbackUrl = htmlspecialchars(url('/api/v1/telephony/webhooks/exotel/record?agent_id=' . $agent->id));

        return <<<EXOML
<?xml version="1.0" encoding="UTF-8"?>
<Response>
    <Say>{$opening}</Say>
    <Record action="{$callbackUrl}" maxLength="{$maxSecs}" trim="true" />
</Response>
EXOML;
    }

    public function parseWebhookPayload(array $payload): array
    {
        $statusRaw = strtolower($payload['Status'] ?? ($payload['CallStatus'] ?? ''));
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
            'provider_call_id' => $payload['CallSid'] ?? ($payload['Sid'] ?? null),
            'status' => $status,
            'duration_seconds' => (int) ($payload['Duration'] ?? ($payload['RecordingDuration'] ?? 0)),
            'recording_url' => $payload['RecordingUrl'] ?? null,
            'from' => $payload['From'] ?? null,
            'to' => $payload['To'] ?? null,
        ];
    }
}
