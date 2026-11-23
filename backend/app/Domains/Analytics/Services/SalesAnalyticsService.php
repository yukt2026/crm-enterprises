<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CRM\Models\Deal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAnalyticsService
{
    /**
     * Compute comprehensive sales analytics including weighted forecast, stage velocity, and quota tracking
     */
    public function getSalesMetrics(int $tenantId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();
        $employeeId = $filters['employee_id'] ?? null;

        $baseQuery = Deal::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        if ($employeeId) {
            $baseQuery->where('assigned_user_id', $employeeId);
        }

        $allDeals = (clone $baseQuery)->get();
        $totalDeals = $allDeals->count();

        $wonDeals = $allDeals->where('stage', 'won');
        $lostDeals = $allDeals->where('stage', 'lost');
        $openDeals = $allDeals->whereNotIn('stage', ['won', 'lost']);

        $wonCount = $wonDeals->count();
        $lostCount = $lostDeals->count();
        $openCount = $openDeals->count();

        $wonValue = (float) $wonDeals->sum('value');
        $lostValue = (float) $lostDeals->sum('value');
        $pipelineValue = (float) $openDeals->sum('value');

        // Weighted forecast: open deals multiplied by their probability %
        $weightedPipelineForecast = (float) $openDeals->reduce(function ($carry, $deal) {
            $prob = $deal->probability ?? $this->getDefaultProbabilityForStage($deal->stage);
            return $carry + ($deal->value * ($prob / 100));
        }, 0);

        // Win rate & Loss rate
        $closedCount = $wonCount + $lostCount;
        $winRate = $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 1) : ($totalDeals > 0 ? round(($wonCount / $totalDeals) * 100, 1) : 0);
        $lossRate = $closedCount > 0 ? round(($lostCount / $closedCount) * 100, 1) : 0;
        $avgDealSize = $wonCount > 0 ? round($wonValue / $wonCount, 2) : ($totalDeals > 0 ? round($allDeals->sum('value') / $totalDeals, 2) : 0);

        // Sales cycle duration (average days from creation to won date)
        $salesCycleDays = 0;
        if ($wonCount > 0) {
            $totalDays = $wonDeals->reduce(function ($carry, $deal) {
                $endDate = $deal->won_at ? Carbon::parse($deal->won_at) : ($deal->updated_at ?? Carbon::now());
                $created = $deal->created_at ?? Carbon::now();
                return $carry + max(1, $created->diffInDays($endDate));
            }, 0);
            $salesCycleDays = round($totalDays / $wonCount, 1);
        }

        // Stage-wise breakdown
        $stageBreakdown = $allDeals->groupBy('stage')->map(function ($deals, $stage) {
            return [
                'stage' => $stage,
                'count' => $deals->count(),
                'total_value' => round((float) $deals->sum('value'), 2),
                'weighted_value' => round((float) $deals->reduce(function ($carry, $d) use ($stage) {
                    $prob = $d->probability ?? $this->getDefaultProbabilityForStage($stage);
                    return $carry + ($d->value * ($prob / 100));
                }, 0), 2),
            ];
        })->values()->all();

        // Rep performance & quota vs achievement
        $repPerformance = $allDeals->groupBy(function ($deal) {
            return $deal->assigned_user_id ? "User #{$deal->assigned_user_id}" : 'Unassigned';
        })->map(function ($deals, $rep) {
            $repWon = $deals->where('stage', 'won');
            $repWonVal = (float) $repWon->sum('value');
            $repTarget = 50000.0; // Benchmark target if not defined
            return [
                'representative' => $rep,
                'total_deals' => $deals->count(),
                'won_count' => $repWon->count(),
                'won_value' => round($repWonVal, 2),
                'quota_target' => $repTarget,
                'achievement_percent' => $repTarget > 0 ? round(($repWonVal / $repTarget) * 100, 1) : 0,
                'pipeline_value' => round((float) $deals->whereNotIn('stage', ['won', 'lost'])->sum('value'), 2),
            ];
        })->values()->sortByDesc('won_value')->all();

        // Source breakdown for revenue
        $revenueBySource = $wonDeals->groupBy(function ($deal) {
            return $deal->source ?: ($deal->utm_source ?: 'Direct / Organic');
        })->map(function ($deals, $src) {
            return [
                'source' => $src,
                'deals_count' => $deals->count(),
                'revenue' => round((float) $deals->sum('value'), 2),
            ];
        })->values()->sortByDesc('revenue')->all();

        return [
            'total_deals' => $totalDeals,
            'open_deals_count' => $openCount,
            'won_deals_count' => $wonCount,
            'lost_deals_count' => $lostCount,
            'pipeline_value' => round($pipelineValue, 2),
            'won_value' => round($wonValue, 2),
            'lost_value' => round($lostValue, 2),
            'weighted_forecast' => round($weightedPipelineForecast, 2),
            'win_rate' => $winRate,
            'loss_rate' => $lossRate,
            'avg_deal_size' => $avgDealSize,
            'avg_sales_cycle_days' => $salesCycleDays,
            'stage_breakdown' => $stageBreakdown,
            'rep_performance' => $repPerformance,
            'revenue_by_source' => $revenueBySource,
        ];
    }

    protected function getDefaultProbabilityForStage(string $stage): int
    {
        return match (strtolower($stage)) {
            'won' => 100,
            'closing', 'negotiation' => 80,
            'proposal' => 60,
            'qualification', 'demo' => 40,
            'prospecting', 'lead', 'discovery' => 20,
            'lost' => 0,
            default => 30,
        };
    }
}
