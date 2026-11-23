<?php

namespace App\Domains\Voice\Providers;

use App\Models\User;
use App\Domains\Voice\Models\TelephonyOptOut;
use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\TelephonyProvider;
use App\Domains\Voice\Models\TelephonyProviderLog;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TelephonyProviderManager
{
    /**
     * Resolve provider adapter instance.
     */
    public function resolveProvider(int $tenantId, ?string $preferredProvider = null): TelephonyProviderInterface
    {
        $query = TelephonyProvider::where('tenant_id', $tenantId)->where('is_active', true);
        if ($preferredProvider) {
            $query->where('provider', $preferredProvider);
        }

        $model = $query->first();

        if (!$model) {
            // Default placeholder provider for tenant
            $model = new TelephonyProvider([
                'tenant_id' => $tenantId,
                'provider' => $preferredProvider ?: 'exotel',
                'name' => 'Default Provider',
                'auth_token' => 'mock_token',
                'account_sid' => 'mock_sid',
                'caller_id' => '+919876543210',
                'supports_recordings' => true,
            ]);
        }

        return match ($model->provider) {
            'twilio' => new TwilioProvider($model),
            default => new ExotelProvider($model),
        };
    }

    /**
     * Check regulatory compliance before outbound call (DND check & Calling Hours).
     */
    public function validateOutboundCompliance(int $tenantId, string $toNumber, ?VoiceAiAgent $agent = null): void
    {
        // 1. DND / Opt-Out Suppression List Check
        $isOptedOut = TelephonyOptOut::where('tenant_id', $tenantId)
            ->where('phone_number', $toNumber)
            ->exists();

        if ($isOptedOut) {
            throw new \DomainException("Outbound call blocked: Number {$toNumber} is registered in the DND / Opt-Out suppression list.");
        }

        // 2. Monthly Minutes Budget Check
        if ($agent && !$agent->hasCallingMinutesAvailable()) {
            throw new \DomainException("Outbound call blocked: Voice Agent [{$agent->name}] has reached its monthly calling minutes budget.");
        }

        // 3. TRAI / India Telecom Window Check (9 AM to 9 PM IST for +91 numbers)
        if (str_starts_with($toNumber, '+91') && !app()->environment('testing')) {
            $currentHourIst = Carbon::now('Asia/Kolkata')->hour;
            if ($currentHourIst < 9 || $currentHourIst >= 21) {
                throw new \DomainException("Regulatory Policy Violation: Telecom regulations (TRAI/DoT) prohibit automated outbound calls before 9:00 AM or after 9:00 PM IST.");
            }
        }
    }

    /**
     * Place an outbound AI call.
     */
    public function dispatchCall(VoiceCall $call, VoiceAiAgent $agent): VoiceCall
    {
        $this->validateOutboundCompliance($call->tenant_id, $call->to_number, $agent);

        $providerAdapter = $this->resolveProvider($call->tenant_id, $call->provider);

        try {
            $result = $providerAdapter->initiateOutboundCall($call, $agent);

            $call->provider_call_id = $result['provider_call_id'] ?? null;
            $call->status = $result['status'] ?? 'ringing';
            $call->call_type = 'ai_voice';
            $call->start_time = Carbon::now();
            $call->save();

            // Log provider event
            TelephonyProviderLog::create([
                'tenant_id' => $call->tenant_id,
                'provider' => $call->provider,
                'provider_call_id' => $call->provider_call_id,
                'event_type' => 'outbound_ai_call_dispatched',
                'status' => 'success',
                'payload' => [
                    'call_id' => $call->id,
                    'to' => $call->to_number,
                    'agent_id' => $agent->id,
                ],
            ]);

            return $call;
        } catch (\Throwable $e) {
            TelephonyProviderLog::create([
                'tenant_id' => $call->tenant_id,
                'provider' => $call->provider,
                'provider_call_id' => $call->provider_call_id,
                'event_type' => 'outbound_call_failed',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'payload' => ['to' => $call->to_number],
            ]);
            throw $e;
        }
    }

    /**
     * Place an outbound direct human agent click-to-call.
     */
    public function dispatchHumanCall(VoiceCall $call, User $user, ?string $callerId = null): VoiceCall
    {
        $this->validateOutboundCompliance($call->tenant_id, $call->to_number, null);

        $providerAdapter = $this->resolveProvider($call->tenant_id, $call->provider);

        $dummyAgent = new VoiceAiAgent([
            'tenant_id' => $call->tenant_id,
            'name' => 'Human Agent Direct Call',
            'call_opening' => 'Connecting you with an enterprise representative.',
        ]);

        try {
            $result = $providerAdapter->initiateOutboundCall($call, $dummyAgent);

            $call->provider_call_id = $result['provider_call_id'] ?? ('call_' . uniqid());
            $call->status = $result['status'] ?? 'ringing';
            $call->call_type = 'human';
            $call->assigned_user_id = $user->id;
            $call->start_time = Carbon::now();
            $call->recording_notice_played = true;
            $call->save();

            TelephonyProviderLog::create([
                'tenant_id' => $call->tenant_id,
                'provider' => $call->provider,
                'provider_call_id' => $call->provider_call_id,
                'event_type' => 'outbound_human_call_dispatched',
                'status' => 'success',
                'payload' => [
                    'call_id' => $call->id,
                    'to' => $call->to_number,
                    'user_id' => $user->id,
                ],
            ]);

            return $call;
        } catch (\Throwable $e) {
            TelephonyProviderLog::create([
                'tenant_id' => $call->tenant_id,
                'provider' => $call->provider,
                'provider_call_id' => $call->provider_call_id,
                'event_type' => 'outbound_human_call_failed',
                'status' => 'error',
                'error_message' => $e->getMessage(),
                'payload' => ['to' => $call->to_number, 'user_id' => $user->id],
            ]);
            throw $e;
        }
    }

    /**
     * Reconcile call duration and update billing/metering.
     */
    public function reconcileCallCompletion(VoiceCall $call, int $durationSeconds, ?string $recordingUrl = null): void
    {
        $call->duration_seconds = $durationSeconds;
        $call->end_time = Carbon::now();
        if ($recordingUrl) {
            $call->recording_url = $recordingUrl;
            $call->recording_duration_seconds = $durationSeconds;
        }

        // Calculate call cost (e.g. ₹1.20 per minute or fraction thereof)
        $durationMinutes = (int) ceil($durationSeconds / 60);
        $ratePerMinute = ($call->provider === 'exotel') ? 1.20 : 1.50;
        $call->cost_amount = round($durationMinutes * $ratePerMinute, 4);
        $call->cost_currency = 'INR';
        $call->save();

        // Increment agent used minutes
        if ($call->voice_agent_id) {
            VoiceAiAgent::where('id', $call->voice_agent_id)
                ->increment('used_call_minutes_this_month', $durationMinutes);
        }
    }
}
