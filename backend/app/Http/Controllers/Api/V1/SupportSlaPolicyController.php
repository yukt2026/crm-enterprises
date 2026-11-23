<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Support\Models\SupportSlaPolicy;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportSlaPolicyController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $policies = SupportSlaPolicy::where('tenant_id', $tenantId)
            ->with('escalateToUser')
            ->withCount('tickets')
            ->get();

        return $this->successResponse($policies);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:all,urgent,high,medium,low',
            'response_target_minutes' => 'required|integer|min:5',
            'resolution_target_minutes' => 'required|integer|min:15',
            'business_hours_only' => 'nullable|boolean',
            'escalation_threshold_percent' => 'nullable|integer|between:50,100',
            'escalate_to_user_id' => 'nullable|exists:users,id',
            'is_default' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            SupportSlaPolicy::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $policy = SupportSlaPolicy::create(array_merge($validated, ['tenant_id' => $tenantId]));

        return $this->successResponse($policy->load('escalateToUser'), 'SLA policy created successfully.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $policy = SupportSlaPolicy::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'priority' => 'sometimes|string|in:all,urgent,high,medium,low',
            'response_target_minutes' => 'sometimes|integer|min:5',
            'resolution_target_minutes' => 'sometimes|integer|min:15',
            'business_hours_only' => 'sometimes|boolean',
            'escalation_threshold_percent' => 'sometimes|integer|between:50,100',
            'escalate_to_user_id' => 'nullable|exists:users,id',
            'is_default' => 'sometimes|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            SupportSlaPolicy::where('tenant_id', $tenantId)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $policy->update($validated);

        return $this->successResponse($policy->load('escalateToUser'), 'SLA policy updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $policy = SupportSlaPolicy::where('tenant_id', $tenantId)->findOrFail($id);
        $policy->delete();

        return $this->successResponse(null, 'SLA policy deleted successfully.');
    }
}
