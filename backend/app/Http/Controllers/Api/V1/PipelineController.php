<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use App\Domains\CRM\Services\DealPipelineService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected DealPipelineService $pipelineService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->pipelineService->ensureDefaultPipeline($tenantId);

        $pipelines = Pipeline::where('tenant_id', $tenantId)
            ->with(['stages'])
            ->withCount('deals')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->successResponse($pipelines);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'owner_user_id' => 'nullable|exists:users,id',
            'business_model' => 'nullable|string|max:64',
            'department' => 'nullable|string|max:64',
            'branch_id' => 'nullable|integer',
            'is_default' => 'nullable|boolean',
            'stages' => 'nullable|array',
        ]);

        if (!empty($validated['is_default'])) {
            Pipeline::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $pipeline = Pipeline::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'owner_user_id' => $validated['owner_user_id'] ?? auth()->id(),
            'business_model' => $validated['business_model'] ?? null,
            'department' => $validated['department'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => true,
        ]);

        $stages = $validated['stages'] ?? [
            ['name' => 'Qualification', 'code' => 'qualification', 'probability' => 20, 'order_column' => 1, 'color' => '#3b82f6'],
            ['name' => 'Proposal', 'code' => 'proposal', 'probability' => 60, 'order_column' => 2, 'color' => '#f59e0b', 'required_fields' => ['value']],
            ['name' => 'Negotiation', 'code' => 'negotiation', 'probability' => 80, 'order_column' => 3, 'color' => '#ec4899', 'required_fields' => ['close_date', 'value']],
            ['name' => 'Closed Won', 'code' => 'won', 'probability' => 100, 'order_column' => 4, 'color' => '#10b981', 'is_won' => true],
            ['name' => 'Closed Lost', 'code' => 'lost', 'probability' => 0, 'order_column' => 5, 'color' => '#ef4444', 'is_lost' => true, 'required_fields' => ['lost_reason']],
        ];

        foreach ($stages as $idx => $s) {
            PipelineStage::create([
                'tenant_id' => $tenantId,
                'pipeline_id' => $pipeline->id,
                'name' => $s['name'],
                'code' => $s['code'] ?? strtolower(str_replace(' ', '_', $s['name'])),
                'probability' => $s['probability'] ?? 50,
                'order_column' => $s['order_column'] ?? ($idx + 1),
                'color' => $s['color'] ?? '#10b981',
                'is_won' => $s['is_won'] ?? false,
                'is_lost' => $s['is_lost'] ?? false,
                'required_fields' => $s['required_fields'] ?? null,
            ]);
        }

        return $this->successResponse($pipeline->load('stages'), 'Pipeline created successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $pipeline = Pipeline::where('tenant_id', $tenantId)->with('stages')->findOrFail($id);
        return $this->successResponse($pipeline);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            Pipeline::where('tenant_id', $tenantId)->where('id', '!=', $pipeline->id)->update(['is_default' => false]);
        }

        $pipeline->update($validated);
        return $this->successResponse($pipeline->load('stages'), 'Pipeline updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        if ($pipeline->is_default) {
            return $this->errorResponse('Cannot delete the default sales pipeline.', 422);
        }

        $pipeline->delete();
        return $this->successResponse(null, 'Pipeline deleted successfully.');
    }

    /**
     * Add custom stage to a pipeline.
     */
    public function addStage(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $pipeline = Pipeline::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'probability' => 'required|integer|min:0|max:100',
            'order_column' => 'nullable|integer',
            'color' => 'nullable|string|max:50',
            'is_won' => 'nullable|boolean',
            'is_lost' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'allowed_transitions' => 'nullable|array',
            'required_fields' => 'nullable|array',
            'stale_days_threshold' => 'nullable|integer|min:1',
        ]);

        $stage = PipelineStage::create([
            'tenant_id' => $tenantId,
            'pipeline_id' => $pipeline->id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'probability' => $validated['probability'],
            'order_column' => $validated['order_column'] ?? ($pipeline->stages()->max('order_column') + 1),
            'color' => $validated['color'] ?? '#10b981',
            'is_won' => $validated['is_won'] ?? false,
            'is_lost' => $validated['is_lost'] ?? false,
            'is_default' => $validated['is_default'] ?? false,
            'requires_approval' => $validated['requires_approval'] ?? false,
            'allowed_transitions' => $validated['allowed_transitions'] ?? null,
            'required_fields' => $validated['required_fields'] ?? null,
            'stale_days_threshold' => $validated['stale_days_threshold'] ?? 14,
        ]);

        return $this->successResponse($stage, 'Pipeline stage created.', 201);
    }

    /**
     * Update custom stage.
     */
    public function updateStage(Request $request, int $id, int $stageId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $stage = PipelineStage::where('tenant_id', $tenantId)->where('pipeline_id', $id)->findOrFail($stageId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|max:50',
            'probability' => 'sometimes|integer|min:0|max:100',
            'order_column' => 'sometimes|integer',
            'color' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'allowed_transitions' => 'nullable|array',
            'required_fields' => 'nullable|array',
            'stale_days_threshold' => 'nullable|integer|min:1',
        ]);

        $stage->update($validated);
        return $this->successResponse($stage, 'Stage updated.');
    }

    /**
     * Delete custom stage.
     */
    public function deleteStage(int $id, int $stageId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $stage = PipelineStage::where('tenant_id', $tenantId)->where('pipeline_id', $id)->findOrFail($stageId);

        if ($stage->deals()->count() > 0) {
            return $this->errorResponse('Cannot delete stage containing active deals. Move deals to another stage first.', 422);
        }

        $stage->delete();
        return $this->successResponse(null, 'Stage removed.');
    }
}
