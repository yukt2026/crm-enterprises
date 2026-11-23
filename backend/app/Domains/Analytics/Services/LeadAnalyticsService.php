<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadAnalyticsService
{
    /**
     * Compute comprehensive lead analytics including aging, attribution breakdowns, and funnel metrics
     */
    public function getLeadMetrics(int $tenantId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();
        $employeeId = $filters['employee_id'] ?? null;

        $baseQuery = Lead::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        if ($employeeId) {
            $baseQuery->where('assigned_user_id', $employeeId);
        }

        $allLeads = (clone $baseQuery)->get();
        $totalLeads = $allLeads->count();
        $qualifiedLeads = $allLeads->where('status', 'qualified')->count();
        $convertedLeads = $allLeads->where('status', 'converted')->count();
        $lostLeads = $allLeads->where('status', 'lost')->count();
        $newLeads = $allLeads->where('status', 'new')->count();

        $qualificationRate = $totalLeads > 0 ? round(($qualifiedLeads / $totalLeads) * 100, 1) : 0;
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        // 1. By Source
        $bySource = $allLeads->groupBy(function ($lead) {
            return $lead->source ?: 'Unknown';
        })->map(function ($group, $key) use ($totalLeads) {
            $count = $group->count();
            return [
                'source' => $key,
                'count' => $count,
                'percentage' => $totalLeads > 0 ? round(($count / $totalLeads) * 100, 1) : 0,
                'converted' => $group->where('status', 'converted')->count(),
            ];
        })->values()->sortByDesc('count')->values()->all();

        // 2. By Status
        $byStatus = $allLeads->groupBy('status')->map(function ($group, $status) {
            return [
                'status' => $status,
                'count' => $group->count(),
            ];
        })->values()->all();

        // 3. By Location
        $byLocation = $allLeads->groupBy(function ($lead) {
            return $lead->location ?: ($lead->city ?: 'Unspecified');
        })->map(function ($group, $loc) {
            return [
                'location' => $loc,
                'count' => $group->count(),
                'converted' => $group->where('status', 'converted')->count(),
            ];
        })->values()->sortByDesc('count')->take(10)->values()->all();

        // 4. By Employee / Rep
        $byEmployee = $allLeads->groupBy(function ($lead) {
            return $lead->assigned_user_id ? "User #{$lead->assigned_user_id}" : 'Unassigned';
        })->map(function ($group, $rep) {
            return [
                'representative' => $rep,
                'assigned_count' => $group->count(),
                'converted_count' => $group->where('status', 'converted')->count(),
                'conversion_rate' => $group->count() > 0 ? round(($group->where('status', 'converted')->count() / $group->count()) * 100, 1) : 0,
            ];
        })->values()->sortByDesc('assigned_count')->all();

        // 5. Lead Aging Buckets (for active/open leads)
        $now = Carbon::now();
        $agingBuckets = [
            '0_7_days' => 0,
            '8_14_days' => 0,
            '15_30_days' => 0,
            '30_plus_days' => 0,
        ];
        $openLeads = $allLeads->whereNotIn('status', ['converted', 'lost']);
        foreach ($openLeads as $lead) {
            $ageDays = $lead->created_at ? $lead->created_at->diffInDays($now) : 0;
            if ($ageDays <= 7) {
                $agingBuckets['0_7_days']++;
            } elseif ($ageDays <= 14) {
                $agingBuckets['8_14_days']++;
            } elseif ($ageDays <= 30) {
                $agingBuckets['15_30_days']++;
            } else {
                $agingBuckets['30_plus_days']++;
            }
        }

        // 6. Lost Reason Analysis
        $lostReasons = $allLeads->where('status', 'lost')->groupBy(function ($lead) {
            return $lead->lost_reason ?: 'No Reason Specified';
        })->map(function ($group, $reason) {
            return [
                'reason' => $reason,
                'count' => $group->count(),
            ];
        })->values()->sortByDesc('count')->values()->all();

        // 7. Duplicate Lead Detection
        $duplicateEmails = $allLeads->filter(fn($l) => !empty($l->email))->groupBy('email')->filter(fn($g) => $g->count() > 1)->count();
        $duplicatePhones = $allLeads->filter(fn($l) => !empty($l->phone))->groupBy('phone')->filter(fn($g) => $g->count() > 1)->count();
        $duplicateRate = $totalLeads > 0 ? round((($duplicateEmails + $duplicatePhones) / $totalLeads) * 100, 1) : 0;

        // 8. Lead Response Time (average hours between lead creation and updated activity)
        $avgResponseHours = 0;
        $leadsWithActivity = $allLeads->filter(fn($l) => $l->updated_at && $l->updated_at->gt($l->created_at));
        if ($leadsWithActivity->count() > 0) {
            $totalMins = $leadsWithActivity->reduce(function ($carry, $l) {
                return $carry + $l->created_at->diffInMinutes($l->updated_at);
            }, 0);
            $avgResponseHours = round(($totalMins / $leadsWithActivity->count()) / 60, 1);
        }

        return [
            'total_leads' => $totalLeads,
            'new_leads' => $newLeads,
            'qualified_leads' => $qualifiedLeads,
            'converted_leads' => $convertedLeads,
            'lost_leads' => $lostLeads,
            'qualification_rate' => $qualificationRate,
            'conversion_rate' => $conversionRate,
            'duplicate_rate' => $duplicateRate,
            'avg_response_hours' => $avgResponseHours,
            'aging_buckets' => $agingBuckets,
            'by_source' => $bySource,
            'by_status' => $byStatus,
            'by_location' => $byLocation,
            'by_employee' => $byEmployee,
            'lost_reasons' => $lostReasons,
        ];
    }
}
