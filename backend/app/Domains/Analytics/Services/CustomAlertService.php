<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\CustomAnalyticsAlert;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Domains\Support\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CustomAlertService
{
    /**
     * Evaluate all active custom alerts for a tenant and return triggered alerts
     */
    public function evaluateAlerts(int $tenantId): array
    {
        $alerts = CustomAnalyticsAlert::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $triggered = [];

        foreach ($alerts as $alert) {
            $currentValue = $this->computeMetricValue($tenantId, $alert->metric_type, $alert->branch_id);
            $isBreached = $this->evaluateCondition($currentValue, $alert->condition_operator, (float) $alert->threshold_value);

            if ($isBreached) {
                $payload = [
                    'alert_id' => $alert->id,
                    'name' => $alert->name,
                    'metric_type' => $alert->metric_type,
                    'current_value' => $currentValue,
                    'threshold_value' => (float) $alert->threshold_value,
                    'condition_operator' => $alert->condition_operator,
                    'triggered_at' => Carbon::now()->toIso8601String(),
                    'triggered_at_ist' => IndiaFirstService::formatIstDateTime(Carbon::now()),
                    'channels' => $alert->notification_channels ?? ['in_app'],
                ];

                $alert->update([
                    'last_triggered_at' => Carbon::now(),
                    'last_payload' => $payload,
                ]);

                $triggered[] = $payload;

                // Log or dispatch notification
                Log::info("Custom Analytics Alert Triggered: [{$alert->name}]", $payload);
            }
        }

        return [
            'total_alerts_checked' => $alerts->count(),
            'evaluated' => $alerts->count(),
            'triggered_count' => count($triggered),
            'triggered' => count($triggered),
            'triggered_alerts' => $triggered,
        ];
    }

    /**
     * Compute current value for a given metric type
     */
    public function computeMetricValue(int $tenantId, string $metricType, ?int $branchId = null): float
    {
        switch ($metricType) {
            case 'low_lead_conversion':
                $total = Lead::where('tenant_id', $tenantId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->count();
                $converted = Lead::where('tenant_id', $tenantId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('status', 'converted')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->count();
                return $total > 0 ? round(($converted / $total) * 100, 1) : 0.0;

            case 'overdue_payments':
                return (float) Invoice::where('tenant_id', $tenantId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('status', 'overdue')
                    ->sum('outstanding_amount');

            case 'missed_followups':
                return (float) Task::where('tenant_id', $tenantId)
                    ->where('status', '!=', 'completed')
                    ->where('due_date', '<', Carbon::now())
                    ->count();

            case 'high_ticket_backlog':
                return (float) SupportTicket::where('tenant_id', $tenantId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereIn('status', ['new', 'open', 'in_progress'])
                    ->count();

            case 'low_sales':
                return (float) Deal::where('tenant_id', $tenantId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->where('stage', 'won')
                    ->whereMonth('created_at', Carbon::now()->month)
                    ->sum('value');

            case 'high_expenses':
                return (float) Expense::where('tenant_id', $tenantId)
                    ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                    ->whereMonth('expense_date', Carbon::now()->month)
                    ->sum('amount');

            default:
                return 0.0;
        }
    }

    /**
     * Evaluate condition operator
     */
    protected function evaluateCondition(float $current, string $operator, float $threshold): bool
    {
        return match ($operator) {
            '>' => $current > $threshold,
            '>=' => $current >= $threshold,
            '<' => $current < $threshold,
            '<=' => $current <= $threshold,
            '==' => abs($current - $threshold) < 0.001,
            default => false,
        };
    }
}
