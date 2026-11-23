<?php

namespace App\Domains\Voice\Services;

use App\Models\User;
use App\Domains\Voice\Models\CallQualityReview;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CallReportingService
{
    /**
     * Generate comprehensive Call Center and Telephony Analytics.
     */
    public function getCallCenterAnalytics(int $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = VoiceCall::where('tenant_id', $tenantId);

        if ($startDate) {
            $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
        }
        if ($endDate) {
            $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
        }

        $allCalls = $query->get();

        $totalCalls = $allCalls->count();
        $inboundCalls = $allCalls->where('direction', 'inbound')->count();
        $outboundCalls = $allCalls->where('direction', 'outbound')->count();
        $answeredCalls = $allCalls->whereIn('status', ['completed', 'in-progress'])->count();
        $missedCalls = $allCalls->whereIn('status', ['busy', 'no-answer', 'failed', 'cancelled'])->count();

        $totalDurationSec = (int) $allCalls->sum('duration_seconds');
        $totalDurationMin = (int) ceil($totalDurationSec / 60);
        $avgDurationSec = $answeredCalls > 0 ? (int) round($allCalls->where('status', 'completed')->avg('duration_seconds')) : 0;
        $totalCost = (float) $allCalls->sum('cost_amount');

        // Conversion & Disposition Metrics
        $dispositionCounts = $allCalls->groupBy('disposition')->map->count()->toArray();
        $qualifiedCount = $allCalls->where('qualification_result', 'qualified')->count();
        $callbackRequests = $allCalls->where('disposition', 'callback_requested')->count();
        $dndRequests = $allCalls->where('disposition', 'dnd_requested')->count();

        $conversionRate = $totalCalls > 0 ? round(($qualifiedCount / $totalCalls) * 100, 1) : 0.0;
        $answerRate = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0.0;

        // AI Voice Calling Performance
        $aiCalls = $allCalls->whereNotNull('voice_agent_id');
        $totalAiCalls = $aiCalls->count();
        $aiTransferredCount = $aiCalls->where('human_transferred', true)->count();
        $aiQualifiedCount = $aiCalls->where('qualification_result', 'qualified')->count();
        $aiHandoffRate = $totalAiCalls > 0 ? round(($aiTransferredCount / $totalAiCalls) * 100, 1) : 0.0;
        $aiQualificationRate = $totalAiCalls > 0 ? round(($aiQualifiedCount / $totalAiCalls) * 100, 1) : 0.0;

        // Agent Performance Breakdown
        $humanCalls = $allCalls->whereNotNull('assigned_user_id');
        $agentStats = [];
        $groupedByUser = $humanCalls->groupBy('assigned_user_id');

        foreach ($groupedByUser as $userId => $userCalls) {
            $user = User::find($userId);
            if (!$user) {
                continue;
            }
            $userAnswered = $userCalls->where('status', 'completed')->count();
            $userDurationMin = (int) ceil($userCalls->sum('duration_seconds') / 60);
            $userAvgScore = round($userCalls->whereNotNull('quality_score')->avg('quality_score') ?: 0, 1);

            $agentStats[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'total_calls' => $userCalls->count(),
                'answered_calls' => $userAnswered,
                'total_talk_minutes' => $userDurationMin,
                'average_quality_score' => $userAvgScore,
                'qualified_leads' => $userCalls->where('qualification_result', 'qualified')->count(),
            ];
        }

        // Provider Usage Breakdown
        $providerUsage = $allCalls->groupBy('provider')->map(function ($calls, $providerName) {
            return [
                'provider' => $providerName,
                'call_count' => $calls->count(),
                'duration_minutes' => (int) ceil($calls->sum('duration_seconds') / 60),
                'cost' => (float) $calls->sum('cost_amount'),
            ];
        })->values()->toArray();

        return [
            'summary' => [
                'total_calls' => $totalCalls,
                'inbound_calls' => $inboundCalls,
                'outbound_calls' => $outboundCalls,
                'answered_calls' => $answeredCalls,
                'missed_calls' => $missedCalls,
                'answer_rate_pct' => $answerRate,
                'total_duration_minutes' => $totalDurationMin,
                'avg_call_duration_seconds' => $avgDurationSec,
                'total_cost_inr' => $totalCost,
                'conversion_rate_pct' => $conversionRate,
                'qualified_leads' => $qualifiedCount,
                'callback_requests' => $callbackRequests,
                'dnd_suppressions' => $dndRequests,
            ],
            'ai_performance' => [
                'total_ai_calls' => $totalAiCalls,
                'ai_qualified_leads' => $aiQualifiedCount,
                'ai_qualification_rate_pct' => $aiQualificationRate,
                'ai_human_transfers' => $aiTransferredCount,
                'ai_handoff_rate_pct' => $aiHandoffRate,
            ],
            'dispositions' => $dispositionCounts,
            'agent_performance' => $agentStats,
            'provider_usage' => $providerUsage,
        ];
    }
}
