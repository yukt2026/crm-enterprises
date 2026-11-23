<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Workflow\Models\Workflow;
use App\Domains\Workflow\Models\WorkflowApproval;
use App\Domains\Workflow\Models\WorkflowAutomationLimit;
use App\Domains\Workflow\Models\WorkflowRun;
use App\Domains\Workflow\Services\TriggerRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowLimitController extends Controller
{
    /**
     * Return tenant automation limits and current usage counters.
     */
    public function getLimits(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $limits = WorkflowAutomationLimit::getForTenant($tenantId);

        $activeWorkflowsCount = Workflow::where('tenant_id', $tenantId)->where('is_active', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'limits' => $limits,
                'active_workflows_count' => $activeWorkflowsCount,
                'monthly_usage_percentage' => $limits->max_monthly_executions > 0
                    ? round(($limits->current_month_executions / $limits->max_monthly_executions) * 100, 1)
                    : 0,
            ],
        ]);
    }

    /**
     * Update tenant automation limits (Admin only).
     */
    public function updateLimits(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $limits = WorkflowAutomationLimit::getForTenant($tenantId);

        $validated = $request->validate([
            'max_active_workflows' => 'sometimes|integer|min:1',
            'max_monthly_executions' => 'sometimes|integer|min:100',
            'max_external_api_calls' => 'sometimes|integer|min:0',
            'max_whatsapp_actions' => 'sometimes|integer|min:0',
            'max_email_actions' => 'sometimes|integer|min:0',
            'max_sms_actions' => 'sometimes|integer|min:0',
            'max_ai_actions' => 'sometimes|integer|min:0',
            'max_delayed_tasks' => 'sometimes|integer|min:0',
        ]);

        $limits->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Workflow automation limits updated successfully.',
            'data' => $limits,
        ]);
    }

    /**
     * Comprehensive workflow monitoring & execution metrics.
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $totalWorkflows = Workflow::where('tenant_id', $tenantId)->count();
        $activeWorkflows = Workflow::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $pausedWorkflows = Workflow::where('tenant_id', $tenantId)->where('status', 'paused')->count();

        $runsQuery = WorkflowRun::where('tenant_id', $tenantId);
        $totalRuns = (clone $runsQuery)->count();
        $completedRuns = (clone $runsQuery)->where('status', 'completed')->count();
        $failedRuns = (clone $runsQuery)->where('status', 'failed')->count();
        $waitingRuns = (clone $runsQuery)->whereIn('status', ['waiting', 'waiting_approval'])->count();

        $avgDurationMs = (clone $runsQuery)->where('status', 'completed')->avg('execution_duration_ms') ?? 0;
        $pendingApprovals = WorkflowApproval::where('tenant_id', $tenantId)->where('status', 'pending')->count();

        $limits = WorkflowAutomationLimit::getForTenant($tenantId);

        return response()->json([
            'success' => true,
            'data' => [
                'total_workflows' => $totalWorkflows,
                'active_workflows' => $activeWorkflows,
                'paused_workflows' => $pausedWorkflows,
                'total_runs' => $totalRuns,
                'completed_runs' => $completedRuns,
                'failed_runs' => $failedRuns,
                'waiting_runs' => $waitingRuns,
                'pending_approvals' => $pendingApprovals,
                'success_rate' => $totalRuns > 0 ? round(($completedRuns / $totalRuns) * 100, 1) : 100.0,
                'average_duration_ms' => (int) round($avgDurationMs),
                'monthly_executions' => $limits->current_month_executions,
                'monthly_limit' => $limits->max_monthly_executions,
            ],
        ]);
    }

    /**
     * Return catalog of all supported triggers across all 9 CRM modules.
     */
    public function getTriggersCatalog(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => TriggerRegistry::TRIGGERS,
        ]);
    }
}
