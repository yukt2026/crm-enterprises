<?php

namespace App\Domains\Marketing\Services;

use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\CampaignRecipient;
use App\Domains\Marketing\Models\MarketingCampaign;
use Illuminate\Support\Facades\DB;

class MarketingAnalyticsService
{
    /**
     * Compute and aggregate detailed campaign analytics and attribution.
     */
    public function getCampaignAnalytics(MarketingCampaign $campaign): array
    {
        $tenantId = $campaign->tenant_id;
        $campaignId = $campaign->id;

        // 1. Provider-Reported Metrics
        $totalRecipients = $campaign->total_recipients_count;
        $sentCount = $campaign->sent_count;
        $deliveredCount = $campaign->delivered_count;
        $readCount = $campaign->read_count;
        $opensCount = $campaign->opens_count;
        $clicksCount = $campaign->clicks_count;
        $repliedCount = $campaign->replied_count;
        $failedCount = $campaign->failed_count;

        // Rates
        $deliveryRate = $sentCount > 0 ? round(($deliveredCount / $sentCount) * 100, 2) : 0.0;
        $openRate = $deliveredCount > 0 ? round(($opensCount / $deliveredCount) * 100, 2) : 0.0;
        $clickRate = $deliveredCount > 0 ? round(($clicksCount / $deliveredCount) * 100, 2) : 0.0;
        $replyRate = $deliveredCount > 0 ? round(($repliedCount / $deliveredCount) * 100, 2) : 0.0;

        // 2. CRM Attributed Business Results
        // Find leads created or attributed with this campaign
        $leadsCount = Lead::where('tenant_id', $tenantId)
            ->where(function ($q) use ($campaign) {
                $q->where('campaign_reference', (string) $campaign->id)
                  ->orWhere('campaign_reference', $campaign->name)
                  ->orWhere('utm_campaign', $campaign->name);
            })
            ->count();

        // Also check recipients converted
        $recipientsCount = CampaignRecipient::where('tenant_id', $tenantId)
            ->where('campaign_id', $campaignId)
            ->where('status', 'converted')
            ->count();

        $attributedLeads = max($leadsCount, $recipientsCount);

        // Find deals won attributed to this campaign
        $attributedDealsQuery = Deal::where('tenant_id', $tenantId)
            ->where(function ($q) use ($campaign) {
                $q->where('utm_campaign', $campaign->name)
                  ->orWhere('campaign_id', $campaign->id);
            });

        $wonDeals = (clone $attributedDealsQuery)->where('stage', 'won')->get();
        $conversionsCount = $wonDeals->count() > 0 ? $wonDeals->count() : $campaign->conversions_count;
        $attributedRevenue = $wonDeals->sum('value') > 0 ? (float) $wonDeals->sum('value') : (float) $campaign->attributed_revenue;

        // Financials & ROI
        $spend = (float) $campaign->spend_amount;
        $budget = (float) $campaign->budget;

        $cpl = $attributedLeads > 0 ? round($spend / $attributedLeads, 2) : ($spend > 0 ? $spend : 0.0);
        $cpa = $conversionsCount > 0 ? round($spend / $conversionsCount, 2) : 0.0;
        $roi = $spend > 0 ? round((($attributedRevenue - $spend) / $spend) * 100, 2) : 0.0;
        $roas = $spend > 0 ? round(($attributedRevenue / $spend) * 100, 2) : 0.0;

        // Auto-persist calculated metrics back to campaign
        $campaign->update([
            'cost_per_lead' => $cpl,
            'cost_per_acquisition' => $cpa,
            'attributed_revenue' => $attributedRevenue,
            'conversions_count' => $conversionsCount,
        ]);

        return [
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'channel' => $campaign->channel,
                'type' => $campaign->type,
                'objective' => $campaign->objective,
                'status' => $campaign->status,
                'approval_status' => $campaign->approval_status,
                'budget' => $budget,
                'spend' => $spend,
            ],
            'provider_metrics' => [
                'total_recipients' => $totalRecipients,
                'sent' => $sentCount,
                'delivered' => $deliveredCount,
                'delivery_rate_percent' => $deliveryRate,
                'opens' => $opensCount,
                'open_rate_percent' => $openRate,
                'clicks' => $clicksCount,
                'click_rate_percent' => $clickRate,
                'replies' => $repliedCount,
                'reply_rate_percent' => $replyRate,
                'failed' => $failedCount,
            ],
            'crm_attribution' => [
                'attributed_leads' => $attributedLeads,
                'conversions' => $conversionsCount,
                'attributed_revenue' => $attributedRevenue,
                'cost_per_lead' => $cpl,
                'cost_per_acquisition' => $cpa,
                'roi_percent' => $roi,
                'roas_percent' => $roas,
            ],
        ];
    }

    /**
     * Get aggregate marketing dashboard stats for the tenant.
     */
    public function getDashboardSummary(int $tenantId): array
    {
        $campaigns = MarketingCampaign::where('tenant_id', $tenantId)->get();

        $totalCampaigns = $campaigns->count();
        $activeCampaigns = $campaigns->where('status', 'running')->count();
        $totalSpend = $campaigns->sum('spend_amount');
        $totalBudget = $campaigns->sum('budget');
        $totalAttributedRevenue = $campaigns->sum('attributed_revenue');
        $totalSent = $campaigns->sum('sent_count');
        $totalDelivered = $campaigns->sum('delivered_count');
        $totalConversions = $campaigns->sum('conversions_count');

        $overallRoi = $totalSpend > 0 ? round((($totalAttributedRevenue - $totalSpend) / $totalSpend) * 100, 2) : 0.0;

        return [
            'total_campaigns' => $totalCampaigns,
            'active_campaigns' => $activeCampaigns,
            'total_budget' => round((float) $totalBudget, 2),
            'total_spend' => round((float) $totalSpend, 2),
            'total_attributed_revenue' => round((float) $totalAttributedRevenue, 2),
            'overall_roi_percent' => $overallRoi,
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_conversions' => $totalConversions,
        ];
    }
}
