<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AttributionTouchpoint;
use App\Domains\Analytics\Models\TrackingEvent;
use App\Domains\Analytics\Models\TrackingSetting;
use App\Domains\LeadAcquisition\Models\Lead;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingService
{
    /**
     * Ingest an incoming tracking event from SDK / Landing Page
     */
    public function ingestEvent(array $payload, ?string $clientIp = null, ?string $userAgent = null): array
    {
        $trackingKey = $payload['tracking_key'] ?? null;
        if (!$trackingKey) {
            throw ValidationException::withMessages(['tracking_key' => 'Tracking key is required.']);
        }

        // 1. Resolve Tenant Settings
        $setting = TrackingSetting::withoutGlobalScopes()
            ->where('tracking_key', $trackingKey)
            ->first();

        if (!$setting || !$setting->is_enabled) {
            throw ValidationException::withMessages(['tracking_key' => 'Tracking key is invalid or tracking is disabled.']);
        }

        $tenantId = $setting->tenant_id;

        // 2. Domain Whitelist Verification
        $pageUrl = $payload['page_url'] ?? '';
        if ($pageUrl && !empty($setting->allowed_domains) && !in_array('*', $setting->allowed_domains)) {
            $host = parse_url($pageUrl, PHP_URL_HOST);
            if ($host && !in_array($host, $setting->allowed_domains)) {
                return [
                    'status' => 'ignored',
                    'reason' => 'Domain not in allowed whitelist.',
                ];
            }
        }

        // 3. Bot & Spam Filtering
        $isBot = false;
        $ua = $userAgent ?? ($payload['user_agent'] ?? '');
        if ($setting->bot_filtering && $ua) {
            $botPatterns = '/bot|crawler|spider|crawling|googlebot|bingbot|yandex|headless|postman|curl/i';
            if (preg_match($botPatterns, $ua)) {
                $isBot = true;
                // Discard bots silently or mark as bot without attribution
                return [
                    'status' => 'ignored',
                    'reason' => 'Bot or automated crawler traffic detected.',
                ];
            }
        }

        // 4. Consent-Aware Behavior
        $consentGiven = isset($payload['consent_given']) ? (bool) $payload['consent_given'] : true;
        if ($setting->require_consent && !$consentGiven) {
            // Strictly drop or limit non-essential tracking if consent is required and denied
            return [
                'status' => 'ignored',
                'reason' => 'Tracking rejected: Visitor has not granted analytical consent.',
            ];
        }

        // 5. Event Configuration Check
        $eventName = $payload['event_name'] ?? 'page_view';
        $enabledEvents = $setting->enabled_events ?? TrackingSetting::DEFAULT_EVENTS;
        if (!in_array($eventName, $enabledEvents)) {
            return [
                'status' => 'ignored',
                'reason' => "Event '{$eventName}' is not enabled for this tenant.",
            ];
        }

        // 6. Duplicate Event Prevention (Deduplication)
        $sessionId = $payload['session_id'] ?? ('sess_' . md5($clientIp . $ua . date('Y-m-d-H')));
        $eventId = $payload['event_id'] ?? null;

        if ($eventId) {
            $existing = TrackingEvent::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('session_id', $sessionId)
                ->where('event_id', $eventId)
                ->first();

            if ($existing) {
                return [
                    'status' => 'duplicate',
                    'event_id' => $existing->id,
                    'message' => 'Duplicate event discarded.',
                ];
            }
        }

        // 7. IP Anonymization (Zero invasive fingerprinting / privacy preservation)
        $ipAnonymized = null;
        if ($clientIp) {
            if ($setting->anonymize_ip) {
                // Anonymize IPv4 (mask last octet) or IPv6
                if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $parts = explode('.', $clientIp);
                    $parts[3] = '0';
                    $ipAnonymized = implode('.', $parts);
                } else {
                    $ipAnonymized = substr(hash('sha256', $clientIp), 0, 16);
                }
            } else {
                $ipAnonymized = $clientIp;
            }
        }

        // 8. Lead Association if voluntarily provided
        $leadId = $payload['lead_id'] ?? null;
        $customerEmail = $payload['email'] ?? ($payload['metadata']['email'] ?? null);
        $customerPhone = $payload['phone'] ?? ($payload['metadata']['phone'] ?? null);

        if (!$leadId && ($customerEmail || $customerPhone)) {
            // Lookup existing lead in this tenant
            $lead = Lead::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($customerEmail, $customerPhone) {
                    if ($customerEmail) {
                        $q->where('email', $customerEmail);
                    }
                    if ($customerPhone) {
                        $q->orWhere('phone', $customerPhone);
                    }
                })
                ->first();

            if ($lead) {
                $leadId = $lead->id;
            }
        }

        // 9. Persist Tracking Event
        $event = TrackingEvent::create([
            'tenant_id' => $tenantId,
            'tracking_key' => $trackingKey,
            'session_id' => $sessionId,
            'event_name' => $eventName,
            'event_id' => $eventId,
            'page_url' => $pageUrl,
            'page_title' => $payload['page_title'] ?? null,
            'referrer' => $payload['referrer'] ?? null,
            'utm_source' => $payload['utm_source'] ?? null,
            'utm_medium' => $payload['utm_medium'] ?? null,
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'utm_term' => $payload['utm_term'] ?? null,
            'utm_content' => $payload['utm_content'] ?? null,
            'consent_given' => $consentGiven,
            'lead_id' => $leadId,
            'metadata' => $payload['metadata'] ?? [],
            'ip_anonymized' => $ipAnonymized,
            'user_agent' => substr($ua, 0, 255),
            'is_bot' => $isBot,
        ]);

        // 10. Multi-Touch Attribution Connection
        // If an authorized lead conversion occurs, backfill earlier session events and record touchpoint
        if ($leadId) {
            $this->associateSessionWithLead($tenantId, $sessionId, $leadId);

            // Record attribution touchpoint
            AttributionTouchpoint::create([
                'tenant_id' => $tenantId,
                'lead_id' => $leadId,
                'touchpoint_type' => $eventName === 'form_submission' ? 'form_submit' : ($eventName === 'conversion_event' ? 'deal_created' : 'landing_page_visit'),
                'channel' => $payload['utm_source'] ?? (!empty($payload['referrer']) ? 'referral' : 'direct'),
                'utm_source' => $payload['utm_source'] ?? null,
                'utm_medium' => $payload['utm_medium'] ?? null,
                'utm_campaign' => $payload['utm_campaign'] ?? null,
                'utm_term' => $payload['utm_term'] ?? null,
                'utm_content' => $payload['utm_content'] ?? null,
                'touchpoint_timestamp' => Carbon::now(),
                'metadata' => array_merge($payload['metadata'] ?? [], [
                    'session_id' => $sessionId,
                    'page_url' => $pageUrl,
                    'referrer' => $payload['referrer'] ?? null,
                ]),
            ]);
        }

        return [
            'status' => 'success',
            'event_id' => $event->id,
            'session_id' => $sessionId,
            'lead_associated' => (bool) $leadId,
        ];
    }

    /**
     * Backfill past anonymous session events when visitor identifies / submits a form
     */
    public function associateSessionWithLead(int $tenantId, string $sessionId, int $leadId): int
    {
        return TrackingEvent::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('session_id', $sessionId)
            ->whereNull('lead_id')
            ->update(['lead_id' => $leadId]);
    }

    /**
     * Prune expired events based on tenant retention policy
     */
    public function pruneExpiredEvents(int $tenantId): int
    {
        $setting = TrackingSetting::forTenant($tenantId);
        $days = max(7, (int) $setting->data_retention_days);
        $cutoff = Carbon::now()->subDays($days);

        return TrackingEvent::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    /**
     * Diagnostic Health Information for SDK & Integrations
     */
    public function getDiagnostics(int $tenantId): array
    {
        $setting = TrackingSetting::forTenant($tenantId);
        $past24h = Carbon::now()->subHours(24);

        $events24h = TrackingEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $past24h)
            ->count();

        $activeSessions = TrackingEvent::where('tenant_id', $tenantId)
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->distinct('session_id')
            ->count('session_id');

        $lastEvent = TrackingEvent::where('tenant_id', $tenantId)
            ->latest('created_at')
            ->first();

        $topSources = TrackingEvent::where('tenant_id', $tenantId)
            ->whereNotNull('utm_source')
            ->select('utm_source', DB::raw('count(*) as count'))
            ->groupBy('utm_source')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $eventsByType = TrackingEvent::where('tenant_id', $tenantId)
            ->select('event_name', DB::raw('count(*) as count'))
            ->groupBy('event_name')
            ->get()
            ->pluck('count', 'event_name');

        return [
            'tracking_key' => $setting->tracking_key,
            'is_enabled' => $setting->is_enabled,
            'require_consent' => $setting->require_consent,
            'allowed_domains' => $setting->allowed_domains,
            'data_retention_days' => $setting->data_retention_days,
            'events_past_24h' => $events24h,
            'active_sessions_now' => $activeSessions,
            'last_event_at' => $lastEvent?->created_at?->toIso8601String(),
            'last_event_type' => $lastEvent?->event_name,
            'events_by_type' => $eventsByType,
            'top_utm_sources' => $topSources,
            'ga4_connected' => (bool) $setting->ga4_connected_at,
            'ga4_property_id' => $setting->ga4_property_id,
            'ga4_measurement_id' => $setting->ga4_measurement_id,
        ];
    }
}
