<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\HR\Models\Employee;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Projects\Models\Project;
use App\Domains\Support\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OwnerExecutiveDashboardService
{
    /**
     * Compute full Owner & Executive BI Dashboard metrics
     */
    public function getDashboardMetrics(int $tenantId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();

        $branchId = !empty($filters['branch_id']) ? (int) $filters['branch_id'] : null;
        $employeeId = !empty($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $departmentId = !empty($filters['department_id']) ? (int) $filters['department_id'] : null;

        // 1. Leads Lifecycle
        $leadsQuery = Lead::where('tenant_id', $tenantId);
        if ($branchId) $leadsQuery->where('branch_id', $branchId);
        if ($employeeId) $leadsQuery->where('assigned_user_id', $employeeId);

        $totalLeads = (clone $leadsQuery)->count();
        $periodLeadsQuery = (clone $leadsQuery)->whereBetween('created_at', [$fromDate, $toDate]);
        $newLeads = (clone $periodLeadsQuery)->where('status', 'new')->count();
        $qualifiedLeads = (clone $periodLeadsQuery)->where('status', 'qualified')->count();
        $convertedLeads = (clone $periodLeadsQuery)->where('status', 'converted')->count();
        $leadConversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0.0;

        // 2. Customers & Retention
        $custQuery = Customer::where('tenant_id', $tenantId);
        if ($branchId) $custQuery->where('branch_id', $branchId);
        $totalCustomers = (clone $custQuery)->count();
        $newCustomers = (clone $custQuery)->whereBetween('created_at', [$fromDate, $toDate])->count();
        
        // Retention: Customers with >1 invoice or deal
        $repeatCustomers = (clone $custQuery)->whereHas('invoices', function ($q) {
            $q->where('status', 'paid');
        }, '>=', 2)->count();
        $retentionRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0.0;

        // 3. Deals & Actual vs Forecast Sales
        $dealQuery = Deal::where('tenant_id', $tenantId);
        if ($branchId) $dealQuery->where('branch_id', $branchId);
        if ($employeeId) $dealQuery->where('assigned_user_id', $employeeId);

        $totalDeals = (clone $dealQuery)->count();
        $openDeals = (clone $dealQuery)->whereNotIn('stage', ['won', 'lost'])->get();
        $wonDeals = (clone $dealQuery)->where('stage', 'won')->whereBetween('created_at', [$fromDate, $toDate])->get();
        $lostDeals = (clone $dealQuery)->where('stage', 'lost')->whereBetween('created_at', [$fromDate, $toDate])->get();

        $wonRevenue = (float) $wonDeals->sum('value');
        $pipelineValue = (float) $openDeals->sum('value');

        // Explicit distinction: Actual vs Forecast
        $weightedPipelineForecast = (float) $openDeals->sum(function ($d) {
            $prob = $d->probability ?? 50;
            return ((float) $d->value) * ($prob / 100);
        });
        $committedForecast = (float) $openDeals->filter(fn($d) => ($d->probability ?? 0) >= 80)->sum('value');
        $bestCaseForecast = (float) $openDeals->filter(fn($d) => ($d->probability ?? 0) >= 50)->sum('value');

        // 4. Finance, Invoices & Collections
        $invQuery = Invoice::where('tenant_id', $tenantId);
        if ($branchId) $invQuery->where('branch_id', $branchId);

        $invoicedAmount = (float) (clone $invQuery)->whereBetween('created_at', [$fromDate, $toDate])->sum('total_amount');
        $pendingPayments = (float) (clone $invQuery)->whereIn('status', ['sent', 'partially_paid', 'overdue'])->sum('outstanding_amount');
        $overdueInvoices = (float) (clone $invQuery)->where('status', 'overdue')->sum('outstanding_amount');

        $payQuery = Payment::where('tenant_id', $tenantId)->where('status', 'success');
        $collectedRevenue = (float) (clone $payQuery)->whereBetween('created_at', [$fromDate, $toDate])->sum('amount');

        // 5. Expenses
        $expQuery = Expense::where('tenant_id', $tenantId);
        if ($branchId) $expQuery->where('branch_id', $branchId);
        $monthlyExpenses = (float) (clone $expQuery)->whereBetween('expense_date', [$fromDate, $toDate])->sum('amount');

        // 6. Branch Performance Summary
        $branches = Branch::where('tenant_id', $tenantId)->get()->map(function ($b) use ($fromDate, $toDate) {
            $branchLeads = Lead::where('branch_id', $b->id)->whereBetween('created_at', [$fromDate, $toDate])->count();
            $branchSales = Deal::where('branch_id', $b->id)->where('stage', 'won')->whereBetween('created_at', [$fromDate, $toDate])->sum('value');
            $branchExpenses = Expense::where('branch_id', $b->id)->whereBetween('expense_date', [$fromDate, $toDate])->sum('amount');
            return [
                'branch_id' => $b->id,
                'name' => $b->name,
                'code' => $b->code,
                'leads_count' => $branchLeads,
                'sales_revenue' => (float) $branchSales,
                'expenses' => (float) $branchExpenses,
                'net_margin' => (float) ($branchSales - $branchExpenses),
                'sales_formatted' => IndiaFirstService::formatShortInr($branchSales),
            ];
        });

        // 7. Employee Performance Summary (Top 5)
        $employees = Employee::where('tenant_id', $tenantId)->take(10)->get()->map(function ($emp) use ($fromDate, $toDate) {
            $wonValue = Deal::where('assigned_user_id', $emp->user_id)->where('stage', 'won')->whereBetween('created_at', [$fromDate, $toDate])->sum('value');
            $leadsAssigned = Lead::where('assigned_user_id', $emp->user_id)->whereBetween('created_at', [$fromDate, $toDate])->count();
            return [
                'employee_id' => $emp->id,
                'name' => $emp->full_name ?? $emp->name,
                'department' => $emp->department,
                'leads_count' => $leadsAssigned,
                'sales_won' => (float) $wonValue,
                'sales_won_formatted' => IndiaFirstService::formatShortInr($wonValue),
            ];
        })->sortByDesc('sales_won')->values();

        // 8. Campaign Performance Summary
        $campaigns = MarketingCampaign::where('tenant_id', $tenantId)->take(5)->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'channel' => $c->channel ?? 'omnichannel',
                'spend' => (float) $c->spend_amount,
                'leads_generated' => (int) ($c->leads_count ?? 0),
                'roi_percent' => (float) ($c->roas ? $c->roas * 100 : 0),
                'spend_formatted' => IndiaFirstService::formatShortInr($c->spend_amount),
            ];
        });

        // 9. Support Ticket Status
        $ticketQ = SupportTicket::where('tenant_id', $tenantId);
        if ($branchId) $ticketQ->where('branch_id', $branchId);
        $totalTickets = (clone $ticketQ)->count();
        $openTickets = (clone $ticketQ)->whereIn('status', ['new', 'open', 'in_progress', 'waiting_customer'])->count();
        $resolvedTickets = (clone $ticketQ)->where('status', 'resolved')->count();
        $slaBreachedTickets = (clone $ticketQ)->where('sla_status', 'breached')->count();

        // 10. Project Delivery Status
        $projQ = Project::where('tenant_id', $tenantId);
        $totalProjects = (clone $projQ)->count();
        $activeProjects = (clone $projQ)->whereIn('status', ['planned', 'active', 'in_progress'])->count();
        $completedProjects = (clone $projQ)->where('status', 'completed')->count();

        return [
            'period' => [
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
                'formatted_ist' => IndiaFirstService::formatIstDateTime($fromDate, 'd M Y') . ' to ' . IndiaFirstService::formatIstDateTime($toDate, 'd M Y'),
            ],
            'actuals' => [
                'total_leads' => $totalLeads,
                'new_leads' => $newLeads,
                'qualified_leads' => $qualifiedLeads,
                'converted_leads' => $convertedLeads,
                'lead_conversion_rate' => $leadConversionRate,

                'total_customers' => $totalCustomers,
                'new_customers' => $newCustomers,
                'repeat_customers' => $repeatCustomers,
                'customer_retention_rate' => $retentionRate,

                'total_deals' => $totalDeals,
                'won_deals_count' => $wonDeals->count(),
                'won_revenue' => $wonRevenue,
                'won_revenue_inr' => IndiaFirstService::formatInr($wonRevenue),
                'won_revenue_short' => IndiaFirstService::formatShortInr($wonRevenue),

                'collected_revenue' => $collectedRevenue,
                'collected_revenue_inr' => IndiaFirstService::formatInr($collectedRevenue),
                'collected_revenue_short' => IndiaFirstService::formatShortInr($collectedRevenue),

                'invoiced_sales' => $invoicedAmount,
                'invoiced_sales_inr' => IndiaFirstService::formatInr($invoicedAmount),
                'invoiced_sales_short' => IndiaFirstService::formatShortInr($invoicedAmount),

                'pending_payments' => $pendingPayments,
                'pending_payments_inr' => IndiaFirstService::formatInr($pendingPayments),
                'pending_payments_short' => IndiaFirstService::formatShortInr($pendingPayments),

                'overdue_invoices' => $overdueInvoices,
                'overdue_invoices_inr' => IndiaFirstService::formatInr($overdueInvoices),

                'monthly_expenses' => $monthlyExpenses,
                'monthly_expenses_inr' => IndiaFirstService::formatInr($monthlyExpenses),
                'monthly_expenses_short' => IndiaFirstService::formatShortInr($monthlyExpenses),

                'net_profit' => round($collectedRevenue - $monthlyExpenses, 2),
                'net_profit_short' => IndiaFirstService::formatShortInr($collectedRevenue - $monthlyExpenses),
            ],
            'forecasts' => [
                'pipeline_value' => $pipelineValue,
                'pipeline_value_inr' => IndiaFirstService::formatInr($pipelineValue),
                'pipeline_value_short' => IndiaFirstService::formatShortInr($pipelineValue),

                'weighted_pipeline_forecast' => round($weightedPipelineForecast, 2),
                'weighted_forecast_inr' => IndiaFirstService::formatInr($weightedPipelineForecast),
                'weighted_forecast_short' => IndiaFirstService::formatShortInr($weightedPipelineForecast),

                'committed_forecast' => round($committedForecast, 2),
                'committed_forecast_short' => IndiaFirstService::formatShortInr($committedForecast),

                'best_case_forecast' => round($bestCaseForecast, 2),
                'best_case_forecast_short' => IndiaFirstService::formatShortInr($bestCaseForecast),

                'forecast_disclaimer' => 'Forecast projections are estimated probabilities and not guaranteed collections.',
            ],
            'operations' => [
                'support_tickets' => [
                    'total' => $totalTickets,
                    'open' => $openTickets,
                    'resolved' => $resolvedTickets,
                    'sla_breached' => $slaBreachedTickets,
                ],
                'projects' => [
                    'total' => $totalProjects,
                    'active' => $activeProjects,
                    'completed' => $completedProjects,
                ],
            ],
            'performance' => [
                'branches' => $branches,
                'employees' => $employees,
                'campaigns' => $campaigns,
            ],
        ];
    }
}
