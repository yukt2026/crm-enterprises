<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeePerformanceReview;
use App\Domains\HR\Models\EmployeeProductivityGoal;
use App\Domains\HR\Services\EmployeeProductivityService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeProductivityController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected EmployeeProductivityService $productivityService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * Get holistic employee productivity dashboard.
     */
    public function getProductivity(int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $metrics = $this->productivityService->getProductivityMetrics($tenantId, $employeeId);

        return response()->json([
            'status' => 'success',
            'data' => $metrics,
        ]);
    }

    /**
     * List performance reviews for employee.
     */
    public function getReviews(int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $reviews = EmployeePerformanceReview::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->with('reviewer:id,name')
            ->orderBy('review_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $reviews,
        ]);
    }

    /**
     * Create performance review.
     */
    public function storeReview(Request $request, int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();

        if (!$request->has('overall_rating')) {
            $subRatings = array_filter([
                $request->input('rating_productivity'),
                $request->input('rating_quality'),
                $request->input('rating_teamwork'),
            ], fn($v) => $v !== null && $v !== '');

            $overall = $request->input('rating_overall')
                ?? (!empty($subRatings) ? round(array_sum($subRatings) / count($subRatings), 2) : 4.5);

            $request->merge(['overall_rating' => $overall]);
        }

        $validated = $request->validate([
            'review_period' => 'required|string|max:50',
            'review_date' => 'nullable|date',
            'kpi_scores' => 'nullable|array',
            'overall_rating' => 'required|numeric|between:1.0,5.0',
            'strengths' => 'nullable|string',
            'improvements' => 'nullable|string',
            'areas_for_improvement' => 'nullable|string',
            'goals' => 'nullable|string',
            'action_plan' => 'nullable|string',
            'manager_feedback' => 'nullable|string',
            'status' => 'nullable|in:draft,submitted,acknowledged',
        ]);

        $reviewerId = $request->user()?->id ?? 1;
        $review = $this->productivityService->createReview($tenantId, $employeeId, $validated, $reviewerId);

        return response()->json([
            'status' => 'success',
            'message' => 'Performance review recorded.',
            'data' => $review->load('reviewer:id,name'),
        ], 201);
    }

    /**
     * List productivity goals.
     */
    public function getGoals(int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $goals = EmployeeProductivityGoal::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->orderBy('period_end', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $goals,
        ]);
    }

    /**
     * Set / Store productivity goal.
     */
    public function storeGoal(Request $request, int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();

        if ($request->has('current_value') && !$request->has('achieved_value')) {
            $request->merge(['achieved_value' => $request->input('current_value')]);
        }
        if ($request->has('start_date') && !$request->has('period_start')) {
            $request->merge(['period_start' => $request->input('start_date')]);
        }
        if ($request->has('target_date') && !$request->has('period_end')) {
            $request->merge(['period_end' => $request->input('target_date')]);
        }
        if ($request->input('status') === 'in_progress') {
            $request->merge(['status' => 'on_track']);
        }

        $validated = $request->validate([
            'metric_name' => 'required|string|max:100',
            'target_value' => 'required|numeric|min:0',
            'achieved_value' => 'nullable|numeric|min:0',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date',
            'status' => 'nullable|in:on_track,achieved,missed',
        ]);

        $goal = $this->productivityService->setGoal($tenantId, $employeeId, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Productivity target created.',
            'data' => $goal,
        ], 201);
    }
}
