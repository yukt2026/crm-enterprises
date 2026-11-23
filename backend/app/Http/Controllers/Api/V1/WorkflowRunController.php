<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Workflow\Models\Workflow;
use App\Domains\Workflow\Models\WorkflowRun;
use App\Domains\Workflow\Services\WorkflowExecutionEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowRunController extends Controller
{
    public function __construct(
        protected WorkflowExecutionEngine $engine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $query = WorkflowRun::where('tenant_id', $tenantId)
            ->with(['workflow:id,name,category,status', 'version:id,version'])
            ->latest();

        if ($request->filled('workflow_id')) {
            $query->where('workflow_id', (int)$request->workflow_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('trigger_event')) {
            $query->where('trigger_event', $request->trigger_event);
        }

        $runs = $query->paginate((int)$request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $runs->items(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $run = WorkflowRun::where('tenant_id', $tenantId)
            ->with(['workflow', 'version', 'stepLogs'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $run,
        ]);
    }

    public function retry(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $run = WorkflowRun::where('tenant_id', $tenantId)->findOrFail($id);

        if ($run->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Only failed workflow executions can be retried.',
            ], 400);
        }

        $run->update([
            'status' => 'running',
            'retry_count' => $run->retry_count + 1,
            'error_message' => null,
            'started_at' => now(),
        ]);

        $version = $run->version;
        $result = $this->engine->executeGraph($run, $version->definition);

        return response()->json([
            'success' => true,
            'data' => $run->fresh(['stepLogs']),
            'execution_result' => $result,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $run = WorkflowRun::where('tenant_id', $tenantId)->findOrFail($id);

        if (in_array($run->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => "Cannot cancel workflow run in state [{$run->status}].",
            ], 400);
        }

        $run->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $run,
            'message' => 'Workflow run cancelled.',
        ]);
    }

    public function triggerManual(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'trigger_type' => 'required|string',
            'payload' => 'required|array',
            'idempotency_key' => 'nullable|string',
        ]);

        $result = $this->engine->trigger(
            $tenantId,
            $validated['trigger_type'],
            $validated['payload'],
            $validated['idempotency_key'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
