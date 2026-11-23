<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskTimeLog;
use Carbon\Carbon;
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
        ]);

        if (!empty($data['depends_on_task_ids'])) {
            foreach ($data['depends_on_task_ids'] as $depId) {
                $this->addDependency($task, (int) $depId);
            }
        }

        if ($task->project) {
            $task->project->recalculateProgress();
        }

        return $task->fresh(['assignedUser', 'creator', 'project', 'dependsOnTasks', 'timeLogs']);
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

        if ($task->project) {
            $task->project->recalculateProgress();
        }

        return $task->fresh(['assignedUser', 'creator', 'project', 'dependsOnTasks', 'timeLogs']);
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
     * Log time spent on task
     */
    public function logTime(Task $task, int $userId, array $data): TaskTimeLog
    {
        $duration = $data['duration_minutes'] ?? 0;

        if (empty($duration) && !empty($data['start_time']) && !empty($data['end_time'])) {
            $start = Carbon::parse($data['start_time']);
            $end = Carbon::parse($data['end_time']);
            $duration = abs($end->diffInMinutes($start));
        }

        return TaskTimeLog::create([
            'tenant_id' => $task->tenant_id,
            'task_id' => $task->id,
            'user_id' => $userId,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'duration_minutes' => $duration,
            'description' => $data['description'] ?? null,
            'is_billable' => $data['is_billable'] ?? true,
        ]);
    }

    /**
     * Add a dependency with cycle detection
     */
    public function addDependency(Task $task, int $dependsOnTaskId, string $type = 'finish_to_start'): TaskDependency
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
            'by_priority' => $byPriority,
            'total_hours_logged' => round($totalMinutes / 60, 2),
        ];
    }
}
