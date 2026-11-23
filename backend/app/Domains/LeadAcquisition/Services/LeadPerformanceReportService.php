<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadEscalationLog;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadPerformanceReportService
{
    /**
     * Complete Lead Intelligence & Performance Dashboard Data
     */
    public function getPerformanceDashboard(int $tenantId): array
    {
        return [
            'summary' => $this->getSummaryKpis($tenantId),
            'source_performance' => $this->getSourcePerformance($tenantId),
            'campaign_performance' => $this->getCampaignPerformance($tenantId),
            'employee_performance' => $this->getEmployeePerformance($tenantId),
            'lead_ageing' => $this->getLeadAgeing($tenantId),
            'followup_metrics' => $this->getFollowupMetrics($tenantId),
            'leakage_and_sla' => $this->getLeadLeakageReport($tenantId),
            'lost_and_recycled' => $this->getLostAndRecycledAnalysis($tenantId),
        ];
    }

    public function getSummaryKpis(int $tenantId): array
    {
        $totalLeads = Lead::where('tenant_id', $tenantId)->count();
        $convertedLeads = Lead::where('tenant_id', $tenantId)->where('status', 'converted')->count();
        $lostLeads = Lead::where('tenant_id', $tenantId)->where('status', 'lost')->count();
        $activeLeads = Lead::where('tenant_id', $tenantId)->whereNotIn('status', ['converted', 'lost'])->count();

        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;
        $totalPipelineValue = (float) Lead::where('tenant_id', $tenantId)->sum('estimated_value');

        return [
            'total_leads' => $totalLeads,
            'active_leads' => $activeLeads,
            'converted_leads' => $convertedLeads,
            'lost_leads' => $lostLeads,
            'conversion_rate_percent' => $conversionRate,
            'total_pipeline_value' => $totalPipelineValue,
        ];
    }

    public function getSourcePerformance(int $tenantId): array
    {
        return Lead::where('tenant_id', $tenantId)
            ->select(
                DB::raw("COALESCE(source, 'direct') as lead_source"),
                DB::raw('count(*) as total_leads'),
                DB::raw("sum(case when status = 'converted' then 1 else 0 end) as converted_count"),
                DB::raw('sum(estimated_value) as total_pipeline_value'),
                DB::raw("avg(case when score is not null then score else 0 end) as avg_score")
            )
            ->groupBy(DB::raw("COALESCE(source, 'direct')"))
            ->orderBy('total_leads', 'desc')
            ->get()
            ->map(function ($row) {
                $total = (int) $row->total_leads;
                $conv = (int) $row->converted_count;
                return [
                    'source' => $row->lead_source,
                    'total_leads' => $total,
                    'converted_count' => $conv,
                    'conversion_rate' => $total > 0 ? round(($conv / $total) * 100, 1) : 0,
                    'pipeline_value' => (float) $row->total_pipeline_value,
                    'avg_score' => round((float) $row->avg_score, 1),
                ];
            })
            ->toArray();
    }

    public function getCampaignPerformance(int $tenantId): array
    {
        return Lead::where('tenant_id', $tenantId)
            ->whereNotNull('campaign')
            ->select(
                'campaign',
                DB::raw('count(*) as leads_count'),
                DB::raw("sum(case when status = 'converted' then 1 else 0 end) as converted_count"),
                DB::raw('sum(estimated_value) as pipeline_value')
            )
            ->groupBy('campaign')
            ->orderBy('leads_count', 'desc')
            ->take(10)
            ->get()
            ->toArray();
    }

    public function getEmployeePerformance(int $tenantId): array
    {
        $reps = User::whereHas('workspaces', fn($q) => $q->where('tenants.id', $tenantId))->get(['id', 'name']);
        $result = [];

        foreach ($reps as $rep) {
            $leads = Lead::where('tenant_id', $tenantId)->where('assigned_user_id', $rep->id)->get();
            $total = $leads->count();
            if ($total === 0) continue;

            $converted = $leads->where('status', 'converted')->count();
            $contacted = $leads->whereNotIn('status', ['new', 'uncontacted'])->count();
            $breachedSla = $leads->where('sla_status', 'breached')->count();

            // Calculate average first response in minutes
            $responded = $leads->whereNotNull('sla_first_responded_at');
            $avgMinutes = 0;
            if ($responded->isNotEmpty()) {
                $totalMinutes = $responded->sum(fn($l) => $l->created_at->diffInMinutes($l->sla_first_responded_at));
                $avgMinutes = round($totalMinutes / $responded->count(), 1);
            }

            $result[] = [
                'user_id' => $rep->id,
                'name' => $rep->name,
                'assigned_leads' => $total,
                'contacted_leads' => $contacted,
                'converted_leads' => $converted,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
                'avg_first_response_minutes' => $avgMinutes,
                'sla_breaches' => $breachedSla,
            ];
        }

        return $result;
    }

    public function getLeadAgeing(int $tenantId): array
    {
        $now = now();
        $activeLeads = Lead::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['converted', 'lost'])
            ->get(['id', 'created_at']);

        $buckets = [
            'under_24_hours' => 0,
            '1_to_3_days' => 0,
            '4_to_7_days' => 0,
            '8_to_14_days' => 0,
            '15_to_30_days' => 0,
            'over_30_days' => 0,
        ];

        foreach ($activeLeads as $lead) {
            $hours = $lead->created_at ? $lead->created_at->diffInHours($now) : 0;
            if ($hours < 24) {
                $buckets['under_24_hours']++;
            } elseif ($hours <= 72) {
                $buckets['1_to_3_days']++;
            } elseif ($hours <= 168) {
                $buckets['4_to_7_days']++;
            } elseif ($hours <= 336) {
                $buckets['8_to_14_days']++;
            } elseif ($hours <= 720) {
                $buckets['15_to_30_days']++;
            } else {
                $buckets['over_30_days']++;
            }
        }

        return $buckets;
    }

    public function getFollowupMetrics(int $tenantId): array
    {
        $total = LeadFollowup::where('tenant_id', $tenantId)->count();
        $completed = LeadFollowup::where('tenant_id', $tenantId)->where('status', 'completed')->count();
        $pending = LeadFollowup::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $overdue = LeadFollowup::where('tenant_id', $tenantId)->where('status', 'pending')->where('scheduled_at', '<', now())->count();
        $rescheduled = LeadFollowup::where('tenant_id', $tenantId)->where('status', 'rescheduled')->count();

        return [
            'total_followups' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'overdue' => $overdue,
            'rescheduled' => $rescheduled,
            'completion_rate_percent' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
        ];
    }

    public function getLeadLeakageReport(int $tenantId): array
    {
        $totalEvaluated = Lead::where('tenant_id', $tenantId)->whereNotNull('sla_policy_id')->count();
        $breached = Lead::where('tenant_id', $tenantId)->where('sla_status', 'breached')->count();
        $escalated = Lead::where('tenant_id', $tenantId)->where('sla_status', 'escalated')->count();
        $withinSla = Lead::where('tenant_id', $tenantId)->where('sla_status', 'within_sla')->count();

        $escalationsCount = LeadEscalationLog::where('tenant_id', $tenantId)->count();
        $level1Count = LeadEscalationLog::where('tenant_id', $tenantId)->where('escalation_level', 1)->count();
        $level2Count = LeadEscalationLog::where('tenant_id', $tenantId)->where('escalation_level', 2)->count();

        $complianceRate = $totalEvaluated > 0 ? round(($withinSla / $totalEvaluated) * 100, 1) : 100;

        return [
            'total_sla_tracked_leads' => $totalEvaluated,
            'within_sla' => $withinSla,
            'breached_sla' => $breached,
            'escalated_leads' => $escalated,
            'compliance_rate_percent' => $complianceRate,
            'total_escalation_events' => $escalationsCount,
            'level_1_warnings' => $level1Count,
            'level_2_reassignments' => $level2Count,
        ];
    }

    public function getLostAndRecycledAnalysis(int $tenantId): array
    {
        $lostReasons = Lead::where('tenant_id', $tenantId)
            ->where('status', 'lost')
            ->select('lost_reason', DB::raw('count(*) as count'))
            ->groupBy('lost_reason')
            ->pluck('count', 'lost_reason')
            ->toArray();

        $recycledCount = Lead::where('tenant_id', $tenantId)->where('status', 'recycled')->count();

        $qualBreakdown = Lead::where('tenant_id', $tenantId)
            ->select('qualification_status', DB::raw('count(*) as count'))
            ->groupBy('qualification_status')
            ->pluck('count', 'qualification_status')
            ->toArray();

        return [
            'lost_reasons_breakdown' => $lostReasons,
            'recycled_leads_count' => $recycledCount,
            'qualification_breakdown' => $qualBreakdown,
        ];
    }
}
