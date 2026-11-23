<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadFollowupService
{
    public function __construct(
        protected LeadSlaEngine $slaEngine
    ) {}

    /**
     * Schedule a new follow-up
     */
    public function scheduleFollowup(Lead $lead, array $data, ?int $userId = null): LeadFollowup
    {
        return DB::transaction(function () use ($lead, $data, $userId) {
            $scheduledAt = Carbon::parse($data['scheduled_at']);

            $followup = LeadFollowup::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'assigned_user_id' => $data['assigned_user_id'] ?? $lead->assigned_user_id,
                'followup_type' => $data['followup_type'] ?? 'call',
                'scheduled_at' => $scheduledAt,
                'status' => 'pending',
                'outcome_notes' => $data['notes'] ?? $data['outcome_notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Sync next_followup_at on lead
            if (!$lead->next_followup_at || $scheduledAt->lt($lead->next_followup_at)) {
                $lead->next_followup_at = $scheduledAt;
                $lead->save();
            }

            CrmActivity::create([
                'tenant_id' => $lead->tenant_id,
                'user_id' => $userId,
                'lead_id' => $lead->id,
                'activity_type' => 'task',
                'title' => "Follow-up Scheduled: " . ucfirst($followup->followup_type),
                'description' => "Scheduled for {$scheduledAt->format('M d, Y h:i A')}. Notes: " . ($followup->outcome_notes ?: 'No details'),
                'metadata' => [
                    'followup_id' => $followup->id,
                    'type' => $followup->followup_type,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                ],
            ]);

            return $followup;
        });
    }

    /**
     * Complete a follow-up with outcome
     */
    public function completeFollowup(LeadFollowup $followup, string $outcome, ?string $notes = null, ?int $userId = null): LeadFollowup
    {
        return DB::transaction(function () use ($followup, $outcome, $notes, $userId) {
            $followup->status = 'completed';
            $followup->outcome = $outcome;
            $followup->outcome_notes = $notes ?? $followup->outcome_notes;
            $followup->completed_at = now();
            $followup->save();

            $lead = $followup->lead;
            if ($lead) {
                // If this is first response, record in SLA engine
                $this->slaEngine->recordFirstResponse($lead, $followup->followup_type, $userId);

                // Update next_followup_at on lead to next pending follow-up if any
                $next = LeadFollowup::where('lead_id', $lead->id)
                    ->where('status', 'pending')
                    ->orderBy('scheduled_at', 'asc')
                    ->first();

                $lead->next_followup_at = $next?->scheduled_at;
                $lead->last_contacted_at = now();
                $lead->save();

                CrmActivity::create([
                    'tenant_id' => $lead->tenant_id,
                    'user_id' => $userId,
                    'lead_id' => $lead->id,
                    'activity_type' => 'communication',
                    'title' => "Follow-up Completed: " . ucfirst($followup->followup_type),
                    'description' => "Outcome: " . ucfirst(str_replace('_', ' ', $outcome)) . ($notes ? " • Notes: {$notes}" : ''),
                    'metadata' => [
                        'followup_id' => $followup->id,
                        'outcome' => $outcome,
                    ],
                ]);
            }

            return $followup;
        });
    }

    /**
     * Reschedule follow-up
     */
    public function rescheduleFollowup(LeadFollowup $oldFollowup, string $newDate, ?string $reason = null, ?int $userId = null): LeadFollowup
    {
        return DB::transaction(function () use ($oldFollowup, $newDate, $reason, $userId) {
            $newScheduledAt = Carbon::parse($newDate);

            // Create new follow-up
            $newFollowup = LeadFollowup::create([
                'tenant_id' => $oldFollowup->tenant_id,
                'lead_id' => $oldFollowup->lead_id,
                'assigned_user_id' => $oldFollowup->assigned_user_id,
                'followup_type' => $oldFollowup->followup_type,
                'scheduled_at' => $newScheduledAt,
                'status' => 'pending',
                'outcome_notes' => "Rescheduled from {$oldFollowup->scheduled_at->format('M d, Y')}. Reason: {$reason}",
                'created_by' => $userId,
            ]);

            // Mark old as rescheduled
            $oldFollowup->status = 'rescheduled';
            $oldFollowup->rescheduled_to_id = $newFollowup->id;
            $oldFollowup->save();

            // Sync lead next_followup_at
            $lead = $oldFollowup->lead;
            if ($lead) {
                $lead->next_followup_at = $newScheduledAt;
                $lead->save();
            }

            return $newFollowup;
        });
    }

    /**
     * Get overdue follow-ups
     */
    public function getOverdueFollowups(int $tenantId, ?int $assignedUserId = null)
    {
        $query = LeadFollowup::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('scheduled_at', '<', now())
            ->with(['lead:id,name,phone,email,company_name,status', 'assignedUser:id,name']);

        if ($assignedUserId) {
            $query->where('assigned_user_id', $assignedUserId);
        }

        return $query->orderBy('scheduled_at', 'asc')->get();
    }
}
