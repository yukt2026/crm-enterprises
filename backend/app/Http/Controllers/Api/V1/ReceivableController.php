<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Services\ReceivableService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceivableController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected ReceivableService $receivableService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * Get Accounts Receivable (AR) Ageing Report (0-30, 31-45, 46-90, 90+ days).
     */
    public function getAgeingReport(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $customerId = $request->query('customer_id') ? (int) $request->query('customer_id') : null;

        $report = $this->receivableService->getAgeingReport($tenantId, $customerId);

        return $this->successResponse($report, 'Accounts Receivable ageing report retrieved.');
    }

    /**
     * Update promise-to-pay date and commitments.
     */
    public function updatePromiseToPay(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'promise_to_pay_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $invoice = $this->receivableService->updatePromiseToPay(
            $tenantId,
            $id,
            $validated['promise_to_pay_date'],
            $validated['notes'] ?? null
        );

        return $this->successResponse($invoice, 'Promise-to-pay commitment recorded.');
    }

    /**
     * Flag invoice dispute status.
     */
    public function setDispute(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'is_disputed' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
        ]);

        $invoice = $this->receivableService->setDisputeStatus(
            $tenantId,
            $id,
            $validated['is_disputed'],
            $validated['reason'] ?? null,
            $validated['status'] ?? null
        );

        return $this->successResponse($invoice, 'Invoice dispute status updated.');
    }

    /**
     * Assign collection officer.
     */
    public function assignCollectionAgent(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'recovery_status' => 'nullable|string|max:50',
        ]);

        $invoice = $this->receivableService->assignCollectionAgent(
            $tenantId,
            $id,
            $validated['user_id'] ?? null,
            $validated['recovery_status'] ?? 'follow_up_needed'
        );

        return $this->successResponse($invoice, 'Collection officer assigned.');
    }
}
