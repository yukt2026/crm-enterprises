<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Services\DuplicateDetectionService;
use App\Domains\CRM\Services\LeadScoringService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Domains\LeadAcquisition\Models\LeadStageHistory;
use App\Domains\LeadAcquisition\Services\Adapters\IndianB2BPortalNormalizer;
use App\Domains\Projects\Models\Task;
use App\Domains\Tenancy\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadCaptureService
{
    public function __construct(
        protected LeadRoutingEngine $routingEngine,
        protected LeadSlaEngine $slaEngine,
        protected LeadScoringService $scoringService,
        protected DuplicateDetectionService $duplicateService
    ) {}

    /**
     * Ingest lead from any source, normalize, check duplicates, route, SLA, and score
     */
    public function captureLead(array $input, int $tenantId, ?int $capturedByUserId = null): array
    {
        TenantContext::setTenantId($tenantId);

        // 1. Normalization
        $phone = IndianB2BPortalNormalizer::normalizeIndianPhone($input['phone'] ?? $input['mobile'] ?? null);
        $email = !empty($input['email']) ? strtolower(trim($input['email'])) : null;
        $name = trim($input['name'] ?? $input['customer_name'] ?? 'New Prospect');
        $company = !empty($input['company_name']) ? trim($input['company_name']) : (!empty($input['company']) ? trim($input['company']) : null);
        $source = strtolower($input['source'] ?? 'website');
        $campaign = $input['campaign'] ?? $input['utm_campaign'] ?? null;

        // 2. Duplicate Detection
        $duplicateCheck = $this->duplicateService->checkDuplicates([
            'phone' => $phone,
            'email' => $email,
            'company_name' => $company,
            'name' => $name,
        ]);

        $hasExactDuplicate = false;
        $existingLeadId = null;
        foreach ($duplicateCheck as $dup) {
            if ($dup['entity_type'] === 'lead' && in_array($dup['confidence'] ?? '', ['exact', 'high'])) {
                $hasExactDuplicate = true;
                $existingLeadId = $dup['id'];
                break;
            }
        }

        // 3. Create or Update Record
        $lead = DB::transaction(function () use ($input, $tenantId, $phone, $email, $name, $company, $source, $campaign, $capturedByUserId) {
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'phone' => $phone,
                'alternate_phone' => IndianB2BPortalNormalizer::normalizeIndianPhone($input['alternate_phone'] ?? null),
                'email' => $email,
                'company_name' => $company,
                'designation' => $input['designation'] ?? null,
                'address' => $input['address'] ?? null,
                'city' => $input['city'] ?? null,
                'state' => $input['state'] ?? null,
                'country' => $input['country'] ?? 'India',
                'postal_code' => $input['postal_code'] ?? null,
                'product_interest' => $input['product_interest'] ?? $input['service'] ?? null,
                'requirement' => $input['requirement'] ?? $input['message'] ?? $input['query'] ?? null,
                'estimated_value' => (float) ($input['estimated_value'] ?? $input['budget'] ?? 0),
                'expected_purchase_date' => $input['expected_purchase_date'] ?? null,
                'source' => $source,
                'original_source' => $source, // Immutable original attribution
                'campaign' => $campaign,
                'original_campaign' => $campaign, // Immutable original attribution
                'external_lead_id' => $input['external_lead_id'] ?? null,
                'utm_source' => $input['utm_source'] ?? $input['source'] ?? null,
                'utm_medium' => $input['utm_medium'] ?? null,
                'utm_campaign' => $campaign,
                'utm_term' => $input['utm_term'] ?? null,
                'utm_content' => $input['utm_content'] ?? null,
                'landing_page_url' => $input['landing_page_url'] ?? null,
                'branch_id' => $input['branch_id'] ?? null,
                'status' => 'new',
                'priority' => $input['priority'] ?? 'medium',
                'tags' => $input['tags'] ?? [$source, 'inbound'],
                'notes' => $input['notes'] ?? null,
                'custom_fields_payload' => $input['custom_fields'] ?? null,
            ]);

            // Initial Stage History
            LeadStageHistory::create([
                'tenant_id' => $tenantId,
                'lead_id' => $lead->id,
                'user_id' => $capturedByUserId,
                'from_stage' => null,
                'to_stage' => 'new',
                'time_in_previous_stage_seconds' => 0,
                'reason' => 'Initial lead acquisition',
                'notes' => "Lead captured via {$source}",
                'created_at' => now(),
            ]);

            // Initial Activity Log
            CrmActivity::create([
                'tenant_id' => $tenantId,
                'user_id' => $capturedByUserId,
                'lead_id' => $lead->id,
                'activity_type' => 'status_change',
                'title' => "Lead Captured ({$source})",
                'description' => "Enquiry received from {$name} (" . ($company ?: 'Individual') . ") via {$source}.",
                'metadata' => [
                    'source' => $source,
                    'campaign' => $campaign,
                    'external_id' => $input['external_lead_id'] ?? null,
                ],
            ]);

            return $lead;
        });

        // 4. Calculate initial Lead Score & Qualification
        $lead = $this->scoringService->updateLeadScore($lead);

        // 5. Apply SLA Policy (Compute first-contact deadline)
        $lead = $this->slaEngine->applySlaPolicy($lead);

        // 6. Execute Routing Rules Engine
        $lead = $this->routingEngine->routeAndAssign($lead, $capturedByUserId);

        // 7. Auto-create follow-up task
        LeadFollowup::create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead->id,
            'assigned_user_id' => $lead->assigned_user_id,
            'followup_type' => 'call',
            'scheduled_at' => $lead->sla_first_response_due_at ?? now()->addMinutes(15),
            'status' => 'pending',
            'outcome_notes' => 'Initial lead follow-up and discovery call',
            'created_by' => $capturedByUserId,
        ]);

        return [
            'lead' => $lead->fresh(['assignedUser', 'company', 'slaPolicy']),
            'is_duplicate' => $hasExactDuplicate,
            'duplicate_lead_id' => $existingLeadId,
            'similar_leads' => $duplicateCheck,
        ];
    }
}
