<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Services\SalesTeamService;
use App\Domains\Communications\Services\NotificationService;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskTimeLog;
use App\Domains\Projects\Services\TaskService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected SalesTeamService $salesTeamService,
        protected NotificationService $notificationService,
        protected TaskService $taskService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $user = auth()->user();

        $query = Task::where('tenant_id', $tenantId)
            ->with(['assignedUser', 'assignees', 'creator', 'lead', 'deal', 'contact', 'company', 'project', 'dependsOnTasks', 'subtasks', 'milestone', 'timeLogs']);

        // View scope filters
        $view = $request->get('view', 'all');
        if ($view === 'my_tasks' && $user) {
            $query->myTasks($user->id);
        } elseif ($view === 'today') {
            $query->today();
        } elseif ($view === 'upcoming') {
            $query->upcoming((int) $request->get('days', 7));
        } elseif ($view === 'overdue') {
            $query->overdue();
        } elseif ($view === 'completed') {
            $query->completed();
        } elseif ($user && $user->role === 'Sales Executive') {
            $query->myTasks($user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('milestone_id')) {
            $query->where('milestone_id', $request->milestone_id);
        }

        if ($request->filled('parent_task_id')) {
            $query->where('parent_task_id', $request->parent_task_id);
        }

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->assigned_user_id);
        }

        if ($request->boolean('is_blocked')) {
            $query->where('is_blocked', true);
        }

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }

        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $query->orderByRaw("CASE WHEN status IN ('done', 'completed') THEN 2 ELSE 1 END")
              ->orderBy('order_index', 'asc')
              ->orderBy('due_date', 'asc')
              ->orderByDesc('id');

        $perPage = (int) $request->get('limit', $request->get('per_page', 25));
        return $this->paginatedResponse($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|string|in:todo,in_progress,in_review,completed,done,cancelled',
            'assigned_user_id' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'reminder_at' => 'nullable|date',
            'is_recurring' => 'nullable|boolean',
            'recurring_rule' => 'nullable|string|in:daily,weekly,monthly',
            'checklist_items' => 'nullable|array',
            'labels' => 'nullable|array',
            'is_blocked' => 'nullable|boolean',
            'block_reason' => 'nullable|string',
            'order_index' => 'nullable|integer',
            'lead_id' => 'nullable|exists:leads,id',
            'deal_id' => 'nullable|exists:deals,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'company_id' => 'nullable|exists:companies,id',
            'project_id' => 'nullable|exists:projects,id',
            'depends_on_task_ids' => 'nullable|array',
        ]);

        $task = $this->taskService->createTask($validated, $tenantId, auth()->id());

        // Register Activity if linked to lead or deal
        if ($task->lead_id || $task->deal_id) {
            CrmActivity::create([
                'workspace_id' => $tenantId,
                'lead_id' => $task->lead_id,
                'deal_id' => $task->deal_id,
                'activity_type' => 'task',
                'title' => "Task Created: {$task->title}",
                'description' => $task->description ?? "Due date: {$task->due_date}",
            ]);
        }

        // Notify assigned employee if different from creator
        if ($task->assignedUser && $task->assigned_user_id !== auth()->id()) {
            $this->notificationService->notify(
                $task->assignedUser,
                'task.assigned',
                "New Task: {$task->title}",
                "Assigned to you with due date " . ($task->due_date ? Carbon::parse($task->due_date)->format('M d, Y') : 'TBD'),
                "/tasks"
            );
        }

        return $this->successResponse($task->load(['assignedUser', 'assignees', 'lead', 'deal', 'project', 'milestone']), 'Task created successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)
            ->with(['assignedUser', 'assignees', 'creator', 'lead', 'deal', 'contact', 'company', 'project', 'milestone', 'subtasks.assignedUser', 'parentTask', 'dependsOnTasks', 'blockingTasks', 'timeLogs.user'])
            ->findOrFail($id);

        return $this->successResponse($task);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:todo,in_progress,in_review,completed,done,cancelled',
            'assigned_user_id' => 'nullable|exists:users,id',
            'assignee_ids' => 'nullable|array',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'milestone_id' => 'nullable|exists:project_milestones,id',
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'reminder_at' => 'nullable|date',
            'checklist_items' => 'nullable|array',
            'labels' => 'nullable|array',
            'is_blocked' => 'nullable|boolean',
            'block_reason' => 'nullable|string',
            'order_index' => 'nullable|integer',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $task = $this->taskService->updateTask($task, $validated);

        return $this->successResponse($task->load(['assignedUser', 'assignees', 'lead', 'deal', 'project', 'milestone', 'subtasks']), 'Task updated successfully.');
    }

    /**
     * Quick-toggle checklist item completion.
     */
    public function toggleChecklistItem(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'item_id' => 'required',
        ]);

        $items = $task->checklist_items ?? [];
        foreach ($items as &$item) {
            if ($item['id'] == $validated['item_id']) {
                $item['completed'] = !($item['completed'] ?? false);
                break;
            }
        }

        $task->update(['checklist_items' => $items]);
        return $this->successResponse($task, 'Checklist updated.');
    }

    /**
     * Mark task as completed with dependency verification.
     */
    public function complete(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $task = $this->taskService->completeTask($task);

        return $this->successResponse($task, 'Task marked as completed.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);
        $task->delete();

        return $this->successResponse(null, 'Task deleted successfully.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $request->validate(['ids' => 'required|array']);
        Task::where('tenant_id', $tenantId)->whereIn('id', $request->ids)->delete();
        return $this->successResponse(null, 'Selected tasks deleted successfully.');
    }

    // --- TIME LOGGING & LIVE TIMER ---

    public function logTime(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'duration_minutes' => 'nullable|integer|min:1',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'description' => 'nullable|string',
            'is_billable' => 'nullable|boolean',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        $log = $this->taskService->logTime($task, auth()->id() ?? 1, $validated);

        return $this->successResponse($log->load('user'), 'Time logged successfully.', 201);
    }

    public function startTimer(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $log = $this->taskService->startTimer($task, auth()->id() ?? 1);

        return $this->successResponse($log->load(['task', 'user']), 'Live timer started.', 201);
    }

    public function stopTimer(Request $request, int $logId): JsonResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string',
        ]);

        $log = $this->taskService->stopTimer($logId, auth()->id() ?? 1, $validated['description'] ?? null);

        return $this->successResponse($log->load(['task', 'user']), 'Live timer stopped.');
    }

    public function approveTimeLog(Request $request, int $logId): JsonResponse
    {
        $log = $this->taskService->approveTimeLog($logId, auth()->id() ?? 1);

        return $this->successResponse($log->load(['approvedByUser', 'user', 'task']), 'Time log approved successfully.');
    }

    public function rejectTimeLog(Request $request, int $logId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $log = $this->taskService->rejectTimeLog($logId, auth()->id() ?? 1, $validated['reason']);

        return $this->successResponse($log->load(['approvedByUser', 'user', 'task']), 'Time log rejected.');
    }

    public function timeLogs(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse($task->timeLogs()->with(['user', 'approvedByUser'])->latest()->get());
    }

    public function allTimesheets(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = TaskTimeLog::where('tenant_id', $tenantId)->with(['user', 'task.project', 'approvedByUser']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('project_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('project_id', $request->project_id)
                  ->orWhereHas('task', fn($t) => $t->where('project_id', $request->project_id));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('is_running')) {
            $query->where('is_running', true);
        }

        $query->orderByDesc('id');

        $perPage = (int) $request->get('limit', $request->get('per_page', 25));
        return $this->paginatedResponse($query->paginate($perPage));
    }

    // --- DEPENDENCIES ---

    public function dependencies(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse([
            'depends_on' => $task->dependsOnTasks,
            'blocking' => $task->blockingTasks,
        ]);
    }

    public function addDependency(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'depends_on_task_id' => 'required|exists:tasks,id',
            'dependency_type' => 'nullable|string|in:finish_to_start,start_to_start',
            'lag_days' => 'nullable|integer|min:0',
        ]);

        $dep = $this->taskService->addDependency(
            $task,
            $validated['depends_on_task_id'],
            $validated['dependency_type'] ?? 'finish_to_start',
            (int) ($validated['lag_days'] ?? 0)
        );

        return $this->successResponse($dep->load('dependsOnTask'), 'Dependency added successfully.', 201);
    }

    public function removeDependency(int $id, int $dependsOnTaskId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        $this->taskService->removeDependency($task, $dependsOnTaskId);

        return $this->successResponse(null, 'Dependency removed.');
    }

    // --- METRICS ---

    public function metrics(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = $request->boolean('my_metrics') ? auth()->id() : null;

        $metrics = $this->taskService->getTaskMetrics($tenantId, $userId);

        return $this->successResponse($metrics);
    }
}
