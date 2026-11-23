<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Workflow\Models\WorkflowApproval;
use App\Domains\Workflow\Services\WorkflowExecutionEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowApprovalController extends Controller
{
    public function __construct(
        protected WorkflowExecutionEngine $engine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $query = WorkflowApproval::where('tenant_id', $tenantId)
            ->with(['workflow:id,name,category', 'run:id,status,started_at', 'requester:id,name', 'decisionBy:id,name'])
            ->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('approver_role')) {
            $query->where('approver_role', $request->approver_role);
        }

        $approvals = $query->paginate((int)$request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $approvals->items(),
            'meta' => [
                'current_page' => $approvals->currentPage(),
                'last_page' => $approvals->lastPage(),
                'total' => $approvals->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $approval = WorkflowApproval::where('tenant_id', $tenantId)
            ->with(['workflow', 'run.stepLogs', 'requester', 'decisionBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $approval,
        ]);
    }

    public function decide(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $approval = WorkflowApproval::where('tenant_id', $tenantId)->findOrFail($id);

        if ($approval->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Approval #{$id} is already in [{$approval->status}] state.",
            ], 400);
        }

        $validated = $request->validate([
            'decision' => 'required|string|in:approve,reject',
            'comments' => 'nullable|string|max:500',
        ]);

        $run = $this->engine->decideApproval(
            approval: $approval,
            decision: $validated['decision'],
            comments: $validated['comments'] ?? null,
            user: $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "Approval request #{$id} successfully {$validated['decision']}d.",
            'data' => [
                'approval' => $approval->fresh(['decisionBy']),
                'run' => $run,
            ],
        ]);
    }
}
