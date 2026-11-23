<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Models\CustomAnalyticsAlert;
use App\Domains\Analytics\Services\AiBusinessInsightsService;
use App\Domains\Analytics\Services\CustomAlertService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiInsightsAlertsController extends Controller
{
    public function __construct(
        protected AiBusinessInsightsService $insightsService,
        protected CustomAlertService $alertService
    ) {}

    /**
     * Get Permission-Aware AI Business Insights
     */
    public function getAiInsights(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $filters = [
            'from_date' => $request->get('from_date'),
            'to_date' => $request->get('to_date'),
        ];

        $insights = $this->insightsService->generateInsights($tenantId, $filters);

        return response()->json([
            'success' => true,
            'data' => $insights,
        ]);
    }

    /**
     * List Custom Analytics Alerts
     */
    public function getAlerts(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $alerts = CustomAnalyticsAlert::where('tenant_id', $tenantId)->get();

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Create Custom Analytics Alert Rule
     */
    public function createAlert(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $userId = $request->user()->id;

        if ($request->has('channels') && !$request->has('notification_channels')) {
            $request->merge(['notification_channels' => $request->input('channels')]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'metric_type' => 'required|string|in:low_lead_conversion,overdue_payments,missed_followups,high_ticket_backlog,low_sales,high_expenses,branch_target_shortfall,subscription_usage_limit',
            'condition_operator' => 'required|string|in:>,<,>=,<=,==',
            'threshold_value' => 'required|numeric',
            'notification_channels' => 'required|array',
            'branch_id' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $alert = CustomAnalyticsAlert::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'name' => $validated['name'],
            'metric_type' => $validated['metric_type'],
            'condition_operator' => $validated['condition_operator'],
            'threshold_value' => $validated['threshold_value'],
            'notification_channels' => $validated['notification_channels'],
            'branch_id' => $validated['branch_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $alert,
            'message' => 'Custom analytics alert created successfully.',
        ], 201);
    }

    /**
     * Update Custom Alert Rule
     */
    public function updateAlert(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $alert = CustomAnalyticsAlert::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'metric_type' => 'sometimes|string',
            'condition_operator' => 'sometimes|string|in:>,<,>=,<=,==',
            'threshold_value' => 'sometimes|numeric',
            'notification_channels' => 'sometimes|array',
            'branch_id' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $alert->update($validated);

        return response()->json([
            'success' => true,
            'data' => $alert,
            'message' => 'Alert rule updated.',
        ]);
    }

    /**
     * Delete Alert Rule
     */
    public function deleteAlert(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $alert = CustomAnalyticsAlert::where('tenant_id', $tenantId)->findOrFail($id);
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert rule deleted.',
        ]);
    }

    /**
     * Evaluate Alerts Immediately
     */
    public function evaluateAlerts(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $result = $this->alertService->evaluateAlerts($tenantId);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
