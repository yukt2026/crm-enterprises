<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LeadAcquisition\Models\LeadIngestionEvent;
use App\Domains\LeadAcquisition\Services\LeadIngestionPipeline;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadIngestionEventController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected LeadIngestionPipeline $pipeline
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = $this->getTenantId();

        $query = LeadIngestionEvent::where('workspace_id', $workspaceId)
            ->with(['source', 'lead'])
            ->orderByDesc('id');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('provider') && $request->provider !== '') {
            $query->where('provider', $request->provider);
        }

        $events = $query->paginate($request->query('per_page', 25));

        return response()->json([
            'status' => 'success',
            'data' => $events,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $workspaceId = $this->getTenantId();
        $event = LeadIngestionEvent::where('workspace_id', $workspaceId)
            ->with(['source', 'lead'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $event,
        ]);
    }

    public function retry(int $id): JsonResponse
    {
        $workspaceId = $this->getTenantId();
        $event = LeadIngestionEvent::where('workspace_id', $workspaceId)->findOrFail($id);

        $result = $this->pipeline->retry($event);

        return response()->json([
            'status' => $result['success'] ? 'success' : 'error',
            'message' => $result['success'] ? 'Event reprocessed successfully.' : ($result['error'] ?? 'Retry failed.'),
            'data' => $result,
        ]);
    }
}
