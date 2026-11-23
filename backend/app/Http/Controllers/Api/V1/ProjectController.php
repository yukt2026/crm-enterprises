<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected ProjectService $projectService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $query = Project::where('tenant_id', $tenantId)
            ->with(['customer', 'company', 'owner', 'manager', 'teamMembers'])
            ->withCount(['tasks', 'milestones']);

        if ($request->boolean('archived')) {
            $query->archived();
        } else {
            $query->active();
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('manager_user_id')) {
            $query->where('manager_user_id', $request->manager_user_id);
        }

        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%')
                  ->orWhere('code', 'like', '%' . $request->q . '%')
                  ->orWhere('description', 'like', '%' . $request->q . '%');
            });
        }

        $query->orderByDesc('id');

        $perPage = (int) $request->get('limit', $request->get('per_page', 15));
        return $this->paginatedResponse($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'deal_id' => 'nullable|exists:deals,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'branch_id' => 'nullable|exists:branches,id',
            'owner_id' => 'nullable|exists:users,id',
            'manager_user_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string|in:draft,planned,active,not_started,in_progress,on_hold,at_risk,completed,cancelled,archived',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'budget' => 'nullable|numeric|min:0',
            'labor_cost_rate' => 'nullable|numeric|min:0',
            'billing_rate' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'progress' => 'nullable|integer|between:0,100',
            'client_portal_enabled' => 'nullable|boolean',
            'team_members' => 'nullable|array',
            'milestones' => 'nullable|array',
        ]);

        $project = $this->projectService->createProject($validated, $tenantId, auth()->id());

        return $this->successResponse($project, 'Project created successfully.', 201);
    }

    public function createFromDeal(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'deal_id' => 'required|exists:deals,id',
            'name' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'manager_user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $overrides = array_filter($validated, fn($key) => $key !== 'deal_id', ARRAY_FILTER_USE_KEY);
        $project = $this->projectService->createProjectFromDeal((int) $validated['deal_id'], $overrides, $tenantId, auth()->id() ?? 1);

        return $this->successResponse($project, 'Project created from deal successfully.', 201);
    }

    public function createFromQuotation(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'quotation_id' => 'required|exists:quotations,id',
            'name' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'manager_user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $overrides = array_filter($validated, fn($key) => $key !== 'quotation_id', ARRAY_FILTER_USE_KEY);
        $project = $this->projectService->createProjectFromQuotation((int) $validated['quotation_id'], $overrides, $tenantId, auth()->id() ?? 1);

        return $this->successResponse($project, 'Project created from quotation successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)
            ->with([
                'customer',
                'company',
                'owner',
                'manager',
                'teamMembers',
                'milestones.approvalLogs',
                'documents.uploadedByUser',
                'statusHistories.changedByUser',
                'comments.user',
                'tasks.assignedUser',
                'tasks.subtasks',
                'tasks.assignees',
                'meetings',
            ])
            ->findOrFail($id);

        return $this->successResponse($project);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'deal_id' => 'nullable|exists:deals,id',
            'quotation_id' => 'nullable|exists:quotations,id',
            'branch_id' => 'nullable|exists:branches,id',
            'owner_id' => 'nullable|exists:users,id',
            'manager_user_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|string|in:draft,planned,active,not_started,in_progress,on_hold,at_risk,completed,cancelled,archived',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
            'budget' => 'nullable|numeric|min:0',
            'labor_cost_rate' => 'nullable|numeric|min:0',
            'billing_rate' => 'nullable|numeric|min:0',
            'estimated_hours' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'progress' => 'nullable|integer|between:0,100',
            'client_portal_enabled' => 'nullable|boolean',
            'team_members' => 'nullable|array',
        ]);

        $project = $this->projectService->updateProject($project, $validated);

        return $this->successResponse($project, 'Project updated successfully.');
    }

    public function transitionStatus(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,planned,active,on_hold,at_risk,completed,cancelled,archived',
            'reason' => 'nullable|string|max:500',
        ]);

        $project = $this->projectService->transitionProjectStatus(
            $project,
            $validated['status'],
            auth()->id() ?? 1,
            $validated['reason'] ?? null
        );

        return $this->successResponse($project->load('statusHistories.changedByUser'), 'Project status transitioned successfully.');
    }

    public function financials(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $financials = $this->projectService->getFinancials($project);

        return $this->successResponse($financials);
    }

    public function gantt(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $gantt = $this->projectService->getGanttData($project);

        return $this->successResponse($gantt);
    }

    public function workload(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;

        $report = $this->projectService->getWorkloadReport($tenantId, $branchId);

        return $this->successResponse($report);
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);
        $project->delete();

        return $this->successResponse(null, 'Project deleted successfully.');
    }

    public function archive(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);
        $project = $this->projectService->archiveProject($project);

        return $this->successResponse($project, 'Project archived successfully.');
    }

    public function restore(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);
        $project = $this->projectService->restoreProject($project);

        return $this->successResponse($project, 'Project restored successfully.');
    }

    // --- MILESTONES ---

    public function milestones(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse($project->milestones()->with('approvalLogs')->orderBy('order_index')->get());
    }

    public function storeMilestone(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0',
            'billing_percentage' => 'nullable|numeric|min:0|max:100',
            'deliverables_summary' => 'nullable|string',
            'approval_status' => 'nullable|string',
            'status' => 'nullable|in:pending,in_progress,completed',
            'progress' => 'nullable|integer|between:0,100',
        ]);

        $milestone = $this->projectService->addMilestone($project, $validated);

        return $this->successResponse($milestone, 'Milestone created successfully.', 201);
    }

    public function updateMilestone(Request $request, int $projectId, int $milestoneId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $milestone = ProjectMilestone::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->findOrFail($milestoneId);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0',
            'billing_percentage' => 'nullable|numeric|min:0|max:100',
            'deliverables_summary' => 'nullable|string',
            'approval_status' => 'sometimes|string',
            'status' => 'sometimes|in:pending,in_progress,completed',
            'progress' => 'sometimes|integer|between:0,100',
        ]);

        $milestone = $this->projectService->updateMilestone($milestone, $validated);

        return $this->successResponse($milestone, 'Milestone updated successfully.');
    }

    public function destroyMilestone(int $projectId, int $milestoneId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $milestone = ProjectMilestone::where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->findOrFail($milestoneId);

        $this->projectService->deleteMilestone($milestone);

        return $this->successResponse(null, 'Milestone deleted successfully.');
    }

    // --- MEMBERS & COMMENTS ---

    public function addMember(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'nullable|string|in:manager,member,viewer',
        ]);

        $this->projectService->addMember($project, $validated['user_id'], $validated['role'] ?? 'member');

        return $this->successResponse($project->teamMembers, 'Member added to project.');
    }

    public function removeMember(int $id, int $userId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $this->projectService->removeMember($project, $userId);

        return $this->successResponse(null, 'Member removed from project.');
    }

    public function comments(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        return $this->successResponse($project->comments()->with('user')->latest()->get());
    }

    public function storeComment(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $project = Project::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'comment' => 'required|string',
            'attachments' => 'nullable|array',
        ]);

        $comment = $this->projectService->addComment(
            $project,
            auth()->id() ?? 1,
            $validated['comment'],
            $validated['attachments'] ?? null
        );

        return $this->successResponse($comment->load('user'), 'Comment added successfully.', 201);
    }

    public function metrics(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $metrics = $this->projectService->getProjectMetrics($tenantId);

        return $this->successResponse($metrics);
    }
}
