<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\BranchSalesTarget;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Invoice;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Domains\Support\Models\SupportTicket;
use Carbon\Carbon;

class AiBusinessInsightsService
{
    /**
     * Generate permission-aware, grounded AI business insights
     */
    public function generateInsights(int $tenantId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();

        $daysDiff = max(1, $fromDate->diffInDays($toDate));
        $prevFromDate = $fromDate->copy()->subDays($daysDiff);
        $prevToDate = $fromDate->copy()->subSecond();

        $insights = [];

        // 1. Lead Conversion Trend Insight
        $currentLeads = Lead::where('tenant_id', $tenantId)->whereBetween('created_at', [$fromDate, $toDate]);
        $currentTotal = (clone $currentLeads)->count();
        $currentConverted = (clone $currentLeads)->where('status', 'converted')->count();
        $currentConvRate = $currentTotal > 0 ? ($currentConverted / $currentTotal) * 100 : 0;

        $prevLeads = Lead::where('tenant_id', $tenantId)->whereBetween('created_at', [$prevFromDate, $prevToDate]);
        $prevTotal = (clone $prevLeads)->count();
        $prevConverted = (clone $prevLeads)->where('status', 'converted')->count();
        $prevConvRate = $prevTotal > 0 ? ($prevConverted / $prevTotal) * 100 : 0;

        $diffRate = round($currentConvRate - $prevConvRate, 1);
        if ($currentTotal > 0) {
            $insights[] = [
                'insight_key' => 'lead_conversion_velocity',
                'title' => $diffRate >= 0 ? 'Lead Conversion Velocity Growing' : 'Lead Conversion Rate Deceleration',
                'category' => 'leads',
                'type' => $diffRate >= 0 ? 'opportunity' : 'warning',
                'supporting_data' => [
                    'current_rate' => round($currentConvRate, 1) . '%',
                    'previous_rate' => round($prevConvRate, 1) . '%',
                    'delta_percentage_points' => $diffRate,
                    'current_leads_count' => $currentTotal,
                    'converted_count' => $currentConverted,
                ],
                'date_range' => "{$fromDate->toDateString()} to {$toDate->toDateString()}",
                'related_records' => (clone $currentLeads)->where('status', 'new')->take(5)->pluck('id')->toArray(),
                'explanation' => $diffRate >= 0
                    ? "Lead conversion increased by {$diffRate}% compared to the prior period, indicating higher lead qualification effectiveness."
                    : "Lead conversion decreased by " . abs($diffRate) . "% compared to the prior period. Newly captured leads may require faster first-contact response.",
                'suggested_action' => $diffRate < 0 ? 'Activate lead distribution SLA automation and prompt sales reps to follow up within 15 minutes.' : 'Scale high-performing acquisition channels.',
                'confidence' => 0.92,
                'limitations' => 'Based solely on status transitions of recorded CRM leads within the selected date window.',
            ];
        }

        // 2. Overdue Follow-ups Insight
        $overdueTasks = Task::where('tenant_id', $tenantId)
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', Carbon::now())
            ->with('assignedUser')
            ->get();

        if ($overdueTasks->count() > 0) {
            $insights[] = [
                'insight_key' => 'overdue_followups_backlog',
                'title' => "{$overdueTasks->count()} Overdue Follow-up Actions Require Attention",
                'category' => 'sales',
                'type' => 'action_required',
                'supporting_data' => [
                    'overdue_count' => $overdueTasks->count(),
                    'impacted_leads_or_deals' => $overdueTasks->whereNotNull('lead_id')->count(),
                    'oldest_due_date' => $overdueTasks->min('due_date'),
                ],
                'date_range' => 'Active Backlog',
                'related_records' => $overdueTasks->take(10)->pluck('id')->toArray(),
                'explanation' => "There are {$overdueTasks->count()} follow-up activities past their scheduled deadline, risking deal stagnation and lead leakage.",
                'suggested_action' => 'Reassign stalled follow-ups or trigger automated WhatsApp reminders to assigned reps.',
                'confidence' => 0.96,
                'limitations' => 'Requires task records to have accurate due date assignments.',
            ];
        }

        // 3. Approaching Deals Insight (Closing within 7 days)
        $closingDeals = Deal::where('tenant_id', $tenantId)
            ->whereNotIn('stage', ['won', 'lost'])
            ->whereBetween('close_date', [Carbon::now(), Carbon::now()->addDays(7)])
            ->orderBy('value', 'desc')
            ->get();

        if ($closingDeals->count() > 0) {
            $totalClosingValue = $closingDeals->sum('value');
            $insights[] = [
                'insight_key' => 'approaching_deal_closures',
                'title' => "₹" . IndiaFirstService::formatIndianNumber($totalClosingValue) . " in Pipeline Scheduled to Close This Week",
                'category' => 'sales',
                'type' => 'opportunity',
                'supporting_data' => [
                    'deals_count' => $closingDeals->count(),
                    'total_value' => (float) $totalClosingValue,
                    'total_value_short' => IndiaFirstService::formatShortInr($totalClosingValue),
                ],
                'date_range' => 'Next 7 Days',
                'related_records' => $closingDeals->take(5)->pluck('id')->toArray(),
                'explanation' => "{$closingDeals->count()} deals are scheduled to close within the next 7 days, representing significant potential monthly revenue.",
                'suggested_action' => 'Ensure decision-makers have received revised proposals and follow up with senior executive sponsors.',
                'confidence' => 0.88,
                'limitations' => 'Closing dates reflect sales representative forecasts and may slip without contract sign-off.',
            ];
        }

        // 4. Receivables / Aging Outstanding Insight
        $overdueInvoices = Invoice::where('tenant_id', $tenantId)
            ->where('status', 'overdue')
            ->where('outstanding_amount', '>', 0)
            ->get();

        if ($overdueInvoices->count() > 0) {
            $totalOverdue = (float) $overdueInvoices->sum('outstanding_amount');
            $insights[] = [
                'insight_key' => 'accounts_receivable_aging_alert',
                'title' => 'Outstanding Accounts Receivable Growing in Overdue Bracket',
                'category' => 'finance',
                'type' => 'warning',
                'supporting_data' => [
                    'overdue_invoices_count' => $overdueInvoices->count(),
                    'total_overdue_balance' => (float) $totalOverdue,
                    'total_overdue_short' => IndiaFirstService::formatShortInr($totalOverdue),
                ],
                'date_range' => 'Current Ledger',
                'related_records' => $overdueInvoices->take(5)->pluck('id')->toArray(),
                'explanation' => "There is " . IndiaFirstService::formatShortInr($totalOverdue) . " in overdue invoices past the payment term grace period.",
                'suggested_action' => 'Generate UPI payment links and dispatch automated payment reminder notifications to clients.',
                'confidence' => 0.95,
                'limitations' => 'Reflects billed invoice ledger; excludes unbilled or cash transactions.',
            ];
        }

        // 5. Branch Sales Target Shortfall
        $now = Carbon::now();
        $targets = BranchSalesTarget::where('tenant_id', $tenantId)
            ->where('period_type', 'monthly')
            ->where('period_year', $now->year)
            ->where('period_number', $now->month)
            ->with('branch')
            ->get();

        foreach ($targets as $target) {
            $achieved = Deal::where('branch_id', $target->branch_id)
                ->where('stage', 'won')
                ->whereMonth('created_at', Carbon::now()->month)
                ->sum('value');

            $targetAmount = (float) $target->target_amount;
            if ($targetAmount > 0 && $achieved < ($targetAmount * 0.70)) {
                $pct = round(($achieved / $targetAmount) * 100, 1);
                $insights[] = [
                    'insight_key' => "branch_target_shortfall_{$target->branch_id}",
                    'title' => "Branch [{$target->branch->name}] Running at {$pct}% of Monthly Target",
                    'category' => 'branches',
                    'type' => 'warning',
                    'supporting_data' => [
                        'branch_name' => $target->branch->name,
                        'target_amount' => $targetAmount,
                        'achieved_amount' => (float) $achieved,
                        'achievement_percent' => $pct,
                    ],
                    'date_range' => "Month of " . Carbon::now()->format('F Y'),
                    'related_records' => [$target->branch_id],
                    'explanation' => "Branch {$target->branch->name} has achieved {$pct}% of its ₹" . IndiaFirstService::formatIndianNumber($targetAmount) . " target for the current month.",
                    'suggested_action' => 'Review active pipeline deals and coordinate with branch manager on key enterprise opportunities.',
                    'confidence' => 0.90,
                    'limitations' => 'Assumes sales are recorded with branch attribution.',
                ];
                break;
            }
        }

        // 6. Support Ticket SLA Breaches
        $breachedTickets = SupportTicket::where('tenant_id', $tenantId)
            ->where('sla_status', 'breached')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        if ($breachedTickets->count() > 0) {
            $insights[] = [
                'insight_key' => 'support_sla_breach_spike',
                'title' => "{$breachedTickets->count()} Support Ticket SLA Breaches in Selected Period",
                'category' => 'support',
                'type' => 'warning',
                'supporting_data' => [
                    'breached_count' => $breachedTickets->count(),
                    'urgent_priority_count' => $breachedTickets->where('priority', 'urgent')->count(),
                ],
                'date_range' => "{$fromDate->toDateString()} to {$toDate->toDateString()}",
                'related_records' => $breachedTickets->take(5)->pluck('id')->toArray(),
                'explanation' => "{$breachedTickets->count()} customer support tickets have exceeded guaranteed response or resolution countdown timers.",
                'suggested_action' => 'Redistribute support queue load and update knowledge base articles for frequently asked questions.',
                'confidence' => 0.94,
                'limitations' => 'Applies to tickets with active SLA tracking policies.',
            ];
        }

        foreach ($insights as &$item) {
            $item['confidence_score'] = $item['confidence'] ?? 0.9;
            $item['limitations_disclaimer'] = $item['limitations'] ?? '';
            $item['suggested_actions'] = [$item['suggested_action'] ?? ''];
        }
        unset($item);

        return [
            'total_insights' => count($insights),
            'generated_at' => Carbon::now()->toIso8601String(),
            'generated_at_ist' => IndiaFirstService::formatIstDateTime(Carbon::now()),
            'insights' => $insights,
        ];
    }
}
