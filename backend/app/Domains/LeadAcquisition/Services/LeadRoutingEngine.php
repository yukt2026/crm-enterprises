<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\LeadAssignment;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadRoutingRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadRoutingEngine
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Route and assign lead based on tenant routing rules with concurrency lock
     */
    public function routeAndAssign(Lead $lead, ?int $assignedByUserId = null): Lead
    {
        $tenantId = $lead->tenant_id;

        return DB::transaction(function () use ($lead, $tenantId, $assignedByUserId) {
            // Re-fetch lead with lock to prevent concurrent double-assignments
            $lockedLead = Lead::where('id', $lead->id)->lockForUpdate()->first();
            if (!$lockedLead) {
                return $lead;
            }

            // 1. Fetch active rules ordered by priority asc (lower number = higher priority)
            $rules = LeadRoutingRule::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('priority', 'asc')
                ->lockForUpdate()
                ->get();

            $assignedUserId = null;
            $matchedRule = null;
            $assignmentMethod = 'round_robin';

            foreach ($rules as $rule) {
                if ($this->matchesRuleConditions($rule, $lockedLead)) {
                    $matchedRule = $rule;
                    $assignmentMethod = $rule->assignment_method;
                    $assignedUserId = $this->resolveUserByMethod($rule, $lockedLead);
                    if ($assignedUserId) {
                        break;
                    }
                }
            }

            // 2. Fallback to default tenant user or rule fallback if none resolved
            if (!$assignedUserId) {
                $assignedUserId = $matchedRule?->fallback_user_id;
            }

            if (!$assignedUserId) {
                // Default to first active user in workspace
                $fallbackUser = User::whereHas('workspaces', fn($q) => $q->where('tenants.id', $tenantId))->first();
                $assignedUserId = $fallbackUser ? $fallbackUser->id : 1;
            }

            // 3. Apply Assignment
            $previousUserId = $lockedLead->assigned_user_id;
            $lockedLead->assigned_user_id = $assignedUserId;
            $lockedLead->save();

            // 4. Log Assignment & Activity
            LeadAssignment::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lockedLead->id,
                'assigned_by_user_id' => $assignedByUserId,
                'assigned_to_user_id' => $assignedUserId,
                'team_id' => $lockedLead->team_id ?? $matchedRule?->target_team_id,
                'assignment_type' => in_array($assignmentMethod, ['manual', 'round_robin', 'rule_based']) ? $assignmentMethod : 'rule_based',
                'reason' => $matchedRule ? "Matched Routing Rule: {$matchedRule->name} ({$assignmentMethod})" : 'Default workspace assignment',
                'created_at' => now(),
            ]);

            CrmActivity::create([
                'tenant_id' => $tenantId,
                'user_id' => $assignedByUserId,
                'lead_id' => $lockedLead->id,
                'activity_type' => 'assignment',
                'title' => "Lead Assigned to " . (User::find($assignedUserId)?->name ?? "User #{$assignedUserId}"),
                'description' => "Routing method: {$assignmentMethod}" . ($matchedRule ? " (Rule: {$matchedRule->name})" : ''),
                'metadata' => [
                    'rule_id' => $matchedRule?->id,
                    'assignment_method' => $assignmentMethod,
                    'previous_user_id' => $previousUserId,
                ],
            ]);

            // 5. Notify assigned representative
            $targetUser = User::find($assignedUserId);
            if ($targetUser) {
                try {
                    $this->notificationService->notify(
                        recipient: $targetUser,
                        type: 'lead.assigned',
                        title: "New Lead Assigned: {$lockedLead->name}",
                        message: "Lead from {$lockedLead->source} assigned to you. First contact response timer active.",
                        actionUrl: "/leads/{$lockedLead->id}",
                        data: [
                            'lead_id' => $lockedLead->id,
                            'source' => $lockedLead->source,
                            'sla_due_at' => $lockedLead->sla_first_response_due_at?->toIso8601String(),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error("Failed to notify user on lead assignment: " . $e->getMessage());
                }
            }

            return $lockedLead;
        });
    }

    /**
     * Check if a lead matches the rule's criteria
     */
    protected function matchesRuleConditions(LeadRoutingRule $rule, Lead $lead): bool
    {
        $conds = $rule->conditions;
        if (empty($conds)) {
            return true; // Catch-all rule
        }

        // Sources filter
        if (!empty($conds['sources']) && is_array($conds['sources'])) {
            $leadSource = strtolower($lead->source ?? '');
            $sources = array_map('strtolower', $conds['sources']);
            if (!in_array($leadSource, $sources)) {
                return false;
            }
        }

        // Locations (City / State) filter
        if (!empty($conds['cities']) && is_array($conds['cities'])) {
            $leadCity = strtolower($lead->city ?? '');
            $cities = array_map('strtolower', $conds['cities']);
            if (!in_array($leadCity, $cities)) {
                return false;
            }
        }

        // Product Interest filter
        if (!empty($conds['products']) && is_array($conds['products'])) {
            $leadProduct = strtolower($lead->product_interest ?? '');
            $matched = false;
            foreach ($conds['products'] as $p) {
                if (str_contains($leadProduct, strtolower($p))) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        // Minimum Budget filter
        if (!empty($conds['min_budget'])) {
            if ((float) $lead->estimated_value < (float) $conds['min_budget']) {
                return false;
            }
        }

        // Branch filter
        if (!empty($conds['branch_id'])) {
            if ((int) $lead->branch_id !== (int) $conds['branch_id']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve eligible user based on configured method
     */
    protected function resolveUserByMethod(LeadRoutingRule $rule, Lead $lead): ?int
    {
        $targetUserIds = $rule->target_user_ids ?? [];
        if (empty($targetUserIds)) {
            return $rule->fallback_user_id;
        }

        switch ($rule->assignment_method) {
            case 'least_active':
                // Find rep with fewest active/open leads
                $leastActive = DB::table('leads')
                    ->select('assigned_user_id', DB::raw('count(*) as open_leads'))
                    ->whereIn('assigned_user_id', $targetUserIds)
                    ->where('tenant_id', $lead->tenant_id)
                    ->whereNotIn('status', ['converted', 'lost', 'disqualified'])
                    ->groupBy('assigned_user_id')
                    ->pluck('open_leads', 'assigned_user_id')
                    ->toArray();

                $chosenId = $targetUserIds[0];
                $minCount = PHP_INT_MAX;
                foreach ($targetUserIds as $uid) {
                    $count = $leastActive[$uid] ?? 0;
                    if ($count < $minCount) {
                        $minCount = $count;
                        $chosenId = $uid;
                    }
                }
                return $chosenId;

            case 'round_robin':
            default:
                $index = $rule->last_assigned_index % count($targetUserIds);
                $chosenUserId = $targetUserIds[$index];
                $rule->last_assigned_index = ($index + 1) % count($targetUserIds);
                $rule->save();
                return $chosenUserId;
        }
    }
}
