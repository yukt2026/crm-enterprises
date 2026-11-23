<?php

namespace App\Domains\Omnichannel\Services;

use App\Domains\Omnichannel\Models\OmnichannelSlaPolicy;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OmnichannelSlaService
{
    /**
     * Apply SLA policy to a conversation and compute due dates.
     */
    public function applySlaPolicy(WhatsAppConversation $conversation): WhatsAppConversation
    {
        $tenantId = $conversation->tenant_id;
        $priority = $conversation->priority ?? 'medium';
        $channel = $conversation->channel ?? 'whatsapp';

        // 1. Find matching SLA policy (channel-specific first, then priority, then default)
        $policy = OmnichannelSlaPolicy::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($channel) {
                $q->where('channel', $channel)->orWhere('channel', 'all');
            })
            ->where(function ($q) use ($priority) {
                $q->where('priority', $priority)->orWhere('priority', 'all');
            })
            ->latest('id')
            ->first();

        // If no custom policy configured, use sane enterprise defaults
        $frtMinutes = $policy?->first_response_target_minutes ?? match ($priority) {
            'urgent' => 15,
            'high' => 30,
            'medium' => 60,
            'low' => 120,
            default => 60,
        };

        $resMinutes = $policy?->resolution_target_minutes ?? match ($priority) {
            'urgent' => 240, // 4 hours
            'high' => 480,  // 8 hours
            'medium' => 1440, // 24 hours
            'low' => 2880,  // 48 hours
            default => 1440,
        };

        $schedule = $policy?->business_hours_enabled ? $policy->business_hours : null;

        $now = now();
        $firstResponseDueAt = $this->calculateDueDate($now, $frtMinutes, $schedule);
        $resolutionDueAt = $this->calculateDueDate($now, $resMinutes, $schedule);

        $conversation->update([
            'sla_policy_id' => $policy?->id,
            'first_response_due_at' => $firstResponseDueAt,
            'resolution_due_at' => $resolutionDueAt,
            'sla_status' => 'on_track',
        ]);

        return $conversation;
    }

    /**
     * Calculate due timestamp respecting working hours schedule.
     */
    public function calculateDueDate(Carbon $start, int $targetMinutes, ?array $schedule = null): Carbon
    {
        if (empty($schedule)) {
            return $start->copy()->addMinutes($targetMinutes);
        }

        $tz = $schedule['timezone'] ?? 'Asia/Kolkata';
        $current = $start->copy()->setTimezone($tz);
        $remainingMinutes = $targetMinutes;

        $allowedDays = $schedule['days'] ?? [1, 2, 3, 4, 5];
        $startHour = (int) explode(':', $schedule['start'] ?? '09:00')[0];
        $startMin = (int) explode(':', $schedule['start'] ?? '09:00')[1];
        $endHour = (int) explode(':', $schedule['end'] ?? '18:00')[0];
        $endMin = (int) explode(':', $schedule['end'] ?? '18:00')[1];

        // Step forward minute by minute in working periods (cap iterations to avoid infinite loop)
        $iterations = 0;
        $maxIterations = 1440 * 14; // max 14 days ahead

        while ($remainingMinutes > 0 && $iterations < $maxIterations) {
            $dayOfWeek = $current->dayOfWeekIso;

            if (in_array($dayOfWeek, $allowedDays)) {
                $workStart = $current->copy()->setTime($startHour, $startMin, 0);
                $workEnd = $current->copy()->setTime($endHour, $endMin, 0);

                if ($current->lt($workStart)) {
                    $current = $workStart;
                }

                if ($current->gte($workStart) && $current->lt($workEnd)) {
                    $availableThisDay = $current->diffInMinutes($workEnd);
                    if ($remainingMinutes <= $availableThisDay) {
                        $current->addMinutes($remainingMinutes);
                        $remainingMinutes = 0;
                        break;
                    } else {
                        $remainingMinutes -= $availableThisDay;
                        $current = $workEnd;
                    }
                }
            }

            // Move to start of next day
            $current->addDay()->setTime($startHour, $startMin, 0);
            $iterations++;
        }

        return $current->utc();
    }

    /**
     * Check and refresh SLA status on a conversation.
     */
    public function evaluateSlaStatus(WhatsAppConversation $conversation): string
    {
        if ($conversation->status === 'resolved') {
            return 'resolved';
        }

        $now = now();
        $targetDue = is_null($conversation->first_response_at)
            ? $conversation->first_response_due_at
            : $conversation->resolution_due_at;

        if (!$targetDue) {
            return 'on_track';
        }

        if ($now->gt($targetDue)) {
            $conversation->update(['sla_status' => 'breached']);
            return 'breached';
        }

        // Warning state when within 20% of target time
        $created = Carbon::parse($conversation->created_at ?? $now);
        $totalWindowSeconds = max(60, $created->diffInSeconds($targetDue));
        $remainingSeconds = $now->diffInSeconds($targetDue, false);

        if ($remainingSeconds > 0 && ($remainingSeconds / $totalWindowSeconds) <= 0.20) {
            $conversation->update(['sla_status' => 'warning']);
            return 'warning';
        }

        $conversation->update(['sla_status' => 'on_track']);
        return 'on_track';
    }

    /**
     * Recheck and batch refresh SLA statuses across open conversations for a tenant.
     */
    public function checkOverdueConversations(int $tenantId): array
    {
        $openConvs = WhatsAppConversation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->get();

        $stats = [
            'total' => $openConvs->count(),
            'on_track' => 0,
            'warning' => 0,
            'breached' => 0,
        ];

        foreach ($openConvs as $conv) {
            $status = $this->evaluateSlaStatus($conv);
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }
}
