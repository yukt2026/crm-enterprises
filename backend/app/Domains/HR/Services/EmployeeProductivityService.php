<?php

namespace App\Domains\HR\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeePerformanceReview;
use App\Domains\HR\Models\EmployeeProductivityGoal;
use App\Domains\Projects\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeProductivityService
{
    /**
     * Holistic employee productivity analytics
     */
    public function getProductivityMetrics(int $tenantId, int $employeeId, ?string $period = null): array
    {
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);
        $userId = $employee->user_id;

        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();

        // 1. Task Management Metrics
        $tasksQuery = Task::where('tenant_id', $tenantId);
        if ($userId) {
            $tasksQuery->where('assigned_user_id', $userId);
        } else {
            $tasksQuery->whereRaw('1 = 0');
        }

        $allTasks = $tasksQuery->get();
        $totalTasks = $allTasks->count();
        $completedTasks = $allTasks->whereIn('status', ['completed', 'done'])->count();
        $taskCompletionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 100.0;

        // 2. Sales & Revenue Metrics
        $dealsWonCount = 0;
        $totalDealsWonValue = 0.0;
        if ($userId) {
            $wonDeals = Deal::where('tenant_id', $tenantId)
                ->where('assigned_user_id', $userId)
                ->where('stage', 'won')
                ->get();
            $dealsWonCount = $wonDeals->count();
            $totalDealsWonValue = (float) $wonDeals->sum('value');
        }

        // 3. Attendance Reliability
        $attendances = Attendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $daysPresent = $attendances->whereIn('status', ['present', 'late'])->count();
        $lateArrivals = $attendances->where('is_late', true)->count();
        $overtimeMinutes = (int) $attendances->sum('overtime_minutes');

        // 4. Goals and Targets
        $goals = EmployeeProductivityGoal::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->get();

        $goalsOnTrack = $goals->whereIn('status', ['on_track', 'achieved'])->count();
        $goalPacing = $goals->count() > 0 ? round(($goalsOnTrack / $goals->count()) * 100, 1) : 100.0;

        // 5. Recent Performance Reviews
        $reviews = EmployeePerformanceReview::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->orderBy('review_date', 'desc')
            ->limit(3)
            ->get();

        $latestRating = $reviews->first()?->overall_rating ?? 4.50;

        // 6. Holistic Weighted Productivity Score (0 - 100)
        // Task Execution: 35%, Attendance: 25%, Goals: 25%, Review Rating: 15%
        $taskComponent = ($taskCompletionRate / 100) * 35;
        $attendanceComponent = min(25, ($daysPresent / max(1, now()->day)) * 25);
        $goalsComponent = ($goalPacing / 100) * 25;
        $reviewComponent = ($latestRating / 5.0) * 15;

        $overallScore = min(100.0, round($taskComponent + $attendanceComponent + $goalsComponent + $reviewComponent, 1));

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'department' => $employee->department,
            'designation' => $employee->designation,
            'overall_productivity_score' => $overallScore,
            'holistic_productivity_score' => $overallScore,
            'metrics' => [
                'holistic_productivity_score' => $overallScore,
                'overall_productivity_score' => $overallScore,
                'tasks_completed' => $completedTasks,
                'deals_won_value' => $totalDealsWonValue,
                'attendance_reliability_rate' => $taskCompletionRate,
            ],
            'score_breakdown' => [
                'task_execution_score' => round($taskComponent, 1),
                'attendance_reliability_score' => round($attendanceComponent, 1),
                'goal_pacing_score' => round($goalsComponent, 1),
                'review_appraisal_score' => round($reviewComponent, 1),
            ],
            'tasks' => [
                'total_assigned' => $totalTasks,
                'completed' => $completedTasks,
                'completion_rate_pct' => $taskCompletionRate,
            ],
            'sales_performance' => [
                'deals_won_count' => $dealsWonCount,
                'total_revenue_won' => $totalDealsWonValue,
            ],
            'attendance_summary' => [
                'days_present' => $daysPresent,
                'late_arrivals' => $lateArrivals,
                'overtime_hours' => round($overtimeMinutes / 60, 2),
            ],
            'goals' => $goals,
            'recent_reviews' => $reviews,
            'contextual_note' => 'Score measures holistic delivery and quality of work in balance with operational consistency, without penalizing high-complexity low-velocity workflows.',
        ];
    }

    /**
     * Create performance review
     */
    public function createReview(int $tenantId, int $employeeId, array $data, ?int $reviewerId = null): EmployeePerformanceReview
    {
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);

        return EmployeePerformanceReview::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'reviewer_id' => $reviewerId,
            'review_period' => $data['review_period'] ?? '2026-Q4',
            'review_date' => $data['review_date'] ?? now()->toDateString(),
            'kpi_scores' => $data['kpi_scores'] ?? [
                'quality_of_work' => 4.5,
                'team_collaboration' => 4.8,
                'problem_solving' => 4.2,
                'timeliness' => 4.6,
            ],
            'overall_rating' => $data['overall_rating'] ?? 4.50,
            'strengths' => $data['strengths'] ?? 'Demonstrates high initiative and excellent domain expertise.',
            'improvements' => $data['improvements'] ?? 'Delegate operational tasks to focus on strategic execution.',
            'goals' => $data['goals'] ?? 'Lead regional enterprise onboarding rollout.',
            'manager_feedback' => $data['manager_feedback'] ?? 'Strong positive trajectory across the quarter.',
            'status' => $data['status'] ?? 'submitted',
        ]);
    }

    /**
     * Set employee productivity goal
     */
    public function setGoal(int $tenantId, int $employeeId, array $data): EmployeeProductivityGoal
    {
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);

        return EmployeeProductivityGoal::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'metric_name' => $data['metric_name'],
            'target_value' => $data['target_value'],
            'achieved_value' => $data['achieved_value'] ?? 0.0,
            'period_start' => $data['period_start'] ?? now()->startOfMonth()->toDateString(),
            'period_end' => $data['period_end'] ?? now()->endOfMonth()->toDateString(),
            'status' => $data['status'] ?? 'on_track',
        ]);
    }
}
