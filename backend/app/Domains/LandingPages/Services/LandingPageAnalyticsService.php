<?php

namespace App\Domains\LandingPages\Services;

use App\Domains\LandingPages\Models\LandingPage;
use App\Domains\LandingPages\Models\LandingPageAnalytics;
use Illuminate\Support\Facades\DB;

class LandingPageAnalyticsService
{
    /**
     * Track a lawful telemetry event (page view, CTA click, form impression).
     */
    public function recordEvent(
        LandingPage $page,
        string $eventType,
        ?string $deviceType = null,
        ?string $referrer = null,
        array $utmData = [],
        ?array $meta = null
    ): LandingPageAnalytics {
        if ($eventType === 'page_view') {
            $page->increment('view_count');
        }

        return LandingPageAnalytics::create([
            'tenant_id' => $page->tenant_id,
            'landing_page_id' => $page->id,
            'event_type' => $eventType,
            'device_type' => $deviceType ?? 'desktop',
            'referrer' => $referrer,
            'utm_source' => $utmData['utm_source'] ?? null,
            'utm_medium' => $utmData['utm_medium'] ?? null,
            'utm_campaign' => $utmData['utm_campaign'] ?? null,
            'meta' => $meta,
        ]);
    }

    /**
     * Compute aggregated performance analytics for a landing page.
     */
    public function getMetrics(LandingPage $page, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = LandingPageAnalytics::where('tenant_id', $page->tenant_id)
            ->where('landing_page_id', $page->id);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totalViews = (clone $query)->where('event_type', 'page_view')->count();
        $totalCtaClicks = (clone $query)->where('event_type', 'cta_click')->count();
        $totalFormViews = (clone $query)->where('event_type', 'form_view')->count();
        $totalSubmissions = $page->submission_count;

        $conversionRate = $totalViews > 0
            ? round(($totalSubmissions / $totalViews) * 100, 2)
            : 0.0;

        // Device breakdown
        $devices = (clone $query)
            ->where('event_type', 'page_view')
            ->select('device_type', DB::raw('count(*) as count'))
            ->groupBy('device_type')
            ->pluck('count', 'device_type')
            ->toArray();

        // Top UTM sources
        $utmSources = (clone $query)
            ->whereNotNull('utm_source')
            ->select('utm_source', DB::raw('count(*) as count'))
            ->groupBy('utm_source')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'utm_source')
            ->toArray();

        return [
            'views' => $totalViews ?: $page->view_count,
            'cta_clicks' => $totalCtaClicks,
            'form_views' => $totalFormViews,
            'submissions' => $totalSubmissions,
            'conversion_rate' => $conversionRate,
            'device_breakdown' => [
                'desktop' => $devices['desktop'] ?? 0,
                'mobile' => $devices['mobile'] ?? 0,
                'tablet' => $devices['tablet'] ?? 0,
            ],
            'top_utm_sources' => $utmSources,
        ];
    }
}
