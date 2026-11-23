<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Models\TenantCostProtectionRule;
use App\Domains\Billing\Services\CreditLedgerService;
use App\Domains\Billing\Services\UsageMeteringService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageMeteringController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected UsageMeteringService $meteringService,
        protected CreditLedgerService $ledgerService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * GET /api/v1/crm/billing/usage
     * Comprehensive usage metering dashboard
     */
    public function dashboard(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $data = $this->meteringService->getUsageDashboard($tenantId);

        return $this->successResponse($data, 'Usage dashboard data retrieved');
    }

    /**
     * POST /api/v1/crm/billing/usage/reserve
     * Pre-action usage reservation hold
     */
    public function reserve(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'meter_type' => 'required|string',
            'estimated_units' => 'required|numeric|min:0.001',
        ]);

        try {
            $reservation = $this->meteringService->reserveUsage(
                $tenantId,
                $validated['meter_type'],
                (float) $validated['estimated_units']
            );

            return $this->successResponse($reservation, 'Usage reservation authorized and held', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 402); // 402 Payment Required / Insufficient Credits
        }
    }

    /**
     * POST /api/v1/crm/billing/usage/settle
     * Post-action actual usage settlement & provider cost reconciliation
     */
    public function settle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|string',
            'actual_units' => 'required|numeric|min:0',
            'provider_cost' => 'required|numeric|min:0',
            'metadata' => 'nullable|array',
        ]);

        try {
            $result = $this->meteringService->settleUsage(
                $validated['reservation_id'],
                (float) $validated['actual_units'],
                (float) $validated['provider_cost'],
                $validated['metadata'] ?? []
            );

            return $this->successResponse($result, 'Usage successfully settled and logged');
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/crm/billing/usage/release
     * Release unused reservation hold
     */
    public function release(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $this->meteringService->releaseReservation(
            $validated['reservation_id'],
            $validated['reason'] ?? 'cancelled'
        );

        return $this->successResponse(null, 'Reservation hold released successfully');
    }

    /**
     * GET /api/v1/crm/billing/wallet
     */
    public function wallet(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $wallet = $this->ledgerService->getOrCreateWallet($tenantId);

        return $this->successResponse([
            'balance' => (float) $wallet->balance,
            'reserved_balance' => (float) $wallet->reserved_balance,
            'available_balance' => $wallet->available_balance,
            'currency' => $wallet->currency,
            'last_recharged_at' => $wallet->last_recharged_at,
        ], 'Credit wallet retrieved');
    }

    /**
     * POST /api/v1/crm/billing/wallet/recharge
     */
    public function recharge(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:5|max:10000',
            'reference_id' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $entry = $this->ledgerService->rechargeCredits(
            $tenantId,
            (float) $validated['amount'],
            $validated['reference_id'] ?? ('sim_' . bin2hex(random_bytes(6))),
            $validated['description'] ?? 'Direct Credit Wallet Recharge'
        );

        return $this->successResponse($entry, 'Wallet recharged successfully', 201);
    }

    /**
     * GET /api/v1/crm/billing/wallet/ledger
     */
    public function ledger(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $history = $this->ledgerService->getLedgerHistory($tenantId, 50);

        return $this->successResponse($history, 'Immutable credit ledger entries retrieved');
    }

    /**
     * GET /api/v1/crm/billing/wallet/verify-integrity
     */
    public function verifyIntegrity(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $report = $this->ledgerService->verifyLedgerIntegrity($tenantId);

        return $this->successResponse($report, 'Credit ledger integrity verified');
    }

    /**
     * GET /api/v1/crm/billing/cost-protection
     */
    public function costProtection(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $rules = TenantCostProtectionRule::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'daily_spending_cap' => 100.00,
                'monthly_spending_cap' => 1000.00,
                'ai_provider_spending_cap' => 500.00,
                'auto_pause_on_breach' => true,
                'low_balance_alert_threshold' => 20.00,
                'is_ai_calling_unlimited_allowed' => false,
                'is_paused' => false,
            ]
        );

        return $this->successResponse($rules, 'Cost protection settings retrieved');
    }

    /**
     * PUT /api/v1/crm/billing/cost-protection
     */
    public function updateCostProtection(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'daily_spending_cap' => 'nullable|numeric|min:10',
            'monthly_spending_cap' => 'nullable|numeric|min:50',
            'ai_provider_spending_cap' => 'nullable|numeric|min:10',
            'auto_pause_on_breach' => 'nullable|boolean',
            'low_balance_alert_threshold' => 'nullable|numeric|min:5',
            'is_ai_calling_unlimited_allowed' => 'nullable|boolean',
            'is_paused' => 'nullable|boolean',
        ]);

        $rules = TenantCostProtectionRule::updateOrCreate(
            ['tenant_id' => $tenantId],
            $validated
        );

        return $this->successResponse($rules, 'Cost protection settings updated');
    }
}
