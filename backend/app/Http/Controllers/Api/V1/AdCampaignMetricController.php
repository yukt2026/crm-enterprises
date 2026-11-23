<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LeadAcquisition\Models\AdCampaignMetric;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdCampaignMetricController extends Controller
{
    public function __construct(protected TenantContext $tenantContext) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = $this->getTenantId();

        $query = AdCampaignMetric::where('workspace_id', $workspaceId)
            ->with('source')
            ->orderByDesc('date')
            ->orderByDesc('spend');

        if ($request->has('provider') && $request->provider !== '') {
            $query->where('provider', $request->provider);
        }

        $metrics = $query->paginate($request->query('per_page', 20));

        $aggregates = AdCampaignMetric::where('workspace_id', $workspaceId)
            ->selectRaw('sum(spend) as total_spend, sum(impressions) as total_impressions, sum(clicks) as total_clicks, sum(leads_count) as total_leads')
            ->first();

        $totalSpend = (float) ($aggregates->total_spend ?? 0);
        $totalLeads = (int) ($aggregates->total_leads ?? 0);
        $cpl = $totalLeads > 0 ? round($totalSpend / $totalLeads, 2) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'metrics' => $metrics,
                'summary' => [
                    'total_spend' => $totalSpend,
                    'total_impressions' => (int) ($aggregates->total_impressions ?? 0),
                    'total_clicks' => (int) ($aggregates->total_clicks ?? 0),
                    'total_leads' => $totalLeads,
                    'cost_per_lead' => $cpl,
                ],
            ],
        ]);
    }
}
