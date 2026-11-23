<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use Carbon\Carbon;

class ExecutiveAnalyticsService
{
    /**
     * Compute comprehensive, permission-aware executive metrics with comparative delta analysis
     */
    public function getExecutiveMetrics(int $tenantId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();

        // Calculate comparison duration
        $daysDiff = max(1, $fromDate->diffInDays($toDate));
        $prevFromDate = $fromDate->copy()->subDays($daysDiff);
        $prevToDate = $fromDate->copy()->subSecond();

        $employeeId = $filters['employee_id'] ?? null;

        // Current period metrics
        $currentMetrics = $this->aggregatePeriodMetrics($tenantId, $fromDate, $toDate, $employeeId);

        // Previous period metrics for trend analysis
        $previousMetrics = $this->aggregatePeriodMetrics($tenantId, $prevFromDate, $prevToDate, $employeeId);

        // Calculate deltas
        $deltas = [];
        foreach ($currentMetrics as $key => $val) {
            if (is_numeric($val)) {
                $prev = $previousMetrics[$key] ?? 0;
                $diff = $val - $prev;
                $pct = $prev > 0 ? round(($diff / $prev) * 100, 1) : ($val > 0 ? 100 : 0);
                $deltas[$key] = [
                    'current' => $val,
                    'previous' => $prev,
                    'change' => $diff,
                    'change_percent' => $pct,
                ];
            }
        }

        return [
            'period' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
                'previous_from' => $prevFromDate->toDateString(),
                'previous_to' => $prevToDate->toDateString(),
            ],
            'kpis' => $currentMetrics,
            'trends' => $deltas,
        ];
    }

    /**
     * Aggregate all KPIs for a given time window
     */
    protected function aggregatePeriodMetrics(int $tenantId, Carbon $from, Carbon $to, ?int $employeeId = null): array
    {
        // 1. Leads
        $leadQ = Lead::where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $to]);
        if ($employeeId) {
            $leadQ->where('assigned_user_id', $employeeId);
        }
        $totalLeads = (clone $leadQ)->count();
        $newLeads = (clone $leadQ)->where('status', 'new')->count();
        $qualifiedLeads = (clone $leadQ)->where('status', 'qualified')->count();
        $convertedLeads = (clone $leadQ)->where('status', 'converted')->count();
        $lostLeads = (clone $leadQ)->where('status', 'lost')->count();
        $leadConversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        // 2. Deals & Sales
        $dealQ = Deal::where('tenant_id', $tenantId)->whereBetween('created_at', [$from, $to]);
        if ($employeeId) {
            $dealQ->where('assigned_user_id', $employeeId);
        }
        $openDeals = (clone $dealQ)->whereNotIn('stage', ['won', 'lost'])->count();
        $wonDealsCount = (clone $dealQ)->where('stage', 'won')->count();
        $wonDealValue = (float) (clone $dealQ)->where('stage', 'won')->sum('value');
        $pipelineValue = (float) (clone $dealQ)->whereNotIn('stage', ['won', 'lost'])->sum('value');
        $totalDealsCount = (clone $dealQ)->count();
        $winRate = $totalDealsCount > 0 ? round(($wonDealsCount / $totalDealsCount) * 100, 1) : 0;
        $avgDealSize = $wonDealsCount > 0 ? round($wonDealValue / $wonDealsCount, 2) : 0;

        // 3. Finance & Invoices
        $invoicedAmount = (float) Invoice::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_amount');

        $receivedRevenue = (float) Payment::where('tenant_id', $tenantId)
            ->where('status', 'success')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $overdueInvoices = (float) Invoice::where('tenant_id', $tenantId)
            ->where('status', 'overdue')
            ->sum('total_amount');

        // 4. Marketing Spend vs Provider Reported
        $campaigns = MarketingCampaign::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $providerReportedSpend = (float) $campaigns->where('spend_type', 'provider_reported')->sum('spend_amount');
        $manualEstimatedSpend = (float) $campaigns->where('spend_type', 'manual_estimate')->sum('spend_amount');
        $totalMarketingSpend = $providerReportedSpend + $manualEstimatedSpend;

        $cpl = $totalLeads > 0 && $totalMarketingSpend > 0 ? round($totalMarketingSpend / $totalLeads, 2) : 0;
        $roas = $totalMarketingSpend > 0 ? round($wonDealValue / $totalMarketingSpend, 2) : 0;

        // 5. Communications & AI
        $whatsappConvs = WhatsAppConversation::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $aiConvs = WhatsAppConversation::where('tenant_id', $tenantId)
            ->where('is_ai_handled', true)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $callsCount = VoiceCall::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $completedCallsCount = VoiceCall::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // 6. Operations & Helpdesk
        $tasksOverdue = Task::where('tenant_id', $tenantId)->overdue()->count();
        $openTickets = SupportTicket::where('tenant_id', $tenantId)
            ->whereIn('status', ['new', 'open', 'in_progress', 'waiting_customer'])
            ->count();

        $breachedTickets = SupportTicket::where('tenant_id', $tenantId)
            ->where('sla_status', 'breached')
            ->count();

        $activeProjects = Project::where('tenant_id', $tenantId)
            ->where('is_archived', false)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $avgProjectProgress = (float) (Project::where('tenant_id', $tenantId)
            ->where('is_archived', false)
            ->avg('progress') ?? 0);

        return [
            'total_leads' => $totalLeads,
            'new_leads' => $newLeads,
            'qualified_leads' => $qualifiedLeads,
            'converted_leads' => $convertedLeads,
            'lost_leads' => $lostLeads,
            'lead_conversion_rate' => $leadConversionRate,

            'open_deals' => $openDeals,
            'won_deals_count' => $wonDealsCount,
            'won_deal_value' => round($wonDealValue, 2),
            'pipeline_value' => round($pipelineValue, 2),
            'win_rate' => $winRate,
            'average_deal_size' => $avgDealSize,

            'invoiced_amount' => round($invoicedAmount, 2),
            'received_revenue' => round($receivedRevenue, 2),
            'overdue_invoices_value' => round($overdueInvoices, 2),

            'total_marketing_spend' => round($totalMarketingSpend, 2),
            'provider_reported_spend' => round($providerReportedSpend, 2),
            'manual_estimated_spend' => round($manualEstimatedSpend, 2),
            'cost_per_lead' => $cpl,
            'roas' => $roas,

            'whatsapp_conversations' => $whatsappConvs,
            'ai_conversations' => $aiConvs,
            'total_voice_calls' => $callsCount,
            'completed_voice_calls' => $completedCallsCount,

            'tasks_overdue' => $tasksOverdue,
            'open_support_tickets' => $openTickets,
            'breached_support_tickets' => $breachedTickets,
            'active_projects' => $activeProjects,
            'average_project_progress' => round($avgProjectProgress, 1),
        ];
    }
}
