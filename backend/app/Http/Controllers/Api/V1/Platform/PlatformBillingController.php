<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\Billing\Models\SubscriptionInvoice;
use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\Billing\Models\TenantSubscription;
use App\Domains\Billing\Services\CreditLedgerService;
use App\Domains\Billing\Services\SubscriptionBillingService;
use App\Domains\PlatformAdmin\Services\PlatformAdminService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformBillingController extends Controller
{
    public function __construct(
        protected SubscriptionBillingService $billingService,
        protected CreditLedgerService $creditLedgerService,
        protected PlatformAdminService $adminService
    ) {}

    /**
     * GET /api/v1/platform/billing/plans
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::orderBy('price', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $plans,
            'message' => 'Subscription plans retrieved',
        ], 200);
    }

    /**
     * POST /api/v1/platform/billing/plans
     */
    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:subscription_plans,code',
            'description' => 'nullable|string',
            'billing_cycle' => 'required|string|in:monthly,annual,custom',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'trial_days' => 'nullable|integer|min:0',
            'grace_period_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'allowances' => 'nullable|array',
            'overage_rates' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $plan = SubscriptionPlan::create($validated);

        $this->adminService->logAudit(
            $request->user(),
            'plan.create',
            'SubscriptionPlan',
            (string) $plan->id,
            ['plan_code' => $plan->code, 'price' => $plan->price],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Commercial subscription plan created successfully',
        ], 201);
    }

    /**
     * PUT /api/v1/platform/billing/plans/{id}
     */
    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'allowances' => 'nullable|array',
            'overage_rates' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $plan->update($validated);

        $this->adminService->logAudit(
            $request->user(),
            'plan.update',
            'SubscriptionPlan',
            (string) $plan->id,
            $validated,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Subscription plan updated successfully',
        ], 200);
    }

    /**
     * GET /api/v1/platform/billing/subscriptions
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $query = TenantSubscription::withoutGlobalScopes()
            ->with(['tenant:id,name,slug', 'plan'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $subs = $query->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $subs->items(),
            'meta' => [
                'current_page' => $subs->currentPage(),
                'last_page' => $subs->lastPage(),
                'total' => $subs->total(),
            ],
            'message' => 'Platform subscriptions retrieved',
        ], 200);
    }

    /**
     * GET /api/v1/platform/billing/invoices
     */
    public function invoices(Request $request): JsonResponse
    {
        $status = $request->input('status');
        $query = SubscriptionInvoice::withoutGlobalScopes()
            ->with('tenant:id,name,slug')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $invoices = $query->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
            'message' => 'Platform invoices retrieved',
        ], 200);
    }

    /**
     * POST /api/v1/platform/billing/adjust-credits
     * Controlled manual credit adjustment by authorized platform staff
     */
    public function adjustCredits(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
            'amount' => 'required|numeric',
            'reason' => 'required|string|max:500',
        ]);

        $tenantId = (int) $validated['tenant_id'];
        $amount = (float) $validated['amount'];
        $reason = $validated['reason'];

        $entry = $this->creditLedgerService->adjustCredits(
            $tenantId,
            $amount,
            $reason,
            [
                'admin_id' => $request->user()->id,
                'admin_email' => $request->user()->email,
            ]
        );

        $this->adminService->logAudit(
            $request->user(),
            'billing.credit_adjustment',
            'Tenant',
            (string) $tenantId,
            [
                'amount' => $amount,
                'reason' => $reason,
                'ledger_entry_id' => $entry->entry_id,
            ],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $entry,
            'message' => 'Tenant credit ledger adjusted successfully',
        ], 200);
    }
}
