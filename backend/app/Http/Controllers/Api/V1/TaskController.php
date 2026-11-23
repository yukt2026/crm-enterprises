<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Services\SalesTeamService;
use App\Domains\Communications\Services\NotificationService;
use App\Domains\Projects\Models\Task;
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
            ->with(['assignedUser', 'creator', 'lead', 'deal', 'contact', 'company', 'project', 'dependsOnTasks', 'timeLogs']);

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

        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->assigned_user_id);
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
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'reminder_at' => 'nullable|date',
            'is_recurring' => 'nullable|boolean',
            'recurring_rule' => 'nullable|string|in:daily,weekly,monthly',
            'checklist_items' => 'nullable|array',
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

        return $this->successResponse($task->load(['assignedUser', 'lead', 'deal', 'project']), 'Task created successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)
            ->with(['assignedUser', 'creator', 'lead', 'deal', 'contact', 'company', 'project', 'dependsOnTasks', 'blockingTasks', 'timeLogs.user'])
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
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'reminder_at' => 'nullable|date',
            'checklist_items' => 'nullable|array',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $task = $this->taskService->updateTask($task, $validated);

        return $this->successResponse($task->load(['assignedUser', 'lead', 'deal', 'project']), 'Task updated successfully.');
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

    // --- TIME LOGGING ---

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
        ]);

        $log = $this->taskService->logTime($task, auth()->id() ?? 1, $validated);

        return $this->successResponse($log->load('user'), 'Time logged successfully.', 201);
    }

    public function timeLogs(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $task = Task::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse($task->timeLogs()->with('user')->latest()->get());
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
        ]);

        $dep = $this->taskService->addDependency(
            $task,
            $validated['depends_on_task_id'],
            $validated['dependency_type'] ?? 'finish_to_start'
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
