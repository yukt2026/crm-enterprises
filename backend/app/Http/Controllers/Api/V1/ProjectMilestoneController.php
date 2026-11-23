<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Services\MilestoneApprovalService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected MilestoneApprovalService $milestoneService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function submitReview(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $milestone = ProjectMilestone::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'deliverables_summary' => 'nullable|string|max:1000',
        ]);

        $milestone = $this->milestoneService->submitForInternalReview(
            $milestone,
            auth()->id() ?? 1,
            $validated['deliverables_summary'] ?? null
        );

        return $this->successResponse($milestone, 'Milestone submitted for internal review.');
    }

    public function approveInternal(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $milestone = ProjectMilestone::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $milestone = $this->milestoneService->approveInternal(
            $milestone,
            auth()->id() ?? 1,
            $validated['notes'] ?? null
        );

        return $this->successResponse($milestone, 'Milestone internal review approved.');
    }

    public function sendToClient(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $milestone = ProjectMilestone::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $milestone = $this->milestoneService->sendToClient(
            $milestone,
            auth()->id() ?? 1,
            $validated['notes'] ?? null
        );

        return $this->successResponse($milestone, 'Milestone dispatched for client approval.');
    }

    public function getMilestoneByToken(string $token): JsonResponse
    {
        $milestone = ProjectMilestone::with(['project.documents', 'approvalLogs', 'tasks'])
            ->where('client_review_token', $token)
            ->firstOrFail();

        return $this->successResponse($milestone);
    }

    public function clientApprove(Request $request, string $token): JsonResponse
    {
        $milestone = ProjectMilestone::where('client_review_token', $token)->firstOrFail();

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $milestone = $this->milestoneService->clientApprove(
            $milestone,
            $validated['client_name'],
            $validated['feedback'] ?? null
        );

        return $this->successResponse($milestone, 'Milestone approved successfully by client.');
    }

    public function clientRevision(Request $request, string $token): JsonResponse
    {
        $milestone = ProjectMilestone::where('client_review_token', $token)->firstOrFail();

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'feedback' => 'required|string|max:1000',
        ]);

        $milestone = $this->milestoneService->clientRequestRevision(
            $milestone,
            $validated['client_name'],
            $validated['feedback']
        );

        return $this->successResponse($milestone, 'Revision requested on milestone.');
    }
}
