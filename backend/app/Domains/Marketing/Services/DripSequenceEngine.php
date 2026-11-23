<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Activities\Models\Activity;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\DripEnrollment;
use App\Domains\Marketing\Models\DripSequence;
use App\Domains\Marketing\Models\DripStep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DripSequenceEngine
{
    public function __construct(
        protected CampaignConsentService $consentService
    ) {}

    /**
     * Enroll an entity into an automated drip sequence.
     */
    public function enroll(
        DripSequence $sequence,
        string $entityType,
        int $entityId,
        array $context = []
    ): ?DripEnrollment {
        if ($sequence->status !== 'active') {
            return null;
        }

        // Prevent duplicate active enrollment
        $existing = DripEnrollment::where('tenant_id', $sequence->tenant_id)
            ->where('drip_sequence_id', $sequence->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return $existing;
        }

        $firstStep = $sequence->steps()->orderBy('step_order')->first();
        if (!$firstStep) {
            return null;
        }

        $nextExecutionAt = now()->add($firstStep->delay_interval, $firstStep->delay_unit);

        $enrollment = DripEnrollment::create([
            'tenant_id' => $sequence->tenant_id,
            'drip_sequence_id' => $sequence->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => 'active',
            'current_step_id' => $firstStep->id,
            'next_execution_at' => $nextExecutionAt,
            'context_data' => $context,
        ]);

        $sequence->increment('active_enrollments_count');

        return $enrollment;
    }

    /**
     * Process due enrollments and advance steps.
     */
    public function processDueEnrollments(int $tenantId): array
    {
        $dueEnrollments = DripEnrollment::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('next_execution_at', '<=', now())
            ->with(['sequence', 'currentStep'])
            ->get();

        $processed = 0;
        $exited = 0;

        foreach ($dueEnrollments as $enrollment) {
            $sequence = $enrollment->sequence;
            $step = $enrollment->currentStep;

            if (!$sequence || !$step || $sequence->status !== 'active') {
                continue;
            }

            // 1. Check Exit Conditions
            // A. Exit on conversion
            if ($sequence->exit_on_conversion && $enrollment->entity_type === 'lead') {
                $lead = Lead::where('tenant_id', $tenantId)->find($enrollment->entity_id);
                if ($lead && in_array($lead->status, ['qualified', 'proposal_sent', 'customer', 'converted'])) {
                    $enrollment->update([
                        'status' => 'exited_conversion',
                        'exit_reason' => 'Lead converted / qualified.',
                    ]);
                    $sequence->decrement('active_enrollments_count');
                    $exited++;
                    continue;
                }
            }

            // B. Exit on customer reply
            if ($sequence->exit_on_reply && !empty($enrollment->context_data['customer_replied'])) {
                $enrollment->update([
                    'status' => 'exited_reply',
                    'exit_reason' => 'Customer sent inbound message reply.',
                ]);
                $sequence->decrement('active_enrollments_count');
                $exited++;
                continue;
            }

            // C. Exit on unsubscribe
            $recipientAddress = $this->resolveRecipientAddress($tenantId, $enrollment->entity_type, $enrollment->entity_id, $step->channel);
            if ($recipientAddress) {
                $permission = $this->consentService->checkPermission($tenantId, $step->channel, $recipientAddress);
                if (!$permission['allowed']) {
                    $enrollment->update([
                        'status' => 'exited_unsubscribed',
                        'exit_reason' => $permission['reason'] ?? 'Opted out or suppressed.',
                    ]);
                    $sequence->decrement('active_enrollments_count');
                    $exited++;
                    continue;
                }
            }

            // 2. Check Quiet Hours
            if ($sequence->respect_quiet_hours) {
                $currentHour = (int) now()->format('H');
                $quietStart = (int) explode(':', $sequence->quiet_hours_start)[0];
                $quietEnd = (int) explode(':', $sequence->quiet_hours_end)[0];

                $inQuietHours = false;
                if ($quietStart > $quietEnd) {
                    $inQuietHours = ($currentHour >= $quietStart || $currentHour < $quietEnd);
                } else {
                    $inQuietHours = ($currentHour >= $quietStart && $currentHour < $quietEnd);
                }

                if ($inQuietHours) {
                    $enrollment->update([
                        'next_execution_at' => now()->startOfDay()->addHours($quietEnd)->addMinutes(5),
                    ]);
                    continue;
                }
            }

            // 3. Process according to step_type
            $stepType = $step->step_type ?? 'send_message';
            $nextTargetStepOrder = null;

            if ($stepType === 'condition_check') {
                $rules = $step->condition_rules ?? [];
                $conditionMet = $this->evaluateCondition($rules, $enrollment);

                if ($conditionMet) {
                    // YES branch target
                    $nextTargetStepOrder = $rules['yes_step_order'] ?? ($step->step_order + 1);
                } else {
                    // NO branch target
                    $nextTargetStepOrder = $rules['no_step_order'] ?? ($step->step_order + 2);
                }
            } elseif ($stepType === 'assign_rep') {
                if ($enrollment->entity_type === 'lead') {
                    $lead = Lead::where('tenant_id', $tenantId)->find($enrollment->entity_id);
                    if ($lead) {
                        $targetUser = User::where('tenant_id', $tenantId)->first();
                        if ($targetUser) {
                            $lead->update(['assigned_user_id' => $targetUser->id]);
                        }
                    }
                }
            } elseif ($stepType === 'schedule_followup') {
                if (class_exists(Activity::class)) {
                    Activity::create([
                        'tenant_id' => $tenantId,
                        'title' => 'Follow up: Drip sequence action ' . $sequence->name,
                        'type' => 'followup',
                        'due_date' => now()->addDay(),
                        'entity_type' => $enrollment->entity_type,
                        'entity_id' => $enrollment->entity_id,
                        'status' => 'pending',
                    ]);
                }
            } else {
                // send_message or wait_delay
                $enrollment->update(['last_message_sent_at' => now()]);
            }

            // 4. Advance to Next Step or Complete Sequence
            $query = DripStep::where('drip_sequence_id', $sequence->id);
            if ($nextTargetStepOrder !== null) {
                $query->where('step_order', '>=', $nextTargetStepOrder);
            } else {
                $query->where('step_order', '>', $step->step_order);
            }

            $nextStep = $query->orderBy('step_order')->first();

            if ($nextStep) {
                $nextExec = now()->add($nextStep->delay_interval, $nextStep->delay_unit);
                $enrollment->update([
                    'current_step_id' => $nextStep->id,
                    'next_execution_at' => $nextExec,
                ]);
            } else {
                $enrollment->update([
                    'status' => 'completed',
                    'next_execution_at' => null,
                ]);
                $sequence->decrement('active_enrollments_count');
                $sequence->increment('completed_enrollments_count');
            }

            $processed++;
        }

        return [
            'due_count' => $dueEnrollments->count(),
            'processed' => $processed,
            'exited' => $exited,
        ];
    }

    /**
     * Evaluate conditional rules for a branch step.
     */
    protected function evaluateCondition(array $rules, DripEnrollment $enrollment): bool
    {
        $check = $rules['check'] ?? 'replied';

        if ($check === 'replied') {
            return !empty($enrollment->context_data['customer_replied']);
        }

        if ($check === 'clicked') {
            return !empty($enrollment->context_data['link_clicked']);
        }

        if ($check === 'opened') {
            return !empty($enrollment->context_data['email_opened']);
        }

        if ($check === 'deal_advanced' && $enrollment->entity_type === 'lead') {
            $lead = Lead::where('tenant_id', $enrollment->tenant_id)->find($enrollment->entity_id);
            return $lead && in_array($lead->status, ['qualified', 'proposal_sent', 'customer', 'converted']);
        }

        return false;
    }

    /**
     * Resolve recipient address based on entity and channel.
     */
    protected function resolveRecipientAddress(int $tenantId, string $entityType, int $entityId, string $channel): ?string
    {
        if ($entityType === 'lead') {
            $lead = Lead::where('tenant_id', $tenantId)->find($entityId);
            return $channel === 'email' ? $lead?->email : ($lead?->phone ?: $lead?->email);
        }
        return null;
    }
}
