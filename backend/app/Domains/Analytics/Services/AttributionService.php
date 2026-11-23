<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AttributionTouchpoint;
use App\Domains\CRM\Models\Deal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttributionService
{
    /**
     * Record a customer marketing/sales touchpoint
     */
    public function recordTouchpoint(int $tenantId, array $data): AttributionTouchpoint
    {
        return AttributionTouchpoint::create([
            'tenant_id' => $tenantId,
            'lead_id' => $data['lead_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'deal_id' => $data['deal_id'] ?? null,
            'touchpoint_type' => $data['touchpoint_type'] ?? 'page_view',
            'channel' => $data['channel'] ?? 'direct',
            'campaign_id' => $data['campaign_id'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'landing_page_id' => $data['landing_page_id'] ?? null,
            'form_id' => $data['form_id'] ?? null,
            'touchpoint_timestamp' => $data['touchpoint_timestamp'] ?? Carbon::now(),
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Compare Attribution Models: First-Touch vs Last-Touch vs Linear vs Position-Based (U-Shaped)
     */
    public function compareAttributionModels(int $tenantId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(60)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();

        // Retrieve won deals within period
        $wonDeals = Deal::where('tenant_id', $tenantId)
            ->where('stage', 'won')
            ->whereBetween('won_at', [$fromDate, $toDate])
            ->get();

        // Also include deals created within period if won_at is null but stage is won
        if ($wonDeals->isEmpty()) {
            $wonDeals = Deal::where('tenant_id', $tenantId)
                ->where('stage', 'won')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->get();
        }

        // Initialize channel aggregations
        $channelStats = [];

        foreach ($wonDeals as $deal) {
            $dealValue = (float) $deal->value;
            if ($dealValue <= 0) {
                continue;
            }

            // Retrieve all touchpoints associated with this deal or its lead
            $touchpoints = AttributionTouchpoint::where('tenant_id', $tenantId)
                ->where(function ($query) use ($deal) {
                    $query->where('deal_id', $deal->id);
                    if ($deal->lead_id) {
                        $query->orWhere('lead_id', $deal->lead_id);
                    }
                })
                ->orderBy('touchpoint_timestamp', 'asc')
                ->get();

            // If no specific touchpoints recorded, synthesize one from deal metadata
            if ($touchpoints->isEmpty()) {
                $channel = $deal->utm_source ?: ($deal->source ?: 'Direct');
                $this->addCredit($channelStats, $channel, 'first_touch', $dealValue);
                $this->addCredit($channelStats, $channel, 'last_touch', $dealValue);
                $this->addCredit($channelStats, $channel, 'linear', $dealValue);
                $this->addCredit($channelStats, $channel, 'u_shaped', $dealValue);
                continue;
            }

            $count = $touchpoints->count();

            // 1. First-Touch Model (100% to first touchpoint)
            $firstTouch = $touchpoints->first();
            $firstChannel = $this->resolveChannelName($firstTouch);
            $this->addCredit($channelStats, $firstChannel, 'first_touch', $dealValue);

            // 2. Last-Touch Model (100% to last touchpoint)
            $lastTouch = $touchpoints->last();
            $lastChannel = $this->resolveChannelName($lastTouch);
            $this->addCredit($channelStats, $lastChannel, 'last_touch', $dealValue);

            // 3. Linear Model (Equal 1/N credit to each touchpoint)
            $linearCredit = $dealValue / $count;
            foreach ($touchpoints as $tp) {
                $ch = $this->resolveChannelName($tp);
                $this->addCredit($channelStats, $ch, 'linear', $linearCredit);
            }

            // 4. Position-Based (U-Shaped) Model (40% first, 40% last, 20% shared among middle)
            if ($count === 1) {
                $this->addCredit($channelStats, $firstChannel, 'u_shaped', $dealValue);
            } elseif ($count === 2) {
                $this->addCredit($channelStats, $firstChannel, 'u_shaped', $dealValue * 0.5);
                $this->addCredit($channelStats, $lastChannel, 'u_shaped', $dealValue * 0.5);
            } else {
                $this->addCredit($channelStats, $firstChannel, 'u_shaped', $dealValue * 0.4);
                $this->addCredit($channelStats, $lastChannel, 'u_shaped', $dealValue * 0.4);
                $middleCount = $count - 2;
                $middleWeight = ($dealValue * 0.2) / $middleCount;
                for ($i = 1; $i < $count - 1; $i++) {
                    $ch = $this->resolveChannelName($touchpoints[$i]);
                    $this->addCredit($channelStats, $ch, 'u_shaped', $middleWeight);
                }
            }
        }

        // Format results as sorted list
        $comparison = [];
        $totalAttributedRevenue = (float) $wonDeals->sum('value');

        foreach ($channelStats as $channel => $models) {
            $comparison[] = [
                'channel' => $channel,
                'first_touch_revenue' => round($models['first_touch'] ?? 0, 2),
                'last_touch_revenue' => round($models['last_touch'] ?? 0, 2),
                'linear_revenue' => round($models['linear'] ?? 0, 2),
                'u_shaped_revenue' => round($models['u_shaped'] ?? 0, 2),
            ];
        }

        usort($comparison, fn($a, $b) => $b['linear_revenue'] <=> $a['linear_revenue']);

        return [
            'total_won_deals' => $wonDeals->count(),
            'total_won_revenue' => round($totalAttributedRevenue, 2),
            'attribution_model_comparison' => $comparison,
            'models_available' => [
                'first_touch' => '100% credit given to the initial discovery touchpoint',
                'last_touch' => '100% credit given to the final touchpoint before deal close',
                'linear' => 'Equal 1/N credit shared across all touchpoints in the customer journey',
                'u_shaped' => '40% credit to first discovery, 40% to conversion, 20% divided among middle touches',
            ],
            'attribution_notice' => 'Attribution models represent algorithmic estimates to guide marketing and sales investments and do not signify absolute deterministic causation.',
        ];
    }

    protected function resolveChannelName(AttributionTouchpoint $tp): string
    {
        if (!empty($tp->utm_source)) {
            return ucfirst($tp->utm_source);
        }
        if (!empty($tp->channel)) {
            return ucfirst($tp->channel);
        }
        return 'Organic / Direct';
    }

    protected function addCredit(array &$channelStats, string $channel, string $model, float $amount): void
    {
        if (!isset($channelStats[$channel])) {
            $channelStats[$channel] = [
                'first_touch' => 0.0,
                'last_touch' => 0.0,
                'linear' => 0.0,
                'u_shaped' => 0.0,
            ];
        }
        $channelStats[$channel][$model] += $amount;
    }
}
