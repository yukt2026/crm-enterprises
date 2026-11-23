<?php

namespace App\Domains\Voice\Services;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Voice\Models\CallIvrFlow;
use App\Domains\Voice\Models\CallQueue;
use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class IncomingCallManagerService
{
    /**
     * Normalize phone number to standard 10-digit and E.164 (+91) format.
     */
    public function normalizePhoneNumber(string $phone): array
    {
        $clean = preg_replace('/[^\d+]/', '', $phone);
        $rawDigits = preg_replace('/[^\d]/', '', $clean);

        // Extract last 10 digits for Indian mobile numbers
        $tenDigit = strlen($rawDigits) >= 10 ? substr($rawDigits, -10) : $rawDigits;
        $e164 = str_starts_with($clean, '+') ? $clean : '+91' . $tenDigit;

        return [
            'raw' => $phone,
            'clean' => $clean,
            'ten_digit' => $tenDigit,
            'e164' => $e164,
        ];
    }

    /**
     * Identify caller from CRM databases (Contacts, Leads, Companies, Deals, Tickets).
     */
    public function identifyCaller(int $tenantId, string $callerNumber): array
    {
        $norm = $this->normalizePhoneNumber($callerNumber);
        $tenDigit = $norm['ten_digit'];
        $e164 = $norm['e164'];

        // 1. Look up Contact
        $contact = Contact::where('tenant_id', $tenantId)
            ->where(function ($q) use ($tenDigit, $e164) {
                $q->where('phone', 'like', "%{$tenDigit}")
                  ->orWhere('phone', $e164);
            })
            ->with(['company'])
            ->first();

        // 2. Look up Lead
        $lead = null;
        if (!$contact) {
            $lead = Lead::where('tenant_id', $tenantId)
                ->where(function ($q) use ($tenDigit, $e164) {
                    $q->where('phone', 'like', "%{$tenDigit}")
                      ->orWhere('phone', $e164);
                })
                ->with(['assignedUser', 'company'])
                ->first();
        }

        $customerName = $contact?->name ?: ($lead?->name ?: 'Unknown Caller');
        $customerEmail = $contact?->email ?: ($lead?->email ?: null);
        $companyName = $contact?->company?->name ?: ($lead?->company_name ?: null);
        $assignedUser = $lead?->assignedUser ?: null;

        // 3. Open Deals
        $openDeals = [];
        if ($contact) {
            $openDeals = Deal::where('tenant_id', $tenantId)
                ->where('contact_id', $contact->id)
                ->whereNotIn('stage', ['won', 'lost'])
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get(['id', 'name', 'value', 'stage'])
                ->toArray();
        } elseif ($lead) {
            $openDeals = Deal::where('tenant_id', $tenantId)
                ->where('lead_id', $lead->id)
                ->whereNotIn('stage', ['won', 'lost'])
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get(['id', 'name', 'value', 'stage'])
                ->toArray();
        }

        // 4. Pending Follow-ups
        $pendingFollowups = [];
        if ($lead) {
            try {
                $pendingFollowups = LeadFollowup::where('tenant_id', $tenantId)
                    ->where('lead_id', $lead->id)
                    ->where('status', 'scheduled')
                    ->orderBy('scheduled_at', 'asc')
                    ->take(3)
                    ->get(['id', 'followup_type', 'scheduled_at', 'status'])
                    ->toArray();
            } catch (\Throwable $e) {
                $pendingFollowups = [];
            }
        }

        // 5. Open Support Tickets
        $openTickets = [];
        try {
            $ticketQuery = SupportTicket::where('tenant_id', $tenantId)->whereNotIn('status', ['closed', 'resolved']);
            if ($contact) {
                $ticketQuery->where('contact_id', $contact->id);
            } elseif ($lead) {
                $ticketQuery->where('requester_email', $lead->email);
            } else {
                $ticketQuery->where('id', 0);
            }
            $openTickets = $ticketQuery->take(3)->get(['id', 'ticket_number', 'subject', 'priority', 'status'])->toArray();
        } catch (\Throwable $e) {
            $openTickets = [];
        }

        // 6. Recent Call History
        $recentCalls = VoiceCall::where('tenant_id', $tenantId)
            ->where(function ($q) use ($tenDigit, $e164) {
                $q->where('from_number', 'like', "%{$tenDigit}")
                  ->orWhere('to_number', 'like', "%{$tenDigit}")
                  ->orWhere('from_number', $e164)
                  ->orWhere('to_number', $e164);
            })
            ->orderBy('id', 'desc')
            ->take(5)
            ->get(['id', 'direction', 'duration_seconds', 'status', 'disposition', 'created_at'])
            ->toArray();

        $isKnown = !empty($contact) || !empty($lead);

        return [
            'is_known_customer' => $isKnown,
            'caller_number' => $callerNumber,
            'normalized_phone' => $e164,
            'contact' => $contact,
            'lead' => $lead,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'company_name' => $companyName,
            'assigned_user' => $assignedUser ? [
                'id' => $assignedUser->id,
                'name' => $assignedUser->name,
                'email' => $assignedUser->email,
            ] : null,
            'open_deals' => $openDeals,
            'pending_followups' => $pendingFollowups,
            'open_tickets' => $openTickets,
            'recent_calls' => $recentCalls,
            'quick_create_suggestion' => !$isKnown ? [
                'phone' => $e164,
                'suggested_name' => 'Inbound Caller (' . substr($tenDigit, -4) . ')',
                'source' => 'phone_call',
            ] : null,
        ];
    }

    /**
     * Process an inbound call through IVR flow or direct routing with deduplication.
     */
    public function handleIncomingCall(
        int $tenantId,
        string $fromNumber,
        string $toNumber,
        string $provider = 'exotel',
        ?string $providerCallId = null,
        ?string $idempotencyKey = null
    ): array {
        // 1. Idempotency Check (avoid duplicate call logs from webhook retries)
        $idKey = $idempotencyKey ?: ($providerCallId ? "prov_{$providerCallId}" : null);
        if ($idKey) {
            $existing = VoiceCall::where('tenant_id', $tenantId)
                ->where(function ($q) use ($idKey, $providerCallId) {
                    $q->where('idempotency_key', $idKey);
                    if ($providerCallId) {
                        $q->orWhere('provider_call_id', $providerCallId);
                    }
                })->first();

            if ($existing) {
                return [
                    'call' => $existing,
                    'is_duplicate' => true,
                    'action' => 'reuse_existing',
                ];
            }
        }

        // 2. Identify Caller
        $callerProfile = $this->identifyCaller($tenantId, $fromNumber);

        // 3. Resolve Inbound Number & IVR Configuration
        $phoneModel = TelephonyPhoneNumber::where('tenant_id', $tenantId)
            ->where(function ($q) use ($toNumber) {
                $q->where('phone_number', $toNumber)
                  ->orWhere('phone_number', 'like', '%' . substr($toNumber, -10));
            })
            ->first();

        $ivrFlow = null;
        if ($phoneModel) {
            $ivrFlow = CallIvrFlow::where('tenant_id', $tenantId)
                ->where('phone_number_id', $phoneModel->id)
                ->where('is_active', true)
                ->first();
        }

        if (!$ivrFlow) {
            $ivrFlow = CallIvrFlow::where('tenant_id', $tenantId)->where('is_active', true)->first();
        }

        // 4. Determine Routing Action & Business Hours
        $routingDecision = $this->evaluateIvrRouting($ivrFlow, $callerProfile);

        // 5. Create Inbound VoiceCall Record
        $voiceCall = VoiceCall::create([
            'tenant_id' => $tenantId,
            'voice_agent_id' => $routingDecision['agent_id'] ?? null,
            'lead_id' => $callerProfile['lead']?->id,
            'contact_id' => $callerProfile['contact']?->id,
            'deal_id' => !empty($callerProfile['open_deals']) ? $callerProfile['open_deals'][0]['id'] : null,
            'assigned_user_id' => $callerProfile['assigned_user'] ? $callerProfile['assigned_user']['id'] : null,
            'provider' => $provider,
            'provider_call_id' => $providerCallId,
            'idempotency_key' => $idKey,
            'direction' => 'inbound',
            'call_type' => $routingDecision['call_type'] ?? 'human',
            'from_number' => $fromNumber,
            'to_number' => $toNumber,
            'start_time' => Carbon::now(),
            'status' => 'ringing',
            'recording_notice_played' => true,
        ]);

        return [
            'call' => $voiceCall,
            'is_duplicate' => false,
            'caller_profile' => $callerProfile,
            'routing' => $routingDecision,
            'ivr_flow' => $ivrFlow,
        ];
    }

    /**
     * Evaluate business hours, holiday rules, and IVR routing targets.
     */
    protected function evaluateIvrRouting(?CallIvrFlow $ivr, array $callerProfile): array
    {
        if (!$ivr) {
            return [
                'action' => 'ring_assigned_or_hunt',
                'call_type' => 'human',
                'greeting' => 'Thank you for calling. Connecting you to an agent.',
                'target_user_id' => $callerProfile['assigned_user'] ? $callerProfile['assigned_user']['id'] : null,
            ];
        }

        // Business Hours Check
        $isWithinHours = true;
        if (!empty($ivr->business_hours)) {
            $bh = $ivr->business_hours;
            $tz = $bh['timezone'] ?? 'Asia/Kolkata';
            $now = Carbon::now($tz);
            $dayOfWeek = $now->dayOfWeekIso; // 1 = Mon, 7 = Sun

            $allowedDays = $bh['days'] ?? [1, 2, 3, 4, 5, 6];
            if (!in_array($dayOfWeek, $allowedDays)) {
                $isWithinHours = false;
            } else {
                $openTime = $bh['open'] ?? '09:00';
                $closeTime = $bh['close'] ?? '18:00';
                $currentTime = $now->format('H:i');
                if ($currentTime < $openTime || $currentTime > $closeTime) {
                    $isWithinHours = false;
                }
            }
        }

        if (!$isWithinHours) {
            return [
                'action' => $ivr->unanswered_action ?: 'voicemail',
                'call_type' => 'ivr',
                'greeting' => 'Thank you for calling. Our offices are currently closed. Please leave a message or request a callback.',
                'target' => $ivr->unanswered_target,
            ];
        }

        // Return Active IVR Menu & Greeting
        return [
            'action' => 'play_ivr_menu',
            'call_type' => 'ivr',
            'greeting' => $ivr->welcome_audio_text,
            'language' => $ivr->language,
            'menu_options' => $ivr->menu_options,
            'agent_id' => null,
        ];
    }
}
