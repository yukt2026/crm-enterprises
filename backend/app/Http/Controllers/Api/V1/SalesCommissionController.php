<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Sales\Models\CommissionPlan;
use App\Domains\Sales\Models\SalesCommission;
use App\Domains\Sales\Services\SalesCommissionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesCommissionController extends Controller
{
    public function __construct(
        protected SalesCommissionService $commissionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $query = SalesCommission::where('tenant_id', $tenantId)
            ->with(['user:id,name,email', 'deal:id,name', 'commissionPlan:id,name', 'approvedByUser:id,name']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('calculation_basis')) {
            $query->where('calculation_basis', $request->calculation_basis);
        }

        $commissions = $query->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data' => $commissions,
        ]);
    }

    public function plans(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $plans = CommissionPlan::where('tenant_id', $tenantId)->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'plan_type' => 'required|in:fixed,percentage,tiered',
            'calculation_basis' => 'required|in:deal_won,payment_collected',
            'rate_percentage' => 'nullable|numeric|min:0',
            'fixed_amount' => 'nullable|numeric|min:0',
            'tier_rules' => 'nullable|array',
            'target_quota' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $plan = CommissionPlan::create(array_merge($validated, [
            'tenant_id' => $tenantId,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Sales commission plan created.',
            'data' => $plan,
        ], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $commission = SalesCommission::where('tenant_id', $tenantId)->findOrFail($id);

        $updated = $this->commissionService->approveCommission($commission, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Commission approved.',
            'data' => $updated->load(['approvedByUser']),
        ]);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $commission = SalesCommission::where('tenant_id', $tenantId)->findOrFail($id);

        $payoutDate = $request->input('payout_date');
        $updated = $this->commissionService->markAsPaid($commission, $payoutDate);

        return response()->json([
            'success' => true,
            'message' => 'Commission marked as paid.',
            'data' => $updated,
        ]);
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $commission = SalesCommission::where('tenant_id', $tenantId)->findOrFail($id);

        $reason = $request->validate(['reason' => 'required|string|min:3'])['reason'];
        $updated = $this->commissionService->reverseCommission($commission, $reason);

        return response()->json([
            'success' => true,
            'message' => 'Commission clawback / reversal recorded.',
            'data' => $updated,
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $report = $this->commissionService->getCommissionReport($tenantId, $userId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
