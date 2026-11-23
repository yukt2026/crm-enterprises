<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Models\AttributionTouchpoint;
use App\Domains\Analytics\Models\TrackingEvent;
use App\Domains\Analytics\Models\TrackingSetting;
use App\Domains\Analytics\Services\GoogleAnalytics4Service;
use App\Domains\Analytics\Services\TrackingService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingAdminController extends Controller
{
    public function __construct(
        protected TrackingService $trackingService,
        protected GoogleAnalytics4Service $ga4Service
    ) {}

    protected function getTenantId(): int
    {
        return TenantContext::getTenantId() ?? 1;
    }

    /**
     * Get tenant tracking settings
     */
    public function getSettings(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $setting = TrackingSetting::forTenant($tenantId);

        return response()->json([
            'success' => true,
            'data' => $setting,
        ]);
    }

    /**
     * Update tenant tracking settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $setting = TrackingSetting::forTenant($tenantId);

        $validated = $request->validate([
            'is_enabled' => 'sometimes|boolean',
            'require_consent' => 'sometimes|boolean',
            'allowed_domains' => 'sometimes|array',
            'allowed_domains.*' => 'string',
            'data_retention_days' => 'sometimes|integer|min:7|max:730',
            'enabled_events' => 'sometimes|array',
            'anonymize_ip' => 'sometimes|boolean',
            'bot_filtering' => 'sometimes|boolean',
        ]);

        $setting->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tracking settings updated successfully.',
            'data' => $setting->fresh(),
        ]);
    }

    /**
     * Get paginated tracking events
     */
    public function getEvents(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = TrackingEvent::where('tenant_id', $tenantId)->with(['lead:id,name,email,company_name']);

        if ($request->filled('event_name')) {
            $query->where('event_name', $request->query('event_name'));
        }

        if ($request->filled('utm_source')) {
            $query->where('utm_source', $request->query('utm_source'));
        }

        if ($request->filled('session_id')) {
            $query->where('session_id', $request->query('session_id'));
        }

        if ($request->boolean('with_leads_only')) {
            $query->whereNotNull('lead_id');
        }

        $events = $query->orderByDesc('created_at')->paginate($request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get multi-touch attribution report linking tracking events to leads
     */
    public function getAttribution(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $fromDate = $request->query('from_date', Carbon::now()->subDays(30)->toDateString());
        $toDate = $request->query('to_date', Carbon::now()->toDateString());

        // 1. Channel touchpoint breakdown
        $channelStats = AttributionTouchpoint::where('tenant_id', $tenantId)
            ->whereBetween('touchpoint_timestamp', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()])
            ->select('channel', 'utm_source', 'touchpoint_type', DB::raw('count(*) as touchpoints_count'))
            ->groupBy('channel', 'utm_source', 'touchpoint_type')
            ->orderByDesc('touchpoints_count')
            ->get();

        // 2. Leads with first and last touch attribution
        $leadsWithAttribution = TrackingEvent::where('tenant_id', $tenantId)
            ->whereNotNull('lead_id')
            ->select('lead_id', 'session_id', 'utm_source', 'utm_campaign', 'referrer', 'created_at')
            ->with('lead:id,name,email,status')
            ->orderBy('created_at')
            ->get()
            ->groupBy('lead_id')
            ->map(function ($events, $leadId) {
                $first = $events->first();
                $last = $events->last();
                return [
                    'lead_id' => (int) $leadId,
                    'lead_name' => $first->lead ? $first->lead->name : 'Lead #' . $leadId,
                    'lead_email' => $first->lead?->email,
                    'total_touches' => $events->count(),
                    'first_touch' => [
                        'source' => $first->utm_source ?? 'direct',
                        'campaign' => $first->utm_campaign ?? null,
                        'referrer' => $first->referrer,
                        'timestamp' => $first->created_at->toIso8601String(),
                    ],
                    'last_touch' => [
                        'source' => $last->utm_source ?? 'direct',
                        'campaign' => $last->utm_campaign ?? null,
                        'referrer' => $last->referrer,
                        'timestamp' => $last->created_at->toIso8601String(),
                    ],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'date_range' => ['from_date' => $fromDate, 'to_date' => $toDate],
                'channel_summary' => $channelStats,
                'lead_attribution_journeys' => $leadsWithAttribution,
            ],
        ]);
    }

    /**
     * Get SDK and tracking integration diagnostics
     */
    public function getDiagnostics(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $diagnostics = $this->trackingService->getDiagnostics($tenantId);

        return response()->json([
            'success' => true,
            'data' => $diagnostics,
        ]);
    }

    /**
     * Connect GA4 Property
     */
    public function connectGa4(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'property_id' => 'required|string',
            'measurement_id' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'oauth_credentials' => 'nullable|array',
            'display_name' => 'nullable|string',
        ]);

        $setting = $this->ga4Service->connectProperty($tenantId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Google Analytics 4 property connected successfully.',
            'data' => $setting,
        ]);
    }

    /**
     * Disconnect GA4 Property
     */
    public function disconnectGa4(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $setting = $this->ga4Service->disconnectProperty($tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Google Analytics 4 property disconnected.',
            'data' => $setting,
        ]);
    }

    /**
     * List GA4 Properties
     */
    public function getGa4Properties(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $properties = $this->ga4Service->getAvailableProperties($tenantId);

        return response()->json([
            'success' => true,
            'data' => $properties,
        ]);
    }

    /**
     * Fetch GA4 Aggregated Report
     */
    public function getGa4Report(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $report = $this->ga4Service->fetchReport($tenantId, $request->all());

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
