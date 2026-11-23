<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesForecastingService
{
    /**
     * Generate comprehensive sales forecast with statistical confidence & multi-dimensional projections.
     */
    public function getForecastReport(
        int $tenantId,
        ?int $pipelineId = null,
        ?int $branchId = null,
        ?int $assignedUserId = null,
        ?string $timeframe = 'quarterly' // monthly, quarterly, annual
    ): array {
        $now = Carbon::now();
        $startDate = $now->copy()->startOfQuarter();
        $endDate = $now->copy()->endOfQuarter();

        if ($timeframe === 'monthly') {
            $startDate = $now->copy()->startOfMonth();
            $endDate = $now->copy()->endOfMonth();
        } elseif ($timeframe === 'annual') {
            $startDate = $now->copy()->startOfYear();
            $endDate = $now->copy()->endOfYear();
        }

        $query = Deal::where('tenant_id', $tenantId)->where('is_archived', false);

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($assignedUserId) {
            $query->where('assigned_user_id', $assignedUserId);
        }

        $deals = $query->with(['pipelineStage', 'pipeline', 'assignedUser'])->get();

        $totalOpenDealsCount = 0;
        $unweightedPipelineValue = 0.0;
        $weightedForecastValue = 0.0;
        $committedValue = 0.0;     // Deals with probability >= 80%
        $bestCaseValue = 0.0;      // Deals with probability >= 50%
        $closedWonValue = 0.0;
        $closedLostValue = 0.0;
        $wonCount = 0;
        $lostCount = 0;

        $employeeStats = [];
        $branchStats = [];
        $stageBreakdown = [];
        $lostReasonBreakdown = [];
        $monthlyBuckets = [];

        foreach ($deals as $deal) {
            $val = (float) $deal->value;
            $prob = (int) $deal->probability;
            $weighted = round(($val * $prob) / 100, 2);
            $stage = $deal->stage;
            $empName = $deal->assignedUser?->name ?? 'Unassigned';
            $branchKey = $deal->branch_id ? "Branch #{$deal->branch_id}" : 'Headquarters';

            // Employee aggregation
            if (!isset($employeeStats[$empName])) {
                $employeeStats[$empName] = ['total_deals' => 0, 'pipeline_value' => 0.0, 'weighted_value' => 0.0, 'won_value' => 0.0];
            }
            $employeeStats[$empName]['total_deals']++;

            // Branch aggregation
            if (!isset($branchStats[$branchKey])) {
                $branchStats[$branchKey] = ['pipeline_value' => 0.0, 'weighted_value' => 0.0, 'won_value' => 0.0];
            }

            if ($stage === 'won') {
                $closedWonValue += $val;
                $wonCount++;
                $employeeStats[$empName]['won_value'] += $val;
                $branchStats[$branchKey]['won_value'] += $val;
            } elseif ($stage === 'lost') {
                $closedLostValue += $val;
                $lostCount++;
                $reason = $deal->lost_reason ?: 'Unspecified Reason';
                $lostReasonBreakdown[$reason] = ($lostReasonBreakdown[$reason] ?? 0) + 1;
            } else {
                // Open deal
                $totalOpenDealsCount++;
                $unweightedPipelineValue += $val;
                $weightedForecastValue += $weighted;

                if ($prob >= 80) {
                    $committedValue += $val;
                }
                if ($prob >= 50) {
                    $bestCaseValue += $val;
                }

                $employeeStats[$empName]['pipeline_value'] += $val;
                $employeeStats[$empName]['weighted_value'] += $weighted;
                $branchStats[$branchKey]['pipeline_value'] += $val;
                $branchStats[$branchKey]['weighted_value'] += $weighted;

                // Stage breakdown
                $stageName = $deal->pipelineStage?->name ?? ucfirst($stage);
                if (!isset($stageBreakdown[$stageName])) {
                    $stageBreakdown[$stageName] = ['count' => 0, 'value' => 0.0, 'weighted' => 0.0, 'probability' => $prob];
                }
                $stageBreakdown[$stageName]['count']++;
                $stageBreakdown[$stageName]['value'] += $val;
                $stageBreakdown[$stageName]['weighted'] += $weighted;

                // Monthly close date forecast bucket
                if ($deal->close_date) {
                    $monthKey = Carbon::parse($deal->close_date)->format('Y-m');
                    if (!isset($monthlyBuckets[$monthKey])) {
                        $monthlyBuckets[$monthKey] = ['unweighted' => 0.0, 'weighted' => 0.0, 'count' => 0];
                    }
                    $monthlyBuckets[$monthKey]['unweighted'] += $val;
                    $monthlyBuckets[$monthKey]['weighted'] += $weighted;
                    $monthlyBuckets[$monthKey]['count']++;
                }
            }
        }

        ksort($monthlyBuckets);

        // Historical Conversion Rate
        $totalDecided = $wonCount + $lostCount;
        $historicalWinRate = $totalDecided > 0 ? round(($wonCount / $totalDecided) * 100, 2) : 0.0;
        $avgDealSize = ($wonCount + $totalOpenDealsCount) > 0
            ? round(($closedWonValue + $unweightedPipelineValue) / ($wonCount + $totalOpenDealsCount), 2)
            : 0.0;

        return [
            'timeframe' => $timeframe,
            'unweighted_pipeline' => round($unweightedPipelineValue, 2),
            'weighted_forecast' => round($weightedForecastValue, 2),
            'committed_forecast' => round($committedValue, 2),
            'best_case_forecast' => round($bestCaseValue, 2),
            'assumptions_notice' => 'Forecast values represent statistical projections based on deal stage probability matrices and are not guaranteed confirmed revenue.',
            'summary' => [
                'open_deals_count' => $totalOpenDealsCount,
                'unweighted_pipeline_value' => round($unweightedPipelineValue, 2),
                'weighted_forecast_value' => round($weightedForecastValue, 2),
                'committed_value' => round($committedValue, 2),
                'best_case_value' => round($bestCaseValue, 2),
                'closed_won_value' => round($closedWonValue, 2),
                'closed_lost_value' => round($closedLostValue, 2),
                'historical_win_rate_percent' => $historicalWinRate,
                'average_deal_size' => $avgDealSize,
            ],
            'assumptions' => [
                'model' => 'Stage-Probability-Weighted Projections',
                'notice' => 'Forecast values represent statistical projections based on deal stage probability matrices and are not guaranteed confirmed revenue.',
                'confidence_levels' => [
                    'committed' => 'Deals in negotiation / proposal with >= 80% stage probability',
                    'best_case' => 'Deals advanced past qualification with >= 50% stage probability',
                    'pipeline' => 'All active unclosed opportunities',
                ],
            ],
            'monthly_projections' => $monthlyBuckets,
            'stage_breakdown' => $stageBreakdown,
            'employee_performance' => $employeeStats,
            'branch_performance' => $branchStats,
            'lost_reasons' => $lostReasonBreakdown,
        ];
    }
}
