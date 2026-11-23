<?php

namespace App\Domains\Voice\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Domains\Projects\Models\Task;
use App\Domains\Voice\Models\TelephonyOptOut;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class VoiceCrmAutomationService
{
    /**
     * Run post-call CRM synchronization and automation rules.
     */
    public function processPostCallAutomation(VoiceCall $call): void
    {
        $transcript = $call->transcript ?: '';
        $extracted = $call->extracted_data ?: [];

        // 1. Analyze outcome & disposition if not already set
        $lower = mb_strtolower($transcript);

        if (str_contains($lower, 'do not call') || str_contains($lower, 'dnd') || str_contains($lower, 'stop calling')) {
            $call->disposition = 'dnd_requested';
            $call->qualification_result = 'unqualified';

            // Auto-register in Telephony Opt-Out Registry
            TelephonyOptOut::firstOrCreate([
                'tenant_id' => $call->tenant_id,
                'phone_number' => $call->to_number,
            ], [
                'opt_out_type' => 'customer_verbal',
                'reason' => 'Verbal DND request during Voice Call #' . $call->id,
                'opted_out_at' => Carbon::now(),
            ]);
        } elseif (str_contains($lower, 'transfer') || str_contains($lower, 'human') || $call->human_transferred) {
            $call->disposition = 'callback_requested';
            $call->qualification_result = 'follow_up_needed';
            $call->follow_up_required = true;
        } elseif (str_contains($lower, 'interested') || str_contains($lower, 'demo') || str_contains($lower, 'budget') || !empty($extracted['budget'])) {
            $call->disposition = 'interested';
            $call->qualification_result = 'qualified';
            $call->follow_up_required = true;
        } else {
            $call->disposition = $call->disposition ?: 'completed';
            $call->qualification_result = $call->qualification_result ?: 'follow_up_needed';
        }

        // 2. Generate AI Summary of dialogue if not set
        if (empty($call->ai_summary)) {
            $summary = "Voice call with {$call->to_number} (Duration: {$call->duration_seconds}s). ";
            if ($call->qualification_result === 'qualified') {
                $summary .= "Customer demonstrated genuine interest. ";
                if (!empty($extracted['budget'])) {
                    $summary .= "Stated budget: ₹" . number_format($extracted['budget']) . ". ";
                }
            } elseif ($call->disposition === 'dnd_requested') {
                $summary .= "Customer requested Do-Not-Call (DND). Number suppressed from future campaigns. ";
            } else {
                $summary .= "Call completed. Disposition: {$call->disposition}. ";
            }
            $call->ai_summary = $summary;
        }

        $call->save();

        // 3. Update Linked CRM Lead
        if ($call->lead_id) {
            $lead = Lead::where('tenant_id', $call->tenant_id)->find($call->lead_id);
            if ($lead) {
                if ($call->qualification_result === 'qualified') {
                    $lead->qualification_status = 'qualified';
                    $lead->status = 'contacted';
                    $lead->score = min(100, $lead->score + 35);
                    if (!empty($extracted['budget'])) {
                        $lead->estimated_value = $extracted['budget'];
                    }
                } elseif ($call->disposition === 'dnd_requested') {
                    $lead->status = 'lost';
                    $lead->score = 0;
                }
                $lead->save();
            }
        }

        // 4. Log CrmActivity on CRM Timeline (Lead / Contact / Deal / Company)
        $userId = $call->assigned_user_id ?: 1;
        $activityTitle = ($call->call_type === 'ai_voice')
            ? "AI Voice Call Completed ({$call->disposition})"
            : "Phone Call Completed ({$call->disposition})";

        $crmActivity = CrmActivity::create([
            'tenant_id' => $call->tenant_id,
            'lead_id' => $call->lead_id,
            'contact_id' => $call->contact_id,
            'deal_id' => $call->deal_id,
            'company_id' => $call->company_id,
            'user_id' => $userId,
            'activity_type' => 'call',
            'title' => $activityTitle,
            'description' => $call->ai_summary,
            'metadata' => [
                'call_id' => $call->id,
                'call_type' => $call->call_type,
                'direction' => $call->direction,
                'duration' => $call->duration_seconds,
                'provider' => $call->provider,
                'recording_url' => $call->recording_url,
                'quality_score' => $call->quality_score,
                'disposition' => $call->disposition,
            ],
        ]);

        // 5. Schedule Follow-up Task or LeadFollowup if qualified or callback requested
        if ($call->follow_up_required || in_array($call->qualification_result, ['qualified', 'follow_up_needed'])) {
            $dueDate = $call->next_follow_up_at ?: Carbon::now()->addDay();

            // Create Task
            Task::create([
                'tenant_id' => $call->tenant_id,
                'lead_id' => $call->lead_id,
                'title' => "Follow-up: Call #{$call->id} ({$call->disposition})",
                'description' => "Follow-up required after call with {$call->to_number}. Summary: {$call->ai_summary}",
                'due_date' => $dueDate,
                'priority' => ($call->qualification_result === 'qualified') ? 'high' : 'medium',
                'status' => 'todo',
                'assigned_user_id' => $userId,
                'created_by' => $userId,
            ]);

            // Create LeadFollowup if lead is present
            if ($call->lead_id) {
                try {
                    LeadFollowup::create([
                        'tenant_id' => $call->tenant_id,
                        'lead_id' => $call->lead_id,
                        'assigned_user_id' => $userId,
                        'followup_type' => 'call',
                        'scheduled_at' => $dueDate,
                        'status' => 'scheduled',
                        'title' => "Voice Call Follow-up ({$call->to_number})",
                        'notes' => $call->ai_summary,
                    ]);
                } catch (\Throwable $e) {
                    // Ignore if already logged or table constraint
                }
            }

            $call->next_follow_up_at = $dueDate;
            $call->save();
        }
    }
}
