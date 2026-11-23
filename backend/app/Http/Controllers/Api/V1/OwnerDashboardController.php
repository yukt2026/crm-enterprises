<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Models\DashboardWidgetLayout;
use App\Domains\Analytics\Services\OwnerExecutiveDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function __construct(
        protected OwnerExecutiveDashboardService $dashboardService
    ) {}

    /**
     * Get Executive & Owner Business Intelligence Dashboard Data
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $filters = [
            'from_date' => $request->get('from_date'),
            'to_date' => $request->get('to_date'),
            'branch_id' => $request->get('branch_id'),
            'employee_id' => $request->get('employee_id'),
            'department_id' => $request->get('department_id'),
        ];

        $metrics = $this->dashboardService->getDashboardMetrics($tenantId, $filters);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * Get Saved Dashboard Layouts for User / Role
     */
    public function getLayouts(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $userId = $request->user()->id;

        $layouts = DashboardWidgetLayout::where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderBy('is_default', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $layouts,
        ]);
    }

    /**
     * Save / Create Dashboard Widget Layout
     */
    public function saveLayout(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $userId = $request->user()->id;

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'role' => 'nullable|string|max:50',
            'is_default' => 'boolean',
            'widgets' => 'required|array',
            'filters' => 'nullable|array',
        ]);

        if (!empty($validated['is_default'])) {
            DashboardWidgetLayout::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->update(['is_default' => false]);
        }

        $layout = DashboardWidgetLayout::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'name' => $validated['name'],
            'role' => $validated['role'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'widgets' => $validated['widgets'],
            'filters' => $validated['filters'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $layout,
            'message' => 'Dashboard widget layout saved successfully.',
        ], 201);
    }

    /**
     * Update Dashboard Layout
     */
    public function updateLayout(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $layout = DashboardWidgetLayout::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'widgets' => 'sometimes|array',
            'filters' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
        ]);

        if (!empty($validated['is_default'])) {
            DashboardWidgetLayout::where('tenant_id', $tenantId)
                ->where('user_id', $request->user()->id)
                ->update(['is_default' => false]);
        }

        $layout->update($validated);

        return response()->json([
            'success' => true,
            'data' => $layout,
            'message' => 'Dashboard layout updated.',
        ]);
    }

    /**
     * Delete Dashboard Layout
     */
    public function deleteLayout(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $layout = DashboardWidgetLayout::where('tenant_id', $tenantId)
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $layout->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard layout deleted.',
        ]);
    }
}
