<?php

namespace App\Domains\Support\Services;

use App\Domains\Support\Models\SupportSlaPolicy;
use App\Domains\Support\Models\SupportTicket;
use Carbon\Carbon;

class SlaService
{
    /**
     * Calculate First Response and Resolution Due timestamps based on SLA Policy & Business Hours
     */
    public function calculateSlaTarget(int $tenantId, string $priority = 'medium', ?int $policyId = null, ?Carbon $startTime = null): array
    {
        $policy = null;

        if ($policyId) {
            $policy = SupportSlaPolicy::where('tenant_id', $tenantId)->find($policyId);
        }

        if (!$policy) {
            $policy = SupportSlaPolicy::where('tenant_id', $tenantId)
                ->where(function ($q) use ($priority) {
                    $q->where('priority', $priority)
                      ->orWhere('priority', 'all');
                })
                ->orderByRaw("CASE WHEN priority = '{$priority}' THEN 1 ELSE 2 END")
                ->first();
        }

        if (!$policy) {
            $policy = SupportSlaPolicy::where('tenant_id', $tenantId)->where('is_default', true)->first();
        }

        // Fallback default targets if no policy is in database
        $responseMinutes = match ($priority) {
            'urgent' => 30,
            'high' => 60,
            'medium' => 240,
            'low' => 480,
            default => 120,
        };

        $resolutionMinutes = match ($priority) {
            'urgent' => 240, // 4 hrs
            'high' => 480,  // 8 hrs
            'medium' => 1440, // 24 hrs
            'low' => 2880,  // 48 hrs
            default => 1440,
        };

        $businessHoursOnly = true;

        if ($policy) {
            $responseMinutes = $policy->response_target_minutes;
            $resolutionMinutes = $policy->resolution_target_minutes;
            $businessHoursOnly = $policy->business_hours_only;
        }

        $start = $startTime ? $startTime->copy() : Carbon::now();

        if ($businessHoursOnly) {
            $firstResponseDue = $this->addBusinessMinutes($start->copy(), $responseMinutes);
            $resolutionDue = $this->addBusinessMinutes($start->copy(), $resolutionMinutes);
        } else {
            $firstResponseDue = $start->copy()->addMinutes($responseMinutes);
            $resolutionDue = $start->copy()->addMinutes($resolutionMinutes);
        }

        return [
            'sla_policy_id' => $policy?->id,
            'first_response_due_at' => $firstResponseDue,
            'resolution_due_at' => $resolutionDue,
        ];
    }

    /**
     * Add minutes considering standard business hours (Mon-Fri 09:00 - 18:00)
     */
    public function addBusinessMinutes(Carbon $start, int $minutesToAdd): Carbon
    {
        $current = $start->copy();
        $remaining = $minutesToAdd;

        // If starting outside business hours, advance to next business morning at 09:00
        $current = $this->normalizeToBusinessHours($current);

        while ($remaining > 0) {
            $endOfDay = $current->copy()->setTime(18, 0, 0);
            $minutesUntilEndOfDay = $current->diffInMinutes($endOfDay, false);

            if ($minutesUntilEndOfDay <= 0) {
                // Advance to next morning
                $current->addDay()->setTime(9, 0, 0);
                $current = $this->normalizeToBusinessHours($current);
                continue;
            }

            if ($remaining <= $minutesUntilEndOfDay) {
                $current->addMinutes($remaining);
                $remaining = 0;
            } else {
                $remaining -= $minutesUntilEndOfDay;
                $current->addDay()->setTime(9, 0, 0);
                $current = $this->normalizeToBusinessHours($current);
            }
        }

        return $current;
    }

    /**
     * Normalize timestamp so it falls on a business day (Mon-Fri) between 09:00 and 18:00
     */
    protected function normalizeToBusinessHours(Carbon $time): Carbon
    {
        // Skip weekend
        while ($time->isWeekend()) {
            $time->addDay()->setTime(9, 0, 0);
        }

        if ($time->hour < 9) {
            $time->setTime(9, 0, 0);
        } elseif ($time->hour >= 18) {
            $time->addDay()->setTime(9, 0, 0);
            while ($time->isWeekend()) {
                $time->addDay()->setTime(9, 0, 0);
            }
        }

        return $time;
    }

    /**
     * Check SLA compliance and trigger escalation if breached or nearing breach
     */
    public function evaluateAndEscalateTicket(SupportTicket $ticket): void
    {
        if (in_array($ticket->status, ['resolved', 'closed'])) {
            return;
        }

        $now = Carbon::now();
        $isBreached = false;
        $isWarning = false;

        // 1. Response SLA
        if (!$ticket->first_responded_at && $ticket->first_response_due_at) {
            if ($now->isAfter($ticket->first_response_due_at)) {
                $isBreached = true;
            } else {
                $totalResponseDuration = $ticket->created_at->diffInMinutes($ticket->first_response_due_at);
                $elapsed = $ticket->created_at->diffInMinutes($now);
                if ($totalResponseDuration > 0 && ($elapsed / $totalResponseDuration) >= 0.8) {
                    $isWarning = true;
                }
            }
        }

        // 2. Resolution SLA
        if ($ticket->resolution_due_at) {
            if ($now->isAfter($ticket->resolution_due_at)) {
                $isBreached = true;
            } else {
                $totalResDuration = $ticket->created_at->diffInMinutes($ticket->resolution_due_at);
                $elapsed = $ticket->created_at->diffInMinutes($now);
                if ($totalResDuration > 0 && ($elapsed / $totalResDuration) >= 0.8) {
                    $isWarning = true;
                }
            }
        }

        $newSlaStatus = $isBreached ? 'breached' : ($isWarning ? 'warning' : 'within_sla');
        $updates = ['sla_status' => $newSlaStatus];

        // Escalation trigger: if breached or warning, and policy specifies escalation manager
        if (($isBreached || $isWarning) && !$ticket->escalated_to_user_id) {
            $escalateUserId = $ticket->slaPolicy?->escalate_to_user_id;
            if ($escalateUserId) {
                $updates['escalated_to_user_id'] = $escalateUserId;
                $updates['escalated_at'] = Carbon::now();
                $updates['escalation_reason'] = $isBreached
                    ? 'Automatic escalation: SLA breached.'
                    : 'Automatic warning escalation: 80% SLA threshold reached.';
            }
        }

        $ticket->update($updates);
    }
}
