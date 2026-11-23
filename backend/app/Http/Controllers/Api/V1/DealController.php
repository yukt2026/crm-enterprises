<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\DealProduct;
use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use App\Domains\CRM\Services\DealPipelineService;
use App\Domains\CRM\Services\SalesTeamService;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected DealPipelineService $pipelineService,
        protected SalesTeamService $salesTeamService,
        protected RbacService $rbacService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->pipelineService->ensureDefaultPipeline($tenantId);

        $query = Deal::where('tenant_id', $tenantId)
            ->with(['company', 'contact', 'lead', 'assignedUser', 'pipelineStage', 'pipeline', 'products']);

        // Apply reporting hierarchy and RBAC data scoping
        $user = auth()->user();
        if ($user) {
            $query = $this->salesTeamService->applyHierarchyScope($query, $user, $tenantId);
            $query = $this->rbacService->applyDataScope($query, $user, 'deals', 'view');
        }

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where('name', 'like', $search);
        }

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->pipeline_id);
        }

        if ($request->filled('pipeline_stage_id')) {
            $query->where('pipeline_stage_id', $request->pipeline_stage_id);
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }

        if ($request->has('archived') && $request->archived === 'true') {
            $query->where('is_archived', true);
        } else {
            $query->where('is_archived', false);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = (int) $request->get('limit', $request->get('per_page', 25));
        return $this->paginatedResponse($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $pipeline = $this->pipelineService->ensureDefaultPipeline($tenantId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'customer_id' => 'nullable|exists:customers,id',
            'lead_id' => 'nullable|exists:leads,id',
            'pipeline_id' => 'nullable|exists:pipelines,id',
            'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
            'branch_id' => 'nullable|integer',
            'sales_team' => 'nullable|string|max:64',
            'value' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'stage' => 'nullable|string',
            'probability' => 'nullable|integer|min:0|max:100',
            'close_date' => 'nullable|date',
            'next_follow_up_at' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'collaborator_ids' => 'nullable|array',
            'notes' => 'nullable|string',
            'competitor' => 'nullable|string|max:150',
            'source' => 'nullable|string|max:50',
            'campaign' => 'nullable|string|max:150',
            'tags' => 'nullable|array',
        ]);

        $pipelineId = $validated['pipeline_id'] ?? $pipeline->id;
        $stageId = $validated['pipeline_stage_id'] ?? null;
        $stage = null;

        if ($stageId) {
            $stage = PipelineStage::where('tenant_id', $tenantId)->find($stageId);
        }

        if (!$stage) {
            $stage = PipelineStage::where('tenant_id', $tenantId)->where('pipeline_id', $pipelineId)->orderBy('order_column')->first();
            $stageId = $stage?->id;
        }

        $probability = $validated['probability'] ?? $stage?->probability ?? 20;
        $val = (float) $validated['value'];
        $weightedValue = round(($val * $probability) / 100, 2);

        $deal = Deal::create([
            'tenant_id' => $tenantId,
            'company_id' => $validated['company_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'lead_id' => $validated['lead_id'] ?? null,
            'pipeline_id' => $pipelineId,
            'pipeline_stage_id' => $stageId,
            'name' => $validated['name'],
            'value' => $val,
            'currency' => $validated['currency'] ?? 'USD',
            'stage' => $stage?->code ?? ($validated['stage'] ?? 'qualification'),
            'probability' => $probability,
            'weighted_value' => $weightedValue,
            'stage_updated_at' => now(),
            'close_date' => $validated['close_date'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? auth()->id(),
            'collaborator_ids' => $validated['collaborator_ids'] ?? [],
            'source' => $validated['source'] ?? null,
            'campaign' => $validated['campaign'] ?? null,
            'tags' => $validated['tags'] ?? [],
        ]);

        return $this->successResponse($deal->load(['company', 'contact', 'lead', 'pipelineStage']), 'Deal created successfully.', 201);
    }

    public function show(Deal $deal): JsonResponse
    {
        $user = auth()->user();
        if ($user && !$this->rbacService->can($user, 'deals', 'view', $deal)) {
            abort(403, 'Unauthorized access: You do not have permission to view this deal.');
        }

        return $this->successResponse(
            $deal->load(['company', 'contact', 'lead', 'assignedUser', 'pipelineStage', 'pipeline', 'products', 'stageHistories', 'activities'])
        );
    }

    public function update(Request $request, Deal $deal): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'lead_id' => 'nullable|exists:leads,id',
            'value' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'probability' => 'nullable|integer|min:0|max:100',
            'close_date' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'collaborator_ids' => 'nullable|array',
            'lost_reason' => 'nullable|string',
            'competitor' => 'nullable|string|max:150',
            'tags' => 'nullable|array',
        ]);

        if (isset($validated['value']) || isset($validated['probability'])) {
            $val = isset($validated['value']) ? (float) $validated['value'] : (float) $deal->value;
            $prob = isset($validated['probability']) ? (int) $validated['probability'] : (int) $deal->probability;
            $validated['weighted_value'] = round(($val * $prob) / 100, 2);
        }

        $deal->update($validated);
        return $this->successResponse($deal->load(['company', 'contact', 'pipelineStage']), 'Deal updated successfully.');
    }

    /**
     * Move deal to target pipeline stage with validation.
     */
    public function moveToStage(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'pipeline_stage_id' => 'required|exists:pipeline_stages,id',
            'value' => 'nullable|numeric|min:0',
            'close_date' => 'nullable|date',
            'lost_reason' => 'nullable|string',
            'competitor' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $updatedDeal = $this->pipelineService->moveDealToStage(
            $deal,
            (int) $validated['pipeline_stage_id'],
            $validated,
            auth()->id()
        );

        return $this->successResponse($updatedDeal, 'Deal moved to stage successfully.');
    }

    /**
     * Add product/service line item to deal.
     */
    public function addProduct(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'name' => 'required|string|max:200',
            'quantity' => 'required|numeric|min:0.01',
            'unit_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        $qty = (float) $validated['quantity'];
        $price = (float) $validated['unit_price'];
        $discount = (float) ($validated['discount'] ?? 0);
        $total = ($qty * $price) - $discount;

        $dealProduct = DealProduct::create([
            'tenant_id' => $tenantId,
            'deal_id' => $deal->id,
            'product_id' => $validated['product_id'] ?? null,
            'name' => $validated['name'],
            'quantity' => $qty,
            'unit_price' => $price,
            'total_price' => max(0, $total),
            'discount' => $discount,
        ]);

        // Recalculate total deal value
        $newDealValue = $deal->products()->sum('total_price');
        if ($newDealValue > 0) {
            $deal->update([
                'value' => $newDealValue,
                'weighted_value' => round(($newDealValue * $deal->probability) / 100, 2),
            ]);
        }

        return $this->successResponse($dealProduct, 'Product added to deal.', 201);
    }

    /**
     * Remove product from deal.
     */
    public function removeProduct(int $id, int $productId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $product = DealProduct::where('tenant_id', $tenantId)->where('deal_id', $deal->id)->findOrFail($productId);
        $product->delete();

        // Recalculate total deal value
        $newDealValue = $deal->products()->sum('total_price');
        $deal->update([
            'value' => $newDealValue,
            'weighted_value' => round(($newDealValue * $deal->probability) / 100, 2),
        ]);

        return $this->successResponse(null, 'Product removed from deal.');
    }

    /**
     * Get weighted sales forecast report.
     */
    public function forecast(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $pipelineId = $request->filled('pipeline_id') ? (int) $request->pipeline_id : null;
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $userId = $request->filled('assigned_user_id') ? (int) $request->assigned_user_id : null;
        $timeframe = $request->get('timeframe', 'quarterly');

        $forecastingService = app(\App\Domains\CRM\Services\SalesForecastingService::class);
        $forecast = $forecastingService->getForecastReport($tenantId, $pipelineId, $branchId, $userId, $timeframe);
        return $this->successResponse($forecast);
    }

    /**
     * Transfer deal ownership with audit trail.
     */
    public function transferOwnership(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'new_assigned_user_id' => 'nullable|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
            'reason' => 'nullable|string',
        ]);

        $newUserId = (int) ($validated['new_assigned_user_id'] ?? $validated['user_id'] ?? 0);
        if (!$newUserId) {
            return $this->errorResponse('A valid user_id or new_assigned_user_id is required.', 422);
        }

        $updated = $this->pipelineService->transferOwnership(
            $deal,
            $newUserId,
            auth()->id() ?? 1,
            $validated['reason'] ?? null
        );

        return $this->successResponse($updated, 'Deal ownership transferred successfully.');
    }

    /**
     * Reopen a closed deal.
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'reopen_stage_id' => 'nullable|exists:pipeline_stages,id',
            'stage_id' => 'nullable|exists:pipeline_stages,id',
            'reason' => 'nullable|string',
        ]);

        $stageId = (int) ($validated['reopen_stage_id'] ?? $validated['stage_id'] ?? 0);
        if (!$stageId) {
            return $this->errorResponse('A valid reopen_stage_id or stage_id is required.', 422);
        }

        $reopened = $this->pipelineService->reopenDeal(
            $deal,
            $stageId,
            auth()->id() ?? 1,
            $validated['reason'] ?? null
        );

        return $this->successResponse($reopened, 'Deal reopened successfully.');
    }

    /**
     * Convert won deal into onboarding project.
     */
    public function convertToProject(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $project = $this->pipelineService->convertToProject(
            $deal,
            auth()->id() ?? 1,
            $validated
        );

        return $this->successResponse([
            'id' => $project->id,
            'deal' => $deal->fresh(),
            'project' => $project,
        ], 'Won deal converted to onboarding project successfully.');
    }

    /**
     * Identify stale deals in need of follow-up.
     */
    public function staleDeals(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $days = (int) $request->get('days', 14);

        $stale = $this->pipelineService->detectStaleDeals($tenantId, $days);
        return $this->successResponse($stale);
    }

    public function archive(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);
        $deal->update(['is_archived' => true]);
        return $this->successResponse($deal, 'Deal archived.');
    }

    public function unarchive(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $deal = Deal::where('tenant_id', $tenantId)->findOrFail($id);
        $deal->update(['is_archived' => false]);
        return $this->successResponse($deal, 'Deal unarchived.');
    }

    public function destroy(Deal $deal): JsonResponse
    {
        $user = auth()->user();
        if ($user && !$this->rbacService->can($user, 'deals', 'delete', $deal)) {
            abort(403, 'Unauthorized access: You do not have permission to delete this deal.');
        }

        $deal->delete();
        return $this->successResponse(null, 'Deal deleted successfully.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);
        Deal::whereIn('id', $request->ids)->delete();
        return $this->successResponse(null, 'Selected deals deleted successfully.');
    }
}
