<?php

namespace App\Domains\CRM\Services;

use App\Domains\LeadAcquisition\Models\Lead;
use Carbon\Carbon;

class LeadScoringService
{
    /**
     * Calculate and return dynamic score with transparent breakdown
     */
    public function calculateScore(Lead $lead): array
    {
        $breakdown = [];
        $totalScore = 0;

        // 1. Contact Completeness (Max 15 pts)
        $completenessPoints = 0;
        if (!empty($lead->phone)) $completenessPoints += 5;
        if (!empty($lead->email)) $completenessPoints += 5;
        if (!empty($lead->company_name) || !empty($lead->company_id)) $completenessPoints += 5;
        $breakdown['contact_completeness'] = [
            'points' => $completenessPoints,
            'max' => 15,
            'details' => 'Phone, email, and corporate organization captured',
        ];
        $totalScore += $completenessPoints;

        // 2. Budget Tier Points (Max 25 pts)
        $budgetPoints = 0;
        $val = (float) $lead->estimated_value;
        if ($val >= 50000) {
            $budgetPoints = 25;
        } elseif ($val >= 10000) {
            $budgetPoints = 18;
        } elseif ($val >= 2500) {
            $budgetPoints = 10;
        } elseif ($val > 0) {
            $budgetPoints = 5;
        }
        $breakdown['budget_tier'] = [
            'points' => $budgetPoints,
            'max' => 25,
            'details' => "Estimated value of \${$val}",
        ];
        $totalScore += $budgetPoints;

        // 3. Purchase Timeline Urgency (Max 20 pts)
        $timelinePoints = 0;
        if ($lead->expected_purchase_date) {
            $daysUntil = Carbon::now()->diffInDays(Carbon::parse($lead->expected_purchase_date), false);
            if ($daysUntil >= 0 && $daysUntil <= 30) {
                $timelinePoints = 20;
            } elseif ($daysUntil > 30 && $daysUntil <= 90) {
                $timelinePoints = 12;
            } elseif ($daysUntil > 90) {
                $timelinePoints = 5;
            }
        }
        $breakdown['timeline_urgency'] = [
            'points' => $timelinePoints,
            'max' => 20,
            'details' => $lead->expected_purchase_date ? "Expected target: {$lead->expected_purchase_date->format('Y-m-d')}" : 'Target date not specified',
        ];
        $totalScore += $timelinePoints;

        // 4. Source Quality (Max 20 pts)
        $sourcePoints = 5;
        $src = strtolower($lead->source ?? '');
        if (str_contains($src, 'referral') || str_contains($src, 'partner')) {
            $sourcePoints = 20;
        } elseif (str_contains($src, 'inbound') || str_contains($src, 'direct')) {
            $sourcePoints = 15;
        } elseif (str_contains($src, 'organic') || str_contains($src, 'google')) {
            $sourcePoints = 12;
        } elseif (str_contains($src, 'paid') || str_contains($src, 'ad') || str_contains($src, 'meta')) {
            $sourcePoints = 10;
        }
        $breakdown['source_channel'] = [
            'points' => $sourcePoints,
            'max' => 20,
            'details' => "Channel attribution: " . ($lead->source ?: 'Direct Inbound'),
        ];
        $totalScore += $sourcePoints;

        // 5. Engagement Activities Count (Max 20 pts)
        $activityCount = $lead->activities()->count();
        $activityPoints = min($activityCount * 5, 20);
        $breakdown['engagement_activities'] = [
            'points' => $activityPoints,
            'max' => 20,
            'details' => "{$activityCount} recorded touchpoints/interactions",
        ];
        $totalScore += $activityPoints;

        $finalScore = min($totalScore, 100);

        // Qualification Level Determination
        $qualification = 'unqualified';
        if ($finalScore >= 60) {
            $qualification = 'qualified';
        } elseif ($finalScore >= 30) {
            $qualification = 'in_qualification';
        }

        return [
            'score' => $finalScore,
            'score_breakdown' => $breakdown,
            'qualification_status' => $qualification,
        ];
    }

    /**
     * Recalculate and persist score on lead
     */
    public function updateLeadScore(Lead $lead): Lead
    {
        $result = $this->calculateScore($lead);
        $lead->score = $result['score'];
        $lead->score_breakdown = $result['score_breakdown'];
        
        // Only update qualification status if not manually disqualified
        if ($lead->qualification_status !== 'disqualified') {
            $lead->qualification_status = $result['qualification_status'];
        }

        $lead->save();
        return $lead;
    }
}
