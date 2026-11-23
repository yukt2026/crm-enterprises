<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Domains\LeadAcquisition\Models\LeadStageHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadLifecycleService
{
    public function __construct(
        protected LeadSlaEngine $slaEngine
    ) {}

    /**
     * Transition lead stage with audit history and time tracking
     */
    public function transitionStage(
        Lead $lead,
        string $toStage,
        ?int $userId = null,
        ?string $reason = null,
        ?string $notes = null
    ): Lead {
        $fromStage = $lead->status;
        if ($fromStage === $toStage) {
            return $lead;
        }

        return DB::transaction(function () use ($lead, $fromStage, $toStage, $userId, $reason, $notes) {
            // 1. Calculate time spent in previous stage
            $lastHistory = LeadStageHistory::where('lead_id', $lead->id)
                ->orderBy('id', 'desc')
                ->first();

            $timeInStage = 0;
            if ($lastHistory && $lastHistory->created_at) {
                $timeInStage = $lastHistory->created_at->diffInSeconds(now());
            } elseif ($lead->created_at) {
                $timeInStage = $lead->created_at->diffInSeconds(now());
            }

            // 2. If moving to contacted, evaluate SLA first response
            if (in_array($fromStage, ['new', 'uncontacted']) && in_array($toStage, ['contacted', 'qualified', 'meeting_scheduled'])) {
                $this->slaEngine->recordFirstResponse($lead, 'lifecycle_transition', $userId);
            }

            // 3. Update lead status
            $lead->status = $toStage;
            if ($toStage === 'lost' && $reason) {
                $lead->lost_reason = $reason;
            }
            if ($toStage === 'recycled') {
                $lead->notes = ($lead->notes ? $lead->notes . "\n" : "") . "[Recycled on " . now()->toDateString() . "]: " . ($reason ?? 'Lead recycled for future re-engagement');
                // Schedule re-engagement follow-up in 30 days
                LeadFollowup::create([
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id,
                    'assigned_user_id' => $lead->assigned_user_id,
                    'followup_type' => 'call',
                    'scheduled_at' => now()->addDays(30),
                    'status' => 'pending',
                    'outcome_notes' => 'Recycled lead re-engagement check',
                    'created_by' => $userId,
                ]);
            }
            $lead->save();

            // 4. Record Stage History
            LeadStageHistory::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'user_id' => $userId,
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
                'time_in_previous_stage_seconds' => $timeInStage,
                'reason' => $reason,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            // 5. Activity Log
            $userName = $userId ? (User::find($userId)?->name ?? "User #{$userId}") : 'System Automation';
            CrmActivity::create([
                'tenant_id' => $lead->tenant_id,
                'user_id' => $userId,
                'lead_id' => $lead->id,
                'activity_type' => 'status_change',
                'title' => "Stage Transitioned to " . ucfirst(str_replace('_', ' ', $toStage)),
                'description' => "Changed from " . ucfirst(str_replace('_', ' ', $fromStage)) . " by {$userName}." . ($reason ? " Reason: {$reason}" : ''),
                'metadata' => [
                    'from_stage' => $fromStage,
                    'to_stage' => $toStage,
                    'duration_seconds' => $timeInStage,
                    'reason' => $reason,
                ],
            ]);

            return $lead;
        });
    }
}
