<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\SalesQuota;
use App\Domains\CRM\Models\SalesTeam;
use App\Domains\CRM\Services\SalesTeamService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesTeamController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected SalesTeamService $salesTeamService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $teams = SalesTeam::where('tenant_id', $tenantId)
            ->with(['leader', 'quotas'])
            ->get()
            ->map(function ($team) {
                return array_merge($team->toArray(), [
                    'members' => $team->getMembers(),
                ]);
            });

        return $this->successResponse($teams);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'leader_id' => 'nullable|exists:users,id',
            'member_ids' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        $team = SalesTeam::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'leader_id' => $validated['leader_id'] ?? null,
            'member_ids' => $validated['member_ids'] ?? [],
            'description' => $validated['description'] ?? null,
        ]);

        return $this->successResponse($team->load('leader'), 'Sales team created successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $team = SalesTeam::where('tenant_id', $tenantId)->with('leader')->findOrFail($id);

        return $this->successResponse(array_merge($team->toArray(), [
            'members' => $team->getMembers(),
        ]));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $team = SalesTeam::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'leader_id' => 'nullable|exists:users,id',
            'member_ids' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        $team->update($validated);
        return $this->successResponse($team->load('leader'), 'Sales team updated.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $team = SalesTeam::where('tenant_id', $tenantId)->findOrFail($id);
        $team->delete();

        return $this->successResponse(null, 'Sales team deleted.');
    }

    /**
     * Get quotas and revenue targets for workspace.
     */
    public function quotas(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $quotas = SalesQuota::where('tenant_id', $tenantId)->with(['user', 'salesTeam'])->get();
        return $this->successResponse($quotas);
    }

    /**
     * Set / update sales quota for user or team.
     */
    public function saveQuota(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'sales_team_id' => 'nullable|exists:sales_teams,id',
            'period' => 'required|in:monthly,quarterly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'target_revenue' => 'required|numeric|min:0',
            'target_deals_count' => 'nullable|integer|min:0',
            'target_leads_count' => 'nullable|integer|min:0',
        ]);

        $quota = SalesQuota::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $validated['user_id'] ?? null,
                'sales_team_id' => $validated['sales_team_id'] ?? null,
                'start_date' => $validated['start_date'],
            ],
            [
                'period' => $validated['period'],
                'end_date' => $validated['end_date'],
                'target_revenue' => $validated['target_revenue'],
                'target_deals_count' => $validated['target_deals_count'] ?? 0,
                'target_leads_count' => $validated['target_leads_count'] ?? 0,
            ]
        );

        return $this->successResponse($quota->load(['user', 'salesTeam']), 'Sales quota target saved.');
    }

    /**
     * Sales performance leaderboard & quota attainment.
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $period = $request->get('period', 'monthly');
        $teamId = $request->filled('team_id') ? (int) $request->team_id : null;

        $data = $this->salesTeamService->getLeaderboard($tenantId, $period, $teamId);
        return $this->successResponse($data);
    }
}
