<?php

namespace App\Domains\Omnichannel\Services;

use App\Domains\CRM\Models\SalesTeam;
use App\Domains\Omnichannel\Models\ConversationAssignmentLog;
use App\Domains\Omnichannel\Models\OmnichannelAgentPresence;
use App\Domains\Omnichannel\Models\OmnichannelRoutingRule;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OmnichannelRoutingService
{
    /**
     * Route a conversation automatically based on tenant routing rules or fallback least-active.
     */
    public function routeConversation(WhatsAppConversation $conversation, ?int $assignedByUserId = null): WhatsAppConversation
    {
        $tenantId = $conversation->tenant_id;
        $channel = $conversation->channel ?? 'whatsapp';

        // 1. Fetch active routing rules ordered by priority
        $rules = OmnichannelRoutingRule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(fn($q) => $q->where('channel', 'all')->orWhere('channel', $channel))
            ->orderBy('priority', 'asc')
            ->get();

        foreach ($rules as $rule) {
            $assigned = $this->evaluateRule($rule, $conversation, $assignedByUserId);
            if ($assigned) {
                return $conversation->fresh(['assignedUser', 'assignedTeam']);
            }
        }

        // 2. Default Fallback: Workload-aware Least-Active Agent in tenant
        return $this->routeLeastActive($conversation, null, $assignedByUserId, 'Default Workload-Aware Fallback');
    }

    /**
     * Evaluate a specific routing rule against a conversation.
     */
    protected function evaluateRule(OmnichannelRoutingRule $rule, WhatsAppConversation $conversation, ?int $assignedByUserId): bool
    {
        $conditions = $rule->conditions ?? [];

        // Check Branch condition
        if (!empty($conditions['branch_id'])) {
            $convBranch = $conversation->branch_id ?? $conversation->lead?->branch_id;
            if ((int)$convBranch !== (int)$conditions['branch_id']) {
                return false;
            }
        }

        // Check Department condition
        if (!empty($conditions['department_id'])) {
            $convDept = $conversation->department_id;
            if ((int)$convDept !== (int)$conditions['department_id']) {
                return false;
            }
        }

        // Check Language condition
        if (!empty($conditions['language'])) {
            if (strtolower($conversation->language ?? 'en') !== strtolower($conditions['language'])) {
                return false;
            }
        }

        // Check Lead Source condition
        if (!empty($conditions['lead_source'])) {
            $leadSource = $conversation->lead?->source ?? ($conversation->metadata['source'] ?? null);
            if (strtolower($leadSource ?? '') !== strtolower($conditions['lead_source'])) {
                return false;
            }
        }

        // Check Working Hours condition
        if (!empty($conditions['working_hours'])) {
            $isWithinHours = $this->isWithinWorkingHours($conditions['working_hours']);
            if (!$isWithinHours && empty($conditions['allow_after_hours'])) {
                return false;
            }
        }

        // Condition matched! Execute assignment based on rule type
        switch ($rule->rule_type) {
            case 'direct':
            case 'user':
                if ($rule->target_user_id) {
                    $this->performAssignment($conversation, $rule->target_user_id, $rule->target_team_id, 'rule_direct', "Matched rule '{$rule->name}'", $assignedByUserId);
                    return true;
                }
                break;

            case 'branch':
                $branchUsers = User::withoutGlobalScopes()
                    ->where('tenant_id', $conversation->tenant_id)
                    ->where('branch_id', $conditions['branch_id'] ?? $conversation->branch_id)
                    ->get();
                if ($branchUsers->isNotEmpty()) {
                    $bestUser = $this->pickLeastActiveUser($branchUsers, $conversation->tenant_id);
                    $this->performAssignment($conversation, $bestUser->id, $rule->target_team_id, 'rule_branch', "Matched branch rule '{$rule->name}'", $assignedByUserId);
                    return true;
                }
                break;

            case 'department':
                $deptUsers = User::withoutGlobalScopes()
                    ->where('tenant_id', $conversation->tenant_id)
                    ->where('department_id', $conditions['department_id'] ?? $conversation->department_id)
                    ->get();
                if ($deptUsers->isNotEmpty()) {
                    $bestUser = $this->pickLeastActiveUser($deptUsers, $conversation->tenant_id);
                    $this->performAssignment($conversation, $bestUser->id, $rule->target_team_id, 'rule_department', "Matched department rule '{$rule->name}'", $assignedByUserId);
                    return true;
                }
                break;

            case 'least_active':
                return (bool) $this->routeLeastActive($conversation, $rule->target_team_id, $assignedByUserId, "Matched least-active rule '{$rule->name}'", 'least_active');

            case 'round_robin':
                return (bool) $this->routeRoundRobin($conversation, $rule->target_team_id, $assignedByUserId, "Matched round-robin rule '{$rule->name}'");
        }

        return false;
    }

    /**
     * Route to least-active agent (lowest open conversation count).
     */
    public function routeLeastActive(
        WhatsAppConversation $conversation,
        ?int $teamId = null,
        ?int $assignedByUserId = null,
        ?string $reason = null,
        string $method = 'round_robin'
    ): WhatsAppConversation {
        $tenantId = $conversation->tenant_id;
        $usersQuery = User::withoutGlobalScopes()->where('tenant_id', $tenantId);

        if ($teamId) {
            $team = SalesTeam::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($teamId);
            if ($team && !empty($team->member_ids)) {
                $usersQuery->whereIn('id', $team->member_ids);
            }
        }

        $users = $usersQuery->get();
        if ($users->isEmpty()) {
            return $conversation;
        }

        $bestUser = $this->pickLeastActiveUser($users, $tenantId);
        $this->performAssignment($conversation, $bestUser->id, $teamId, $method, $reason ?? 'Auto-assigned to least active agent', $assignedByUserId);

        return $conversation;
    }

    /**
     * Route via standard round-robin.
     */
    public function routeRoundRobin(WhatsAppConversation $conversation, ?int $teamId = null, ?int $assignedByUserId = null, ?string $reason = null): WhatsAppConversation
    {
        $tenantId = $conversation->tenant_id;
        $usersQuery = User::withoutGlobalScopes()->where('tenant_id', $tenantId);

        if ($teamId) {
            $team = SalesTeam::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($teamId);
            if ($team && !empty($team->member_ids)) {
                $usersQuery->whereIn('id', $team->member_ids);
            }
        }

        $users = $usersQuery->get();
        if ($users->isEmpty()) {
            return $conversation;
        }

        // Find last assigned user and pick next in circular list
        $lastLog = ConversationAssignmentLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('assigned_to_user_id')
            ->latest('id')
            ->first();

        $lastUserId = $lastLog?->assigned_to_user_id;
        $selectedUser = $users->first();

        if ($lastUserId) {
            $index = $users->search(fn($u) => $u->id === $lastUserId);
            if ($index !== false) {
                $nextIndex = ($index + 1) % $users->count();
                $selectedUser = $users->get($nextIndex);
            }
        }

        $this->performAssignment($conversation, $selectedUser->id, $teamId, 'round_robin', $reason ?? 'Auto-assigned via round robin', $assignedByUserId);

        return $conversation;
    }

    /**
     * Helper to select user with lowest active open conversation count.
     */
    protected function pickLeastActiveUser($users, int $tenantId): User
    {
        $bestUser = $users->first();
        $lowestWorkload = PHP_INT_MAX;

        foreach ($users as $user) {
            $openCount = WhatsAppConversation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('assigned_user_id', $user->id)
                ->where('status', 'open')
                ->count();

            if ($openCount < $lowestWorkload) {
                $lowestWorkload = $openCount;
                $bestUser = $user;
            }
        }

        return $bestUser;
    }

    /**
     * Perform the actual assignment and record in audit log.
     */
    protected function performAssignment(
        WhatsAppConversation $conversation,
        ?int $userId,
        ?int $teamId,
        string $method,
        ?string $reason,
        ?int $assignedByUserId
    ): void {
        $conversation->update([
            'assigned_user_id' => $userId,
            'assigned_team_id' => $teamId,
        ]);

        ConversationAssignmentLog::create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'assigned_by_user_id' => $assignedByUserId,
            'assigned_to_user_id' => $userId,
            'assigned_to_team_id' => $teamId,
            'routing_method' => $method,
            'reason' => $reason,
        ]);
    }

    /**
     * Check if current time is within business hours schedule.
     */
    public function isWithinWorkingHours(?array $schedule): bool
    {
        if (empty($schedule)) {
            return true;
        }

        $tz = $schedule['timezone'] ?? 'Asia/Kolkata';
        $now = now()->setTimezone($tz);
        $dayOfWeek = $now->dayOfWeekIso; // 1 (Mon) to 7 (Sun)

        $allowedDays = $schedule['days'] ?? [1, 2, 3, 4, 5]; // Mon-Fri default
        if (!in_array($dayOfWeek, $allowedDays)) {
            return false;
        }

        $startStr = $schedule['start'] ?? '09:00';
        $endStr = $schedule['end'] ?? '18:00';

        $startTime = Carbon::createFromTimeString($startStr, $tz);
        $endTime = Carbon::createFromTimeString($endStr, $tz);

        return $now->between($startTime, $endTime);
    }
}
