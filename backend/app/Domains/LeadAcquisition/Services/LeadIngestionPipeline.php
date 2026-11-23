<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Services\DuplicateDetectionService;
use App\Domains\CRM\Services\LeadDistributionService;
use App\Domains\CRM\Services\LeadScoringService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFieldMapping;
use App\Domains\LeadAcquisition\Models\LeadIngestionEvent;
use App\Domains\LeadAcquisition\Models\LeadSource;
use App\Domains\LeadAcquisition\Services\Adapters\GenericWebhookSourceAdapter;
use App\Domains\LeadAcquisition\Services\Adapters\GoogleAdsSourceAdapter;
use App\Domains\LeadAcquisition\Services\Adapters\MetaAdsSourceAdapter;
use App\Domains\LeadAcquisition\Services\Adapters\SourceAdapterInterface;
use App\Domains\LeadAcquisition\Services\Adapters\WebsiteFormSourceAdapter;
use App\Domains\LeadAcquisition\Services\Adapters\WhatsAppEnquirySourceAdapter;
use App\Domains\Communications\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadIngestionPipeline
{
    public function __construct(
        protected LeadScoringService $scoringService,
        protected LeadDistributionService $distributionService,
        protected DuplicateDetectionService $duplicateService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Resolve the source adapter instance for a given provider.
     */
    public function resolveAdapter(string $provider): SourceAdapterInterface
    {
        return match (strtolower($provider)) {
            'google_ads', 'google', 'youtube' => new GoogleAdsSourceAdapter(),
            'meta_ads', 'meta', 'facebook', 'instagram' => new MetaAdsSourceAdapter(),
            'website_form', 'website', 'hosted_form', 'public_api' => new WebsiteFormSourceAdapter(),
            'whatsapp' => new WhatsAppEnquirySourceAdapter(),
            default => new GenericWebhookSourceAdapter(),
        };
    }

    /**
     * Ingest an inbound lead event through the 15-step pipeline.
     */
    public function ingest(
        LeadSource $source,
        array $payload,
        array $headers = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?LeadIngestionEvent $existingEvent = null
    ): array {
        $adapter = $this->resolveAdapter($source->provider);
        $workspaceId = $source->workspace_id;
        \App\Domains\Tenancy\Services\TenantContext::setTenantId($workspaceId);

        // 1 & 2. Verify Authentication / Signature
        if (!$adapter->verifyAuthentication($source, $payload, $headers)) {
            Log::warning("Unauthorized lead event rejected for source [{$source->id}] provider [{$source->provider}]");
            return [
                'success' => false,
                'status' => 'unauthorized',
                'error' => 'Invalid signature, webhook secret, or authentication token',
            ];
        }

        // 3. Resolve Event ID for Idempotency
        $eventId = $adapter->extractEventId($payload, $headers);

        // 4. Preserve raw event in database immediately
        if ($existingEvent) {
            $eventRecord = $existingEvent;
            $eventRecord->update([
                'status' => 'processing',
                'raw_payload' => json_encode($payload),
            ]);
        } else {
            $eventRecord = LeadIngestionEvent::create([
                'workspace_id' => $workspaceId,
                'lead_source_id' => $source->id,
                'provider' => $source->provider,
                'event_id' => $eventId,
                'status' => 'pending',
                'raw_payload' => json_encode($payload),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
        }

        // 5. Idempotency Check: check if event with this ID already succeeded
        $existingDuplicate = LeadIngestionEvent::where('workspace_id', $workspaceId)
            ->where('event_id', $eventId)
            ->where('status', 'processed')
            ->where('id', '!=', $eventRecord->id)
            ->first();

        if ($existingDuplicate) {
            $eventRecord->update([
                'status' => 'duplicate',
                'lead_id' => $existingDuplicate->lead_id,
                'error_message' => "Duplicate event ID [{$eventId}] already processed.",
                'processed_at' => now(),
            ]);

            return [
                'success' => true,
                'status' => 'duplicate',
                'message' => 'Event already ingested previously. Lead preserved without duplication.',
                'lead_id' => $existingDuplicate->lead_id,
                'event_id' => $eventId,
            ];
        }

        try {
            return DB::transaction(function () use ($source, $adapter, $payload, $eventRecord, $workspaceId, $eventId) {
                // 6. Normalize field names and values
                $normalized = $adapter->normalizePayload($source, $payload);

                // 7. Apply Tenant-Specific Field Mappings
                $mappings = LeadFieldMapping::where('workspace_id', $workspaceId)
                    ->where(function ($q) use ($source) {
                        $q->where('lead_source_id', $source->id)
                            ->orWhere('provider', $source->provider)
                            ->orWhere('provider', 'generic');
                    })
                    ->get();

                foreach ($mappings as $mapping) {
                    $sourceKey = $mapping->source_field;
                    $crmKey = $mapping->crm_field;

                    if (isset($payload[$sourceKey]) && $payload[$sourceKey] !== '') {
                        $val = $payload[$sourceKey];
                        // Transform rules
                        $val = match ($mapping->transform_rule) {
                            'trim' => trim((string) $val),
                            'lowercase' => strtolower(trim((string) $val)),
                            'uppercase' => strtoupper(trim((string) $val)),
                            'phone_normalize' => preg_replace('/[^0-9+]/', '', (string) $val),
                            default => $val,
                        };
                        $normalized[$crmKey] = $val;
                    } elseif (!empty($mapping->default_value) && empty($normalized[$crmKey])) {
                        $normalized[$crmKey] = $mapping->default_value;
                    }
                }

                // 8. Validate required data
                if (empty($normalized['name']) && empty($normalized['email']) && empty($normalized['phone'])) {
                    $errorMsg = 'Ingestion event missing minimal identity: requires name, email, or phone number.';
                    $eventRecord->update([
                        'status' => 'failed',
                        'error_message' => $errorMsg,
                        'normalized_payload' => $normalized,
                    ]);

                    return [
                        'success' => false,
                        'status' => 'validation_error',
                        'error' => $errorMsg,
                    ];
                }

                if (empty($normalized['name'])) {
                    $normalized['name'] = $normalized['email'] ?? $normalized['phone'] ?? 'New Inbound Lead';
                }

                // 9. Find Possible Duplicates
                $duplicates = $this->duplicateService->checkDuplicates([
                    'phone' => $normalized['phone'] ?? null,
                    'email' => $normalized['email'] ?? null,
                    'company_name' => $normalized['company_name'] ?? null,
                ]);

                $attribution = $adapter->extractAttribution($source, $payload);

                $existingLead = null;
                if (!empty($duplicates)) {
                    $topMatch = $duplicates[0];
                    $existingLead = Lead::where('tenant_id', $workspaceId)->find($topMatch['id']);
                }

                // 10 & 11. Create or Update CRM Lead with Attribution
                if ($existingLead) {
                    // Update existing lead with latest touchpoint without overwriting core details
                    $existingLead->update([
                        'notes' => ($existingLead->notes ? $existingLead->notes . "\n---\n" : '') .
                            "New touchpoint from [{$source->name}] at " . now()->toDateTimeString() .
                            ($normalized['requirement'] ? ": " . $normalized['requirement'] : ''),
                        'last_touch_attribution' => $attribution,
                    ]);
                    $lead = $existingLead;
                    $actionType = 'updated_existing';
                } else {
                    $lead = Lead::create([
                        'tenant_id' => $workspaceId,
                        'name' => $normalized['name'],
                        'email' => $normalized['email'] ?? null,
                        'phone' => $normalized['phone'] ?? null,
                        'company_name' => $normalized['company_name'] ?? null,
                        'designation' => $normalized['designation'] ?? null,
                        'requirement' => $normalized['requirement'] ?? null,
                        'estimated_value' => $normalized['estimated_value'] ?? 0,
                        'status' => 'new',
                        'source' => $attribution['source'] ?? $source->provider,
                        'source_account' => $attribution['source_account'] ?? $source->name,
                        'campaign' => $attribution['campaign'] ?? null,
                        'ad_details' => $attribution['ad_details'] ?? ($attribution['ad_id'] ?? null),
                        'utm_source' => $attribution['utm_source'] ?? null,
                        'utm_medium' => $attribution['utm_medium'] ?? null,
                        'utm_campaign' => $attribution['utm_campaign'] ?? null,
                        'utm_term' => $attribution['utm_term'] ?? null,
                        'utm_content' => $attribution['utm_content'] ?? null,
                        'landing_page_url' => $attribution['landing_page_url'] ?? null,
                        'first_touch_attribution' => $attribution,
                        'last_touch_attribution' => $attribution,
                        'tags' => [$source->provider, 'inbound', 'automated'],
                    ]);
                    $actionType = 'created';
                }

                // 12. Lead Scoring & Auto-Distribution
                $this->scoringService->updateLeadScore($lead);

                // Check if round-robin assignment is enabled on source or workspace
                if (empty($lead->assigned_user_id)) {
                    $this->distributionService->autoDistributeRoundRobin($workspaceId);
                    $lead->refresh();
                }

                // 13. Create Inbound Activity Record
                CrmActivity::create([
                    'workspace_id' => $workspaceId,
                    'lead_id' => $lead->id,
                    'activity_type' => 'status_change',
                    'title' => "Inbound Lead from {$source->name}",
                    'description' => "Captured via {$source->provider} lead acquisition pipeline (Event ID: {$eventId}).",
                    'metadata' => [
                        'provider' => $source->provider,
                        'event_id' => $eventId,
                        'attribution' => $attribution,
                    ],
                ]);

                // 14. Notify assigned user if present
                if ($lead->assignedUser) {
                    $this->notificationService->notify(
                        $lead->assignedUser,
                        'lead.assigned',
                        "New Lead Ingested: {$lead->name}",
                        "New lead captured from {$source->name} and assigned to you.",
                        "/leads/{$lead->id}"
                    );
                }

                // 15. Store Processing Result and Update Event Record
                $eventRecord->update([
                    'status' => 'processed',
                    'lead_id' => $lead->id,
                    'normalized_payload' => $normalized,
                    'processed_at' => now(),
                ]);

                // Update source last sync time and status
                $source->update([
                    'sync_status' => 'success',
                    'last_synced_at' => now(),
                    'last_error' => null,
                ]);

                return [
                    'success' => true,
                    'status' => 'processed',
                    'action' => $actionType,
                    'lead_id' => $lead->id,
                    'lead_name' => $lead->name,
                    'event_id' => $eventId,
                ];
            });
        } catch (\Throwable $e) {
            Log::error("Lead ingestion failed for event [{$eventId}]: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            $eventRecord->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count' => $eventRecord->retry_count + 1,
            ]);

            $source->update([
                'sync_status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'event_id' => $eventId,
            ];
        }
    }

    /**
     * Retry a previously failed ingestion event safely.
     */
    public function retry(LeadIngestionEvent $event): array
    {
        if ($event->status === 'processed') {
            return [
                'success' => true,
                'message' => 'Event already processed successfully.',
                'lead_id' => $event->lead_id,
            ];
        }

        $source = $event->source;
        if (!$source) {
            return [
                'success' => false,
                'error' => 'Source configuration no longer exists for this event.',
            ];
        }

        $payload = json_decode($event->raw_payload, true) ?: [];

        // Increment retry count
        $event->update([
            'retry_count' => $event->retry_count + 1,
            'status' => 'processing',
        ]);

        return $this->ingest(
            $source,
            $payload,
            [],
            $event->ip_address,
            $event->user_agent,
            $event
        );
    }
}
