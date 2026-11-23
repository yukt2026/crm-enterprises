<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Billing\Services\SubscriptionBillingService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionBillingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected SubscriptionBillingService $billingService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * GET /api/v1/crm/billing/plans
     */
    public function plans(): JsonResponse
    {
        $plans = $this->billingService->getPlans();
        return $this->successResponse($plans, 'Subscription plans retrieved successfully');
    }

    /**
     * GET /api/v1/crm/billing/subscription
     */
    public function subscription(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $sub = $this->billingService->getTenantSubscription($tenantId);

        return $this->successResponse($sub, 'Tenant subscription details retrieved');
    }

    /**
     * POST /api/v1/crm/billing/subscribe
     */
    public function subscribe(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'plan_id' => 'required|integer|exists:subscription_plans,id',
            'prorate' => 'nullable|boolean',
            'billing_contact_name' => 'nullable|string|max:255',
            'billing_email' => 'nullable|email|max:255',
            'billing_address' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:64',
        ]);

        $result = $this->billingService->changePlan(
            $tenantId,
            (int) $validated['plan_id'],
            $validated['prorate'] ?? true
        );

        return $this->successResponse($result, 'Subscription plan updated successfully', 200);
    }

    /**
     * POST /api/v1/crm/billing/cancel
     */
    public function cancel(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $reason = $request->input('reason', 'Customer cancellation');

        $sub = $this->billingService->cancelSubscription($tenantId, $reason);

        return $this->successResponse($sub, 'Subscription auto-renewal cancelled successfully');
    }

    /**
     * PUT /api/v1/crm/billing/contact
     */
    public function updateBillingContact(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'billing_contact_name' => 'nullable|string|max:255',
            'billing_email' => 'nullable|email|max:255',
            'billing_address' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:64',
        ]);

        $sub = $this->billingService->updateBillingDetails($tenantId, $validated);

        return $this->successResponse($sub, 'Billing contact details updated');
    }

    /**
     * GET /api/v1/crm/billing/invoices
     */
    public function invoices(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $invoices = $this->billingService->getInvoices($tenantId);

        return $this->successResponse($invoices, 'Subscription invoices retrieved successfully');
    }
}
