<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\WhatsApp\Models\WhatsAppAiExecutionLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppAiLogsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = WhatsAppAiExecutionLog::where('tenant_id', $tenantId)
            ->with(['agent:id,name,primary_language', 'conversation:id,phone_number,customer_name']);

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->input('agent_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 15)));
        $logs = $query->orderBy('id', 'desc')->paginate($perPage);

        // Aggregate statistics for usage dashboard
        $stats = [
            'total_executions' => WhatsAppAiExecutionLog::where('tenant_id', $tenantId)->count(),
            'total_tokens' => (int) WhatsAppAiExecutionLog::where('tenant_id', $tenantId)->sum('total_tokens'),
            'total_cost_usd' => (float) WhatsAppAiExecutionLog::where('tenant_id', $tenantId)->sum('estimated_cost_usd'),
            'avg_latency_ms' => (int) WhatsAppAiExecutionLog::where('tenant_id', $tenantId)->avg('latency_ms'),
            'handovers_triggered' => WhatsAppAiExecutionLog::where('tenant_id', $tenantId)->where('status', 'handover_triggered')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
