<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Support\Models\CustomerFeedback;
use App\Domains\Support\Models\WarrantyContract;
use App\Domains\Support\Services\CustomerSuccessService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSuccessController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected CustomerSuccessService $customerSuccessService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List Customer Health Scores with explainable factor breakdown.
     */
    public function getHealthScores(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $scores = $this->customerSuccessService->getHealthScores(
            $tenantId,
            $request->input('status'),
            $request->input('churn_risk')
        );

        return response()->json([
            'status' => 'success',
            'data' => $scores,
        ]);
    }

    /**
     * Get or calculate explainable Health Score for a specific customer.
     */
    public function getCustomerHealthScore(int $customerId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $score = $this->customerSuccessService->calculateCustomerHealthScore($tenantId, $customerId);

        return response()->json([
            'status' => 'success',
            'data' => $score->load(['customer', 'successOwner']),
        ]);
    }

    /**
     * Record a customer success check-in meeting or review task.
     */
    public function recordCheckIn(Request $request, int $customerId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $data = $request->validate([
            'notes' => 'required|string',
            'next_check_in_date' => 'nullable|date',
            'onboarding_status' => 'nullable|string|in:not_started,in_progress,completed,stalled',
        ]);

        $score = $this->customerSuccessService->recordCheckIn($tenantId, $customerId, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Customer check-in logged and health score recalculated.',
            'data' => $score->load(['customer', 'successOwner']),
        ]);
    }

    /**
     * List Warranty & AMC Contracts.
     */
    public function getContracts(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = WarrantyContract::where('tenant_id', $tenantId)->with(['customer', 'assignedEngineer']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('contract_type')) {
            $query->where('contract_type', $request->input('contract_type'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('end_date', 'asc')->get(),
        ]);
    }

    /**
     * Create Warranty or Annual Maintenance Contract (AMC).
     */
    public function createContract(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'contract_type' => 'nullable|in:warranty,amc,service_agreement',
            'contract_number' => 'nullable|string|max:50',
            'product_name' => 'nullable|string|max:200',
            'serial_number' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'coverage_details' => 'nullable|string',
            'renewal_price' => 'nullable|numeric|min:0',
            'assigned_engineer_id' => 'nullable|integer',
            'preventive_maintenance_interval_days' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $contract = $this->customerSuccessService->createWarrantyContract($tenantId, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Warranty/AMC contract created successfully.',
            'data' => $contract->load(['customer', 'assignedEngineer']),
        ], 201);
    }

    /**
     * Record preventive maintenance execution.
     */
    public function recordMaintenance(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $contract = $this->customerSuccessService->recordMaintenance($tenantId, $id, $data, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Preventive maintenance recorded successfully.',
            'data' => $contract,
        ]);
    }

    /**
     * List CSAT / NPS feedback responses.
     */
    public function getFeedback(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = CustomerFeedback::where('tenant_id', $tenantId)->with(['ticket', 'customer']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('submitted_at', 'desc')->get(),
        ]);
    }

    /**
     * Submit CSAT & NPS feedback response.
     */
    public function submitFeedback(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'ticket_id' => 'nullable|integer',
            'csat_score' => 'required|integer|between:1,5',
            'nps_score' => 'nullable|integer|between:0,10',
            'rating_category' => 'nullable|string',
            'feedback_text' => 'nullable|string',
            'channel' => 'nullable|string',
        ]);

        $data['tenant_id'] = $tenantId;
        $data['submitted_at'] = now();
        $feedback = CustomerFeedback::create($data);

        // Recalculate health score with fresh feedback
        $this->customerSuccessService->calculateCustomerHealthScore($tenantId, $data['customer_id']);

        return response()->json([
            'status' => 'success',
            'message' => 'Customer feedback recorded.',
            'data' => $feedback,
        ], 201);
    }
}
