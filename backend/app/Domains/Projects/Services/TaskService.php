<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskTimeLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TaskService
{
    /**
     * Create a task
     */
    public function createTask(array $data, int $tenantId, ?int $userId = null): Task
    {
        $task = Task::create([
            'tenant_id' => $tenantId,
            'project_id' => $data['project_id'] ?? null,
            'parent_task_id' => $data['parent_task_id'] ?? null,
            'milestone_id' => $data['milestone_id'] ?? null,
            'created_by' => $data['created_by'] ?? $userId,
            'lead_id' => $data['lead_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'ticket_id' => $data['ticket_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => $data['status'] ?? 'todo',
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'reminder_at' => $data['reminder_at'] ?? null,
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurring_rule' => $data['recurring_rule'] ?? null,
            'checklist_items' => $data['checklist_items'] ?? [],
            'labels' => $data['labels'] ?? [],
            'is_blocked' => $data['is_blocked'] ?? false,
            'block_reason' => $data['block_reason'] ?? null,
            'order_index' => $data['order_index'] ?? 0,
        ]);

        if (!empty($data['assignee_ids']) && is_array($data['assignee_ids'])) {
            $syncData = [];
            foreach ($data['assignee_ids'] as $uid) {
                $syncData[$uid] = ['tenant_id' => $tenantId];
            }
            $task->assignees()->sync($syncData);
        } elseif ($task->assigned_user_id) {
            $task->assignees()->syncWithoutDetaching([
                $task->assigned_user_id => ['tenant_id' => $tenantId]
            ]);
        }

        if (!empty($data['depends_on_task_ids'])) {
            foreach ($data['depends_on_task_ids'] as $depId) {
                $this->addDependency($task, (int) $depId);
            }
        }

        if ($task->project) {
            $task->project->recalculateProgress();
        }

        return $task->fresh(['assignedUser', 'assignees', 'creator', 'project', 'dependsOnTasks', 'subtasks', 'milestone', 'timeLogs']);
    }

    /**
     * Update a task
     */
    public function updateTask(Task $task, array $data): Task
    {
        // Check if completing
        if (isset($data['status']) && in_array($data['status'], ['done', 'completed']) && !in_array($task->status, ['done', 'completed'])) {
            if (!$task->canComplete($uncompleted)) {
                $blockingTitles = $uncompleted->pluck('title')->implode(', ');
                throw ValidationException::withMessages([
                    'status' => ["Cannot complete task because prerequisite tasks are still incomplete: {$blockingTitles}"],
                ]);
            }
            $data['completed_at'] = Carbon::now();
        } elseif (isset($data['status']) && $data['status'] === 'cancelled') {
            $data['cancelled_at'] = Carbon::now();
        }

        $task->update($data);

        if (isset($data['assignee_ids']) && is_array($data['assignee_ids'])) {
            $syncData = [];
            foreach ($data['assignee_ids'] as $uid) {
                $syncData[$uid] = ['tenant_id' => $task->tenant_id];
            }
            $task->assignees()->sync($syncData);
        }

        if ($task->project) {
            $task->project->recalculateProgress();
        }

        return $task->fresh(['assignedUser', 'assignees', 'creator', 'project', 'dependsOnTasks', 'subtasks', 'milestone', 'timeLogs']);
    }

    /**
     * Explicitly complete a task
     */
    public function completeTask(Task $task, string $status = 'done'): Task
    {
        return $this->updateTask($task, [
            'status' => $status,
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * Log time spent on task with overlap detection
     */
    public function logTime(Task $task, int $userId, array $data): TaskTimeLog
    {
        $start = !empty($data['start_time']) ? Carbon::parse($data['start_time']) : null;
        $end = !empty($data['end_time']) ? Carbon::parse($data['end_time']) : null;

        $duration = $data['duration_minutes'] ?? 0;
        if (empty($duration) && $start && $end) {
            $duration = abs($end->diffInMinutes($start));
        }

        // Validate time overlap if start and end are provided
        if ($start && $end) {
            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages([
                    'end_time' => ['End time must be after start time.'],
                ]);
            }

            $overlap = TaskTimeLog::where('user_id', $userId)
                ->where('is_running', false)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('start_time', [$start, $end])
                          ->orWhereBetween('end_time', [$start, $end])
                          ->orWhere(function ($q) use ($start, $end) {
                              $q->where('start_time', '<=', $start)
                                ->where('end_time', '>=', $end);
                          });
                })
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_time' => ['Time entry overlaps with an existing logged interval for this user.'],
                ]);
            }
        }

        $hourlyRate = (float) ($data['hourly_rate'] ?? ($task->project?->billing_rate ?: $task->project?->labor_cost_rate ?: 500));
        $isBillable = $data['is_billable'] ?? true;
        $billableAmount = $isBillable ? round(($duration / 60) * $hourlyRate, 2) : 0;

        return TaskTimeLog::create([
            'tenant_id' => $task->tenant_id,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'user_id' => $userId,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $duration,
            'description' => $data['description'] ?? null,
            'is_billable' => $isBillable,
            'hourly_rate' => $hourlyRate,
            'billable_amount' => $billableAmount,
            'is_running' => false,
            'status' => 'pending',
        ]);
    }

    /**
     * Start live timer for task
     */
    public function startTimer(Task $task, int $userId): TaskTimeLog
    {
        // Stop any currently running timer for this user
        $activeLog = TaskTimeLog::where('user_id', $userId)->where('is_running', true)->first();
        if ($activeLog) {
            $this->stopTimer($activeLog->id, $userId, 'Auto-stopped on new timer start');
        }

        $hourlyRate = (float) ($task->project?->billing_rate ?: $task->project?->labor_cost_rate ?: 500);

        return TaskTimeLog::create([
            'tenant_id' => $task->tenant_id,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'user_id' => $userId,
            'start_time' => Carbon::now(),
            'end_time' => null,
            'duration_minutes' => 0,
            'description' => 'Timer started',
            'is_billable' => true,
            'hourly_rate' => $hourlyRate,
            'billable_amount' => 0,
            'is_running' => true,
            'status' => 'pending',
        ]);
    }

    /**
     * Stop live timer for task
     */
    public function stopTimer(int $logId, int $userId, ?string $description = null): TaskTimeLog
    {
        $log = TaskTimeLog::where('user_id', $userId)->findOrFail($logId);
        $stopTime = Carbon::now();
        $startTime = $log->start_time ?: $stopTime;
        $duration = max(1, abs($stopTime->diffInMinutes($startTime)));
        $billableAmount = $log->is_billable ? round(($duration / 60) * (float) $log->hourly_rate, 2) : 0;

        $log->update([
            'end_time' => $stopTime,
            'duration_minutes' => $duration,
            'billable_amount' => $billableAmount,
            'is_running' => false,
            'description' => $description ?: $log->description,
        ]);

        return $log;
    }

    /**
     * Approve timesheet log
     */
    public function approveTimeLog(int $logId, int $managerUserId): TaskTimeLog
    {
        $log = TaskTimeLog::findOrFail($logId);
        $log->update([
            'status' => 'approved',
            'approved_by_user_id' => $managerUserId,
            'approved_at' => Carbon::now(),
            'rejection_reason' => null,
        ]);

        return $log;
    }

    /**
     * Reject timesheet log
     */
    public function rejectTimeLog(int $logId, int $managerUserId, string $reason): TaskTimeLog
    {
        $log = TaskTimeLog::findOrFail($logId);
        $log->update([
            'status' => 'rejected',
            'approved_by_user_id' => $managerUserId,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ]);

        return $log;
    }

    /**
     * Add a dependency with cycle detection
     */
    public function addDependency(Task $task, int $dependsOnTaskId, string $type = 'finish_to_start', int $lagDays = 0): TaskDependency
    {
        if ($task->id === $dependsOnTaskId) {
            throw ValidationException::withMessages([
                'depends_on_task_id' => ['A task cannot depend on itself.'],
            ]);
        }

        // Circular check: does $dependsOnTaskId already depend directly or indirectly on $task->id?
        if ($this->hasCircularDependency($dependsOnTaskId, $task->id)) {
            throw ValidationException::withMessages([
                'depends_on_task_id' => ['Circular dependency detected. This dependency cannot be added.'],
            ]);
        }

        return TaskDependency::firstOrCreate([
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOnTaskId,
        ], [
            'dependency_type' => $type,
            'lag_days' => $lagDays,
        ]);
    }

    /**
     * Remove dependency
     */
    public function removeDependency(Task $task, int $dependsOnTaskId): bool
    {
        return (bool) TaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $dependsOnTaskId)
            ->delete();
    }

    /**
     * Check if startId depends on targetId transitively (DFS)
     */
    protected function hasCircularDependency(int $startId, int $targetId, array &$visited = []): bool
    {
        if ($startId === $targetId) {
            return true;
        }

        if (in_array($startId, $visited)) {
            return false;
        }

        $visited[] = $startId;

        $prerequisites = TaskDependency::where('task_id', $startId)->pluck('depends_on_task_id')->all();
        foreach ($prerequisites as $prereqId) {
            if ($this->hasCircularDependency($prereqId, $targetId, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get task metrics
     */
    public function getTaskMetrics(int $tenantId, ?int $userId = null): array
    {
        $base = Task::where('tenant_id', $tenantId);
        if ($userId) {
            $base->where('assigned_user_id', $userId);
        }

        $total = (clone $base)->count();
        $completed = (clone $base)->whereIn('status', ['done', 'completed'])->count();
        $pending = (clone $base)->whereNotIn('status', ['done', 'completed', 'cancelled'])->count();
        $overdue = (clone $base)->overdue()->count();
        $blocked = (clone $base)->where('is_blocked', true)->count();

        $byPriority = [
            'urgent' => (clone $base)->where('priority', 'urgent')->count(),
            'high' => (clone $base)->where('priority', 'high')->count(),
            'medium' => (clone $base)->where('priority', 'medium')->count(),
            'low' => (clone $base)->where('priority', 'low')->count(),
        ];

        $timeLogsQuery = TaskTimeLog::where('tenant_id', $tenantId);
        if ($userId) {
            $timeLogsQuery->where('user_id', $userId);
        }
        $totalMinutes = $timeLogsQuery->sum('duration_minutes');

        return [
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $pending,
            'overdue_tasks' => $overdue,
            'blocked_tasks' => $blocked,
            'by_priority' => $byPriority,
            'total_hours_logged' => round($totalMinutes / 60, 2),
        ];
    }
}
