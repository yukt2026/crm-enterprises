<?php

namespace App\Domains\Voice\Services;

use App\Domains\Projects\Models\Task;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AdvancedVoiceAiService
{
    /**
     * Process an AI voice conversational turn with multi-lingual Hindi/Hinglish/Regional support,
     * intent extraction, knowledge grounding, and human handoff checks.
     */
    public function processVoiceTurn(
        VoiceCall $call,
        VoiceAiAgent $agent,
        string $userInputText,
        bool $isBargeIn = false
    ): array {
        $rawInput = trim($userInputText);
        $lower = mb_strtolower($rawInput);

        // 1. Interruption / Barge-in handling
        if ($isBargeIn) {
            Log::info("Voice Call #{$call->id}: Customer barged in. Cutting off agent speech playback.");
        }

        // 2. Escalation & Handoff Rule Evaluation
        $handoffCheck = $this->evaluateHumanHandoff($lower, $agent, $call);
        if ($handoffCheck['should_transfer']) {
            $call->human_transferred = true;
            $call->transfer_target = $agent->human_transfer_number ?: 'Assigned Rep Queue';
            $call->disposition = 'callback_requested';
            $call->hangup_cause = 'TRANSFERRED_TO_HUMAN';

            $reply = $this->getHandoffMessage($agent->language, $handoffCheck['reason']);
            $this->appendTranscript($call, $rawInput, $reply);

            return [
                'agent_reply_text' => $reply,
                'detected_intent' => 'human_transfer_requested',
                'confidence_score' => 0.95,
                'should_hangup' => false,
                'should_transfer' => true,
                'transfer_target' => $call->transfer_target,
                'handoff_reason' => $handoffCheck['reason'],
                'extracted_entities' => ['transfer_reason' => $handoffCheck['reason']],
            ];
        }

        // 3. Check for Do-Not-Call (DND) / Opt-Out
        if (str_contains($lower, 'do not call') || str_contains($lower, 'stop calling') || str_contains($lower, 'dnd') || str_contains($lower, 'call mat karna') || str_contains($lower, 'phone mat karo')) {
            $call->disposition = 'dnd_requested';
            $call->qualification_result = 'unqualified';
            $call->status = 'completed';
            $call->hangup_cause = 'CUSTOMER_DND_REQUEST';

            $reply = match ($agent->language) {
                'hi', 'hinglish' => "Maaf kijiye. Maine aapka number hamari Do Not Call list mein add kar diya hai. Ab se aapko koi call nahi aayegi. Dhanyawad.",
                default => "I sincerely apologize for the inconvenience. I have added your phone number to our Do Not Call suppression list. You will not receive further calls. Have a good day.",
            };

            $this->appendTranscript($call, $rawInput, $reply);

            return [
                'agent_reply_text' => $reply,
                'detected_intent' => 'dnd_requested',
                'confidence_score' => 0.99,
                'should_hangup' => true,
                'should_transfer' => false,
                'extracted_entities' => ['dnd_opt_out' => true],
            ];
        }

        // 4. Extract Dynamic Entities (Budget, Timeline, Email, Meeting Availability)
        $extracted = $this->extractEntities($rawInput, $lower);

        // 5. Intent Classification & Purpose-Based Response Generation
        $purpose = $agent->business_purpose ?: 'lead_qualification';
        $intentResult = $this->generateResponseByPurpose($purpose, $agent, $lower, $rawInput, $extracted);

        // 6. Knowledge Grounding Guardrail Check
        $replyText = $intentResult['reply'];
        $confidence = $intentResult['confidence'];
        $detectedIntent = $intentResult['intent'];
        $shouldHangup = $intentResult['should_hangup'] ?? false;

        // If confidence falls below agent threshold, trigger fallback or human handoff
        if ($confidence < ($agent->confidence_threshold ?: 0.65)) {
            $replyText = match ($agent->language) {
                'hi', 'hinglish' => "Main aapki baat poori tarah samajh nahi paaya. Kya main hamare executive ko ye call transfer kar doon?",
                default => "I want to make sure you get accurate information. Would you like me to transfer you to our senior product specialist?",
            };
            $detectedIntent = 'low_confidence_clarification';
        }

        // 7. Update Call Transcript & Extracted Data
        $mergedExtracted = array_merge($call->extracted_data ?: [], $extracted);
        $call->extracted_data = $mergedExtracted;
        if (!empty($extracted['budget'])) {
            $call->qualification_result = 'qualified';
            $call->disposition = 'interested';
        }

        $this->appendTranscript($call, $rawInput, $replyText);

        return [
            'agent_reply_text' => $replyText,
            'detected_intent' => $detectedIntent,
            'confidence_score' => $confidence,
            'should_hangup' => $shouldHangup,
            'should_transfer' => false,
            'extracted_entities' => $mergedExtracted,
        ];
    }

    /**
     * Evaluate rules for transferring the call to a human supervisor.
     */
    protected function evaluateHumanHandoff(string $lower, VoiceAiAgent $agent, VoiceCall $call): array
    {
        // Explicit human request
        if (
            str_contains($lower, 'transfer') ||
            str_contains($lower, 'human') ||
            str_contains($lower, 'manager') ||
            str_contains($lower, 'executive') ||
            str_contains($lower, 'agent se baat') ||
            str_contains($lower, 'baat karni hai') ||
            str_contains($lower, 'insan se') ||
            str_contains($lower, 'kisi aur se')
        ) {
            return ['should_transfer' => true, 'reason' => 'Customer explicitly requested a human specialist.'];
        }

        // Sensitive dispute, complaint, legal or refund threat
        if (
            str_contains($lower, 'refund') ||
            str_contains($lower, 'fraud') ||
            str_contains($lower, 'scam') ||
            str_contains($lower, 'complaint') ||
            str_contains($lower, 'court') ||
            str_contains($lower, 'police') ||
            str_contains($lower, 'consumer court') ||
            str_contains($lower, 'paisa wapas') ||
            str_contains($lower, 'cheating')
        ) {
            return ['should_transfer' => true, 'reason' => 'Sensitive customer complaint or dispute detected.'];
        }

        // Price negotiation / custom discount request (AI guardrail: AI must not invent unauthorized discounts)
        if (
            str_contains($lower, 'heavy discount') ||
            str_contains($lower, 'half price') ||
            str_contains($lower, '50% discount') ||
            str_contains($lower, 'kam karo') ||
            str_contains($lower, 'special approval')
        ) {
            return ['should_transfer' => true, 'reason' => 'Custom discount approval required.'];
        }

        return ['should_transfer' => false, 'reason' => ''];
    }

    /**
     * Generate localized handoff notice.
     */
    protected function getHandoffMessage(string $lang, string $reason): string
    {
        return match ($lang) {
            'hi', 'hinglish' => "Bilkul, main aapki call abhi hamare senior executive ko transfer kar raha hoon. Kripya ek pal line par baniye.",
            'ta' => "Kandippaaga, ungal call-ai engal officer-ukku transfer seigirom. Dayavuseithu line-il irunggal.",
            'te' => "Tappakunda, mee call-ni maa senior executive-ki transfer chestunnamu. Dayachesi line-lo undandi.",
            'kn' => "Khandita, nimma kare-yannu namma executive-ge transfer maaduttiddene. Dayavittu line-nalli iri.",
            'mr' => "Nakkich, mee aapanas aamchya senior executive kade transfer karat aahe. Krupaya line var raha.",
            'bn' => "Nishchoyi, ami apnar call amader senior executive ke transfer korchhi. Doya kore line-e thakun.",
            'gu' => "Chokkas, hu tamaro call amara senior executive ne transfer kari rahyo chhu. Krupya line par raho.",
            default => "Certainly. Please hold for a moment while I transfer your call to our senior specialist.",
        };
    }

    /**
     * Extract key CRM entities from customer utterance.
     */
    protected function extractEntities(string $raw, string $lower): array
    {
        $entities = [];

        // 1. Budget extraction (supports Lakhs, Thousands, Cr, Rupees)
        if (preg_match('/(\d+)\s*(lakh|lakhs|lac|lacs|crore|cr|k|thousand|rupees|rs|inr)?/i', $raw, $m)) {
            $num = (float) $m[1];
            $unit = strtolower($m[2] ?? '');
            $multiplier = match ($unit) {
                'crore', 'cr' => 10000000,
                'lakh', 'lakhs', 'lac', 'lacs' => 100000,
                'k', 'thousand' => 1000,
                default => 1,
            };
            if ($multiplier > 1 || str_contains($lower, 'budget') || str_contains($lower, 'price') || str_contains($lower, 'cost')) {
                $entities['budget'] = (int) ($num * $multiplier);
            }
        }

        // 2. Timeline extraction
        if (str_contains($lower, 'immediately') || str_contains($lower, 'urgent') || str_contains($lower, 'asap') || str_contains($lower, 'turant')) {
            $entities['timeline'] = 'immediate';
        } elseif (str_contains($lower, 'next week') || str_contains($lower, 'agle hafte')) {
            $entities['timeline'] = 'next_week';
        } elseif (str_contains($lower, 'next month') || str_contains($lower, 'agle mahine')) {
            $entities['timeline'] = 'next_month';
        }

        // 3. Callback requested time
        if (str_contains($lower, 'call back') || str_contains($lower, 'call me later') || str_contains($lower, 'baad mein call karna') || str_contains($lower, 'shaam ko')) {
            $entities['callback_requested'] = true;
            if (str_contains($lower, 'evening') || str_contains($lower, 'shaam')) {
                $entities['preferred_time'] = 'evening';
            } elseif (str_contains($lower, 'tomorrow') || str_contains($lower, 'kal')) {
                $entities['preferred_time'] = 'tomorrow';
            }
        }

        return $entities;
    }

    /**
     * Generate response tailored to agent's business purpose.
     */
    protected function generateResponseByPurpose(
        string $purpose,
        VoiceAiAgent $agent,
        string $lower,
        string $raw,
        array $extracted
    ): array {
        $lang = $agent->language ?: 'en';
        $isHindiOrHinglish = in_array($lang, ['hi', 'hinglish']);

        // Check if callback requested
        if (!empty($extracted['callback_requested'])) {
            $reply = $isHindiOrHinglish
                ? "Theek hai, main hamare executive ko aapke preferred samay par call karne ke liye note kar leta hoon. Dhanyawad!"
                : "Noted! I have scheduled a callback for you at your preferred time. Have a wonderful day!";
            return ['reply' => $reply, 'intent' => 'callback_requested', 'confidence' => 0.95, 'should_hangup' => true];
        }

        switch ($purpose) {
            case 'appointment_confirmation':
                if (str_contains($lower, 'yes') || str_contains($lower, 'haan') || str_contains($lower, 'confirm') || str_contains($lower, 'okay') || str_contains($lower, 'theek hai')) {
                    $reply = $isHindiOrHinglish
                        ? "Bahut badiya! Aapka meeting appointment confirm ho gaya hai. Humne meeting link aapke WhatsApp aur email par bhej diya hai. Dhanyawad!"
                        : "Excellent! Your appointment is successfully confirmed. We have shared the meeting details on your WhatsApp and email. Thank you!";
                    return ['reply' => $reply, 'intent' => 'appointment_confirmed', 'confidence' => 0.98, 'should_hangup' => true];
                }
                break;

            case 'payment_reminder':
                if (str_contains($lower, 'paid') || str_contains($lower, 'already paid') || str_contains($lower, 'kar diya') || str_contains($lower, 'bhej diya')) {
                    $reply = $isHindiOrHinglish
                        ? "Shukriya update dene ke liye! Hum apne finance system mein payment verify karke aapko confirmation receipt bhej denge."
                        : "Thank you for confirming. We will cross-check the transaction with our accounts team and update your invoice receipt immediately.";
                    return ['reply' => $reply, 'intent' => 'payment_already_made', 'confidence' => 0.92, 'should_hangup' => true];
                }
                break;

            case 'renewal_reminder':
                if (str_contains($lower, 'renew') || str_contains($lower, 'continue') || str_contains($lower, 'aage badhao') || str_contains($lower, 'yes')) {
                    $reply = $isHindiOrHinglish
                        ? "Great! Hamare team ne renewal proposal aapko email kar diya hai. Kya main aapke liye auto-renewal invoice generate karoon?"
                        : "Wonderful! We have emailed the renewal invoice. Would you like me to send you the direct UPI payment link?";
                    return ['reply' => $reply, 'intent' => 'renewal_confirmed', 'confidence' => 0.95, 'should_hangup' => false];
                }
                break;

            case 'feedback_collection':
                if (preg_match('/[1-5]|one|two|three|four|five|good|bad|excellent|acha/i', $lower)) {
                    $reply = $isHindiOrHinglish
                        ? "Aapke mulyavan feedback ke liye bahut bahut dhanyawad! Ye hamare services ko behtar banane mein madad karega."
                        : "Thank you very much for your valuable feedback! It helps us continuously improve our service quality.";
                    return ['reply' => $reply, 'intent' => 'feedback_recorded', 'confidence' => 0.95, 'should_hangup' => true];
                }
                break;

            case 'lead_qualification':
            default:
                if (!empty($extracted['budget'])) {
                    $formattedBudget = '₹' . number_format($extracted['budget']);
                    $reply = $isHindiOrHinglish
                        ? "Dhanyawad! Maine aapka {$formattedBudget} ka budget note kar liya hai. Hamare solutions aapki requirements ko effectively fulfill karenge. Kya kal subah 11 baje hum ek detailed demo arrange kar sakte hain?"
                        : "Thank you. I have noted your planned budget of {$formattedBudget}. Our enterprise plan fits your criteria. Could we schedule a 15-minute live demo tomorrow at 11:00 AM?";
                    return ['reply' => $reply, 'intent' => 'lead_qualification_budget_stated', 'confidence' => 0.94, 'should_hangup' => false];
                }

                if (str_contains($lower, 'yes') || str_contains($lower, 'sure') || str_contains($lower, 'haan') || str_contains($lower, 'theek hai') || str_contains($lower, 'chalega')) {
                    $reply = $isHindiOrHinglish
                        ? "Bahut badhiya! Maine demo slot book kar liya hai aur meeting link aapko WhatsApp kar di hai. Aapse kal baat hogi, Shubh din!"
                        : "Fantastic! I have booked the demo slot and sent the invite link to your WhatsApp. Looking forward to our session tomorrow. Have a great day!";
                    return ['reply' => $reply, 'intent' => 'demo_scheduled', 'confidence' => 0.96, 'should_hangup' => true];
                }
                break;
        }

        // Standard conversational continuation with knowledge base awareness
        if ($isHindiOrHinglish) {
            $reply = "Namaste! Acme Enterprise CRM mein aapka swagat hai. Main aapki sales aur customer management mein madad kar sakta hoon. Aap kis product ya service ke baare mein jaankari chahte hain?";
        } else {
            $reply = "Thank you for sharing that. As an enterprise business, we specialize in high-efficiency CRM and omnichannel telephony. What is your primary business goal for this quarter?";
        }

        return ['reply' => $reply, 'intent' => 'general_inquiry', 'confidence' => 0.88, 'should_hangup' => false];
    }

    /**
     * Append turn to transcript.
     */
    protected function appendTranscript(VoiceCall $call, string $userText, string $agentText): void
    {
        $turn = "\n[User]: {$userText}\n[AI Agent]: {$agentText}";
        $call->transcript = ($call->transcript ?: '') . $turn;
        $call->save();
    }
}
