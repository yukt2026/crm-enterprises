<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadEscalationLog;
use App\Domains\LeadAcquisition\Models\LeadSlaPolicy;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LeadSlaEngine
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Compute and set initial SLA deadlines on newly captured lead
     */
    public function applySlaPolicy(Lead $lead, ?LeadSlaPolicy $policy = null): Lead
    {
        $tenantId = $lead->tenant_id;
        $policy = $policy ?? LeadSlaPolicy::where('tenant_id', $tenantId)->where('is_default', true)->first();

        if (!$policy) {
            // Create default policy for tenant
            $policy = LeadSlaPolicy::create([
                'tenant_id' => $tenantId,
                'name' => 'Default Indian B2B Lead SLA',
                'is_default' => true,
                'first_response_time_minutes' => 15,
                'followup_deadline_hours' => 24,
                'max_idle_hours' => 48,
                'working_hours_only' => true,
                'business_hours_start' => '09:00',
                'business_hours_end' => '18:00',
                'working_days' => [1, 2, 3, 4, 5, 6],
                'escalation_level_1_minutes' => 15,
                'escalation_level_2_minutes' => 45,
                'auto_reassign_on_escalation' => true,
            ]);
        }

        $dueAt = $this->calculateTargetTime(
            now(),
            $policy->first_response_time_minutes,
            $policy->working_hours_only,
            $policy->business_hours_start,
            $policy->business_hours_end,
            $policy->working_days ?? [1, 2, 3, 4, 5, 6]
        );

        $lead->sla_policy_id = $policy->id;
        $lead->sla_first_response_due_at = $dueAt;
        $lead->sla_status = 'within_sla';
        $lead->save();

        return $lead;
    }

    /**
     * Record first contact attempt and evaluate SLA compliance
     */
    public function recordFirstResponse(Lead $lead, string $channel = 'call', ?int $userId = null): Lead
    {
        if ($lead->sla_first_responded_at) {
            // Already responded previously, only update last_contacted_at
            $lead->last_contacted_at = now();
            $lead->save();
            return $lead;
        }

        $now = now();
        $lead->sla_first_responded_at = $now;
        $lead->last_contacted_at = $now;

        $isCompliant = true;
        if ($lead->sla_first_response_due_at && $now->gt($lead->sla_first_response_due_at)) {
            $isCompliant = false;
            $lead->sla_status = 'breached';
        } else {
            $lead->sla_status = 'within_sla';
        }

        if (in_array($lead->status, ['new', 'uncontacted'])) {
            $lead->status = 'contacted';
        }

        $lead->save();

        // Calculate turnaround minutes
        $turnaroundMinutes = $lead->created_at ? round($lead->created_at->diffInMinutes($now)) : 0;

        CrmActivity::create([
            'tenant_id' => $lead->tenant_id,
            'user_id' => $userId ?? $lead->assigned_user_id,
            'lead_id' => $lead->id,
            'activity_type' => 'communication',
            'title' => "First Response via " . ucfirst($channel),
            'description' => "First response logged in {$turnaroundMinutes} min(s). Status: " . ($isCompliant ? 'Complied with SLA' : 'SLA Breached'),
            'metadata' => [
                'channel' => $channel,
                'turnaround_minutes' => $turnaroundMinutes,
                'sla_status' => $lead->sla_status,
            ],
        ]);

        return $lead;
    }

    /**
     * Scan uncontacted leads and trigger escalation levels
     */
    public function evaluateAndEscalatePendingLeads(?int $tenantId = null): array
    {
        $query = Lead::whereNull('sla_first_responded_at')
            ->whereNotNull('sla_first_response_due_at')
            ->whereNotIn('status', ['converted', 'lost', 'disqualified', 'contacted']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $leads = $query->with(['slaPolicy', 'assignedUser'])->get();
        $escalatedCount = 0;
        $now = now();

        foreach ($leads as $lead) {
            $policy = $lead->slaPolicy;
            if (!$policy) continue;

            $minutesOverdue = $lead->sla_first_response_due_at->diffInMinutes($now, false);
            if ($minutesOverdue <= 0) {
                continue; // Not yet overdue
            }

            // Level 2 Escalation: Overdue past level 2 threshold
            if ($minutesOverdue >= $policy->escalation_level_2_minutes) {
                $alreadyLogged = LeadEscalationLog::where('lead_id', $lead->id)
                    ->where('escalation_level', 2)
                    ->exists();

                if (!$alreadyLogged) {
                    $this->triggerLevel2Escalation($lead, $policy, (int) $minutesOverdue);
                    $escalatedCount++;
                }
            }
            // Level 1 Escalation: Overdue past level 1 threshold
            elseif ($minutesOverdue >= $policy->escalation_level_1_minutes) {
                $alreadyLogged = LeadEscalationLog::where('lead_id', $lead->id)
                    ->where('escalation_level', 1)
                    ->exists();

                if (!$alreadyLogged) {
                    $this->triggerLevel1Escalation($lead, $policy, (int) $minutesOverdue);
                    $escalatedCount++;
                }
            }
        }

        return [
            'evaluated_leads_count' => $leads->count(),
            'escalated_count' => $escalatedCount,
        ];
    }

    protected function triggerLevel1Escalation(Lead $lead, LeadSlaPolicy $policy, int $minutesOverdue): void
    {
        $lead->sla_status = 'breached';
        $lead->save();

        LeadEscalationLog::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'sla_policy_id' => $policy->id,
            'escalation_level' => 1,
            'reason' => "First response SLA missed by {$minutesOverdue} minutes",
            'action_taken' => "Warning sent to assigned rep",
            'previous_user_id' => $lead->assigned_user_id,
            'new_user_id' => $lead->assigned_user_id,
            'triggered_at' => now(),
        ]);

        if ($lead->assignedUser) {
            try {
                $this->notificationService->notify(
                    recipient: $lead->assignedUser,
                    type: 'lead.sla_warning',
                    title: "URGENT: Lead First-Response SLA Breached",
                    message: "Lead {$lead->name} from {$lead->source} has not been contacted. First response SLA was missed by {$minutesOverdue} min(s).",
                    actionUrl: "/leads/{$lead->id}",
                    data: ['lead_id' => $lead->id, 'escalation_level' => 1]
                );
            } catch (\Throwable $e) {
                Log::error("Failed to notify user for SLA level 1: " . $e->getMessage());
            }
        }
    }

    protected function triggerLevel2Escalation(Lead $lead, LeadSlaPolicy $policy, int $minutesOverdue): void
    {
        $previousUserId = $lead->assigned_user_id;
        $targetManagerId = $policy->manager_user_id;

        // If auto-reassign enabled and manager exists, reassign to manager
        if ($policy->auto_reassign_on_escalation && $targetManagerId && $targetManagerId !== $previousUserId) {
            $lead->assigned_user_id = $targetManagerId;
            $lead->reassigned_count += 1;
        }

        $lead->sla_status = 'escalated';
        $lead->sla_escalated_at = now();
        $lead->save();

        LeadEscalationLog::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'sla_policy_id' => $policy->id,
            'escalation_level' => 2,
            'reason' => "Severe SLA breach: Unhandled for {$minutesOverdue} minutes",
            'action_taken' => $targetManagerId ? "Escalated to manager & auto-reassigned" : "Escalated to management",
            'previous_user_id' => $previousUserId,
            'new_user_id' => $lead->assigned_user_id,
            'triggered_at' => now(),
        ]);

        // Alert manager
        $manager = User::find($targetManagerId);
        if ($manager) {
            try {
                $this->notificationService->notify(
                    recipient: $manager,
                    type: 'lead.sla_escalation',
                    title: "CRITICAL: Unhandled Lead Escalation",
                    message: "Lead {$lead->name} was idle for {$minutesOverdue} mins. Reassigned to management to prevent lead leakage.",
                    actionUrl: "/leads/{$lead->id}",
                    data: ['lead_id' => $lead->id, 'escalation_level' => 2]
                );
            } catch (\Throwable $e) {
                Log::error("Failed to notify manager for SLA level 2: " . $e->getMessage());
            }
        }
    }

    /**
     * Compute working hours compliant timestamp
     */
    protected function calculateTargetTime(
        Carbon $from,
        int $minutesToAdd,
        bool $workingHoursOnly,
        string $startStr = '09:00',
        string $endStr = '18:00',
        array $workingDays = [1, 2, 3, 4, 5, 6]
    ): Carbon {
        if (!$workingHoursOnly) {
            return $from->copy()->addMinutes($minutesToAdd);
        }

        $current = $from->copy();
        [$sHour, $sMin] = explode(':', $startStr);
        [$eHour, $eMin] = explode(':', $endStr);

        // If today is not a working day or past end of business, advance to next start of business
        while (!in_array($current->dayOfWeekIso, $workingDays) || $current->hour >= (int) $eHour) {
            $current->addDay()->setTime((int) $sHour, (int) $sMin, 0);
        }

        // If before start of business, set to start of business
        if ($current->hour < (int) $sHour) {
            $current->setTime((int) $sHour, (int) $sMin, 0);
        }

        return $current->addMinutes($minutesToAdd);
    }
}
