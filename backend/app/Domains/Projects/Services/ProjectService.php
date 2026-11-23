<?php

namespace App\Domains\Projects\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectComment;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskTimeLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    /**
     * Generate unique project code: PRJ-YYYY-XXXX
     */
    public function generateProjectCode(int $tenantId): string
    {
        $year = Carbon::now()->format('Y');
        $count = Project::where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        $code = sprintf('PRJ-%s-%04d', $year, $count);
        while (Project::where('tenant_id', $tenantId)->where('code', $code)->exists()) {
            $count++;
            $code = sprintf('PRJ-%s-%04d', $year, $count);
        }

        return $code;
    }

    /**
     * Create a new project
     */
    public function createProject(array $data, int $tenantId, ?int $ownerId = null): Project
    {
        $code = $data['code'] ?? $this->generateProjectCode($tenantId);

        $project = Project::create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'customer_id' => $data['customer_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'quotation_id' => $data['quotation_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? $ownerId,
            'manager_user_id' => $data['manager_user_id'] ?? $data['owner_id'] ?? $ownerId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'priority' => $data['priority'] ?? 'medium',
            'budget' => $data['budget'] ?? 0,
            'labor_cost_rate' => $data['labor_cost_rate'] ?? 0,
            'billing_rate' => $data['billing_rate'] ?? 0,
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'progress' => $data['progress'] ?? 0,
            'client_portal_enabled' => $data['client_portal_enabled'] ?? false,
            'is_archived' => false,
        ]);

        if (!empty($data['team_members'])) {
            foreach ($data['team_members'] as $member) {
                if (is_array($member)) {
                    $project->teamMembers()->syncWithoutDetaching([
                        $member['user_id'] => ['role' => $member['role'] ?? 'member']
                    ]);
                } else {
                    $project->teamMembers()->syncWithoutDetaching([
                        $member => ['role' => 'member']
                    ]);
                }
            }
        }

        if (!empty($data['milestones'])) {
            foreach ($data['milestones'] as $milestoneData) {
                $this->addMilestone($project, $milestoneData);
            }
        }

        return $project->fresh(['customer', 'company', 'owner', 'manager', 'teamMembers', 'milestones']);
    }

    /**
     * Create project directly from a Won Deal
     */
    public function createProjectFromDeal(int $dealId, array $overrides, int $tenantId, int $userId): Project
    {
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($dealId);

        $payload = array_merge([
            'name' => $deal->name ? "Delivery: {$deal->name}" : "Deal Project #{$deal->id}",
            'customer_id' => $deal->customer_id,
            'company_id' => $deal->company_id,
            'deal_id' => $deal->id,
            'branch_id' => $deal->branch_id,
            'manager_user_id' => $deal->assigned_user_id ?: $userId,
            'budget' => (float) ($deal->value ?: 0),
            'status' => 'planned',
            'priority' => 'high',
            'notes' => $deal->notes,
        ], $overrides);

        return $this->createProject($payload, $tenantId, $userId);
    }

    /**
     * Create project directly from an Approved Quotation
     */
    public function createProjectFromQuotation(int $quotationId, array $overrides, int $tenantId, int $userId): Project
    {
        $quotation = Quotation::with('items')->where('tenant_id', $tenantId)->findOrFail($quotationId);

        $milestones = [];
        if (!empty($quotation->items)) {
            $order = 1;
            foreach ($quotation->items as $item) {
                $milestones[] = [
                    'title' => $item->item_name ?: ("Milestone " . $order),
                    'description' => $item->description,
                    'amount' => $item->total ?: 0,
                    'order_index' => $order++,
                ];
            }
        }

        $payload = array_merge([
            'name' => "Delivery: Quotation #" . ($quotation->quote_number ?: $quotation->id),
            'customer_id' => $quotation->customer_id,
            'company_id' => $quotation->company_id,
            'deal_id' => $quotation->deal_id,
            'quotation_id' => $quotation->id,
            'manager_user_id' => $userId,
            'budget' => (float) ($quotation->total_amount ?: 0),
            'status' => 'planned',
            'priority' => 'medium',
            'milestones' => $milestones,
        ], $overrides);

        return $this->createProject($payload, $tenantId, $userId);
    }

    /**
     * Update an existing project
     */
    public function updateProject(Project $project, array $data): Project
    {
        if (isset($data['status']) && in_array($data['status'], ['completed', 'done']) && $project->status !== 'completed') {
            $data['completed_at'] = Carbon::now();
            $data['progress'] = 100;
        }

        $project->update($data);

        if (isset($data['team_members'])) {
            $syncData = [];
            foreach ($data['team_members'] as $member) {
                if (is_array($member)) {
                    $syncData[$member['user_id']] = ['role' => $member['role'] ?? 'member'];
                } else {
                    $syncData[$member] = ['role' => 'member'];
                }
            }
            $project->teamMembers()->sync($syncData);
        }

        return $project->fresh(['customer', 'company', 'owner', 'manager', 'teamMembers', 'milestones']);
    }

    /**
     * Transition project status with history logging
     */
    public function transitionProjectStatus(Project $project, string $newStatus, int $userId, ?string $reason = null): Project
    {
        return $project->transitionStatus($newStatus, $userId, $reason);
    }

    /**
     * Calculate project real-time financials and profitability
     */
    public function getFinancials(Project $project): array
    {
        return $project->calculateFinancials();
    }

    /**
     * Get CPM Gantt Chart data for project
     */
    public function getGanttData(Project $project): array
    {
        $tasks = Task::with(['dependsOnTasks', 'subtasks', 'assignedUser', 'milestone'])
            ->where('project_id', $project->id)
            ->orderBy('order_index')
            ->orderBy('start_date')
            ->get();

        $milestones = ProjectMilestone::where('project_id', $project->id)
            ->orderBy('order_index')
            ->orderBy('due_date')
            ->get();

        $formattedTasks = [];
        foreach ($tasks as $task) {
            $formattedTasks[] = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'start_date' => $task->start_date ? $task->start_date->toDateString() : null,
                'due_date' => $task->due_date ? $task->due_date->toDateString() : null,
                'estimated_hours' => (float) $task->estimated_hours,
                'is_blocked' => (bool) $task->is_blocked,
                'block_reason' => $task->block_reason,
                'parent_task_id' => $task->parent_task_id,
                'milestone_id' => $task->milestone_id,
                'milestone_title' => $task->milestone?->title,
                'assigned_user' => $task->assignedUser ? [
                    'id' => $task->assignedUser->id,
                    'name' => $task->assignedUser->name,
                ] : null,
                'dependencies' => $task->dependsOnTasks->map(fn($dep) => [
                    'depends_on_task_id' => $dep->id,
                    'title' => $dep->title,
                    'dependency_type' => $dep->pivot->dependency_type ?? 'finish_to_start',
                    'lag_days' => (int) ($dep->pivot->lag_days ?? 0),
                ])->all(),
                'subtasks_count' => $task->subtasks->count(),
            ];
        }

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'start_date' => $project->start_date ? $project->start_date->toDateString() : null,
                'end_date' => $project->end_date ? $project->end_date->toDateString() : null,
                'progress' => $project->progress,
                'status' => $project->status,
            ],
            'milestones' => $milestones->map(fn($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'due_date' => $m->due_date ? $m->due_date->toDateString() : null,
                'status' => $m->status,
                'approval_status' => $m->approval_status,
                'progress' => $m->progress,
                'amount' => (float) $m->amount,
            ])->all(),
            'tasks' => $formattedTasks,
        ];
    }

    /**
     * Get team workload report
     */
    public function getWorkloadReport(int $tenantId, ?int $branchId = null): array
    {
        $usersQuery = User::where('tenant_id', $tenantId);
        if ($branchId) {
            $usersQuery->where('branch_id', $branchId);
        }
        $users = $usersQuery->get();

        $workload = [];
        $today = Carbon::today()->toDateString();

        foreach ($users as $user) {
            $activeTasks = Task::where('tenant_id', $tenantId)
                ->where('assigned_user_id', $user->id)
                ->whereNotIn('status', ['done', 'completed', 'cancelled'])
                ->get();

            $completedTasks = Task::where('tenant_id', $tenantId)
                ->where('assigned_user_id', $user->id)
                ->whereIn('status', ['done', 'completed'])
                ->count();

            $overdueTasks = $activeTasks->filter(function ($task) use ($today) {
                return $task->due_date && $task->due_date->toDateString() < $today;
            })->count();

            $totalEstimatedHours = (float) $activeTasks->sum('estimated_hours');
            $loggedMinutes = TaskTimeLog::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->sum('duration_minutes');
            $loggedHours = round($loggedMinutes / 60, 2);

            // Capacity baseline: 40 hrs/week
            $capacityHours = 40.0;
            $utilizationPct = $capacityHours > 0 ? min(150.0, round(($totalEstimatedHours / $capacityHours) * 100, 1)) : 0.0;

            $workload[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'active_tasks_count' => $activeTasks->count(),
                'completed_tasks_count' => $completedTasks,
                'overdue_tasks_count' => $overdueTasks,
                'estimated_hours' => $totalEstimatedHours,
                'logged_hours' => $loggedHours,
                'capacity_hours' => $capacityHours,
                'utilization_percentage' => $utilizationPct,
                'is_overloaded' => $utilizationPct > 100,
            ];
        }

        return $workload;
    }

    /**
     * Archive project
     */
    public function archiveProject(Project $project): Project
    {
        $project->update([
            'is_archived' => true,
            'archived_at' => Carbon::now(),
            'status' => 'archived',
        ]);

        return $project;
    }

    /**
     * Restore archived project
     */
    public function restoreProject(Project $project): Project
    {
        $project->update([
            'is_archived' => false,
            'archived_at' => null,
            'status' => 'active',
        ]);

        return $project;
    }

    /**
     * Add a milestone to the project
     */
    public function addMilestone(Project $project, array $data): ProjectMilestone
    {
        $milestone = ProjectMilestone::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'amount' => $data['amount'] ?? 0,
            'billing_percentage' => $data['billing_percentage'] ?? 0,
            'deliverables_summary' => $data['deliverables_summary'] ?? null,
            'approval_status' => $data['approval_status'] ?? 'pending_review',
            'order_index' => $data['order_index'] ?? (ProjectMilestone::where('project_id', $project->id)->count() + 1),
            'status' => $data['status'] ?? 'pending',
            'progress' => $data['progress'] ?? 0,
            'completed_at' => ($data['status'] ?? '') === 'completed' ? Carbon::now() : null,
        ]);

        $project->recalculateProgress();

        return $milestone;
    }

    /**
     * Update a milestone
     */
    public function updateMilestone(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        if (isset($data['status']) && $data['status'] === 'completed' && $milestone->status !== 'completed') {
            $data['completed_at'] = Carbon::now();
            $data['progress'] = 100;
        }

        $milestone->update($data);

        $milestone->project->recalculateProgress();

        return $milestone;
    }

    /**
     * Delete milestone
     */
    public function deleteMilestone(ProjectMilestone $milestone): bool
    {
        $project = $milestone->project;
        $deleted = $milestone->delete();
        if ($project) {
            $project->recalculateProgress();
        }
        return (bool) $deleted;
    }

    /**
     * Add team member
     */
    public function addMember(Project $project, int $userId, string $role = 'member'): void
    {
        $project->teamMembers()->syncWithoutDetaching([
            $userId => ['role' => $role]
        ]);
    }

    /**
     * Remove team member
     */
    public function removeMember(Project $project, int $userId): void
    {
        $project->teamMembers()->detach($userId);
    }

    /**
     * Add a project comment / activity note
     */
    public function addComment(Project $project, int $userId, string $comment, ?array $attachments = null): ProjectComment
    {
        return ProjectComment::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'comment' => $comment,
            'attachments' => $attachments,
        ]);
    }

    /**
     * Get overall project statistics
     */
    public function getProjectMetrics(int $tenantId): array
    {
        $base = Project::where('tenant_id', $tenantId);

        $total = (clone $base)->count();
        $active = (clone $base)->where('is_archived', false)->whereNotIn('status', ['completed', 'cancelled', 'archived'])->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $onHold = (clone $base)->where('status', 'on_hold')->count();
        $atRisk = (clone $base)->where('status', 'at_risk')->count();
        $totalBudget = (clone $base)->sum('budget');
        $avgProgress = (clone $base)->where('is_archived', false)->avg('progress') ?? 0;

        return [
            'total_projects' => $total,
            'active_projects' => $active,
            'completed_projects' => $completed,
            'on_hold_projects' => $onHold,
            'at_risk_projects' => $atRisk,
            'total_budget' => round((float) $totalBudget, 2),
            'average_progress' => round((float) $avgProgress, 1),
        ];
    }
}
