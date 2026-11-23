<?php

namespace App\Domains\Voice\Services;

use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Facades\Log;

class VoiceAiPipelineService
{
    /**
     * Process an audio transcript or speech turn during a live or recorded call.
     *
     * @param VoiceCall $call
     * @param VoiceAiAgent $agent
     * @param string $userInputText Speech-to-text transcript turn
     * @param bool $isBargeIn Whether customer interrupted the agent
     * @return array [
     *   'agent_reply_text' => string,
     *   'audio_payload_url' => ?string,
     *   'barge_in_handled' => bool,
     *   'detected_intent' => string,
     *   'should_hangup' => bool,
     *   'should_transfer' => bool,
     *   'extracted_entities' => array
     * ]
     */
    public function processTurn(
        VoiceCall $call,
        VoiceAiAgent $agent,
        string $userInputText,
        bool $isBargeIn = false
    ): array {
        $lower = mb_strtolower(trim($userInputText));
        $bargeInHandled = false;

        // 1. Interruption / Barge-in handling
        if ($isBargeIn) {
            $bargeInHandled = true;
            Log::info("Voice Call #{$call->id}: Barge-in detected from user. Interrupted previous playback.");
        }

        $detectedIntent = 'general_inquiry';
        $shouldHangup = false;
        $shouldTransfer = false;
        $extracted = [];
        $replyText = '';

        // Check for transfer request
        if (str_contains($lower, 'transfer') || str_contains($lower, 'human') || str_contains($lower, 'manager') || str_contains($lower, 'executive') || str_contains($lower, 'baat karni hai')) {
            $shouldTransfer = true;
            $detectedIntent = 'human_transfer_requested';
            $replyText = "Sure, please hold while I transfer your call to our specialist.";
        }
        // Check for DND / Stop calling
        elseif (str_contains($lower, 'do not call') || str_contains($lower, 'stop calling') || str_contains($lower, 'dnd') || str_contains($lower, 'call mat karna')) {
            $shouldHangup = true;
            $detectedIntent = 'dnd_requested';
            $replyText = "I apologize for any inconvenience. I have added your number to our Do Not Call list. Have a good day.";
        }
        // Check for Lead qualification / budget mention
        elseif (str_contains($lower, 'interested') || str_contains($lower, 'price') || str_contains($lower, 'quote') || str_contains($lower, 'budget') || str_contains($lower, 'lakh') || str_contains($lower, 'thousand')) {
            $detectedIntent = 'lead_qualification';

            // Extract budget amount if present
            if (preg_match('/(\d+)\s*(lakh|k|thousand|rupees|inr)?/i', $userInputText, $matches)) {
                $num = (int) $matches[1];
                $multiplier = match (strtolower($matches[2] ?? '')) {
                    'lakh' => 100000,
                    'k', 'thousand' => 1000,
                    default => 1,
                };
                $extracted['budget'] = $num * $multiplier;
            }

            if (str_contains($lower, 'namaste') || str_contains($lower, 'chahiye')) {
                $replyText = "Bahut badiya! Hamare CRM solutions ke baare mein maine aapki requirement note kar li hai. Kya aap kal subah 11 baje ek live demo ke liye available hain?";
            } else {
                $replyText = "Excellent. I have noted your requirements. Would tomorrow at 11:00 AM work for a live product demonstration?";
            }
        }
        // Check for Demo / Appointment confirmation
        elseif (str_contains($lower, 'yes') || str_contains($lower, 'sure') || str_contains($lower, 'okay') || str_contains($lower, 'theek hai') || str_contains($lower, 'chalega')) {
            $detectedIntent = 'appointment_confirmed';
            $shouldHangup = true;
            $extracted['appointment_confirmed'] = true;
            $replyText = "Great! I have scheduled the demo and sent the meeting invitation. Thank you for your time, have a wonderful day!";
        }
        // General or Hindi opening
        else {
            if (str_contains($lower, 'namaste') || str_contains($lower, 'kaise ho')) {
                $replyText = "Namaste! Main Acme AI Assistant bol raha hoon. Main aapki CRM requirement ke baare mein jaan sakta hoon?";
            } else {
                $replyText = "Thank you for sharing that. Based on our enterprise services, we can help streamline your sales operations. What is your current team size?";
            }
        }

        // Append turn to full call transcript
        $newTurn = "\n[User]: {$userInputText}\n[AI Agent]: {$replyText}";
        $call->transcript = ($call->transcript ?: '') . $newTurn;
        $call->save();

        return [
            'agent_reply_text' => $replyText,
            'audio_payload_url' => null, // synthesized TTS stream/URL
            'barge_in_handled' => $bargeInHandled,
            'detected_intent' => $detectedIntent,
            'should_hangup' => $shouldHangup,
            'should_transfer' => $shouldTransfer,
            'extracted_entities' => $extracted,
        ];
    }
}
