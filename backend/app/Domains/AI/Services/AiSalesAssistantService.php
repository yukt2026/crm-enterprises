<?php

namespace App\Domains\AI\Services;

use App\Domains\AI\Models\AiSalesInsight;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AiSalesAssistantService
{
    public function __construct(
        protected AiProviderGatewayService $aiGateway
    ) {}

    /**
     * Analyze a lead or deal, extracting buying intent, qualifications, risks, and next actions.
     */
    public function analyzeSalesEntity(int $tenantId, string $entityType, int $entityId, ?User $user = null): AiSalesInsight
    {
        $contextData = [];
        $title = '';

        if ($entityType === 'lead') {
            $lead = Lead::where('tenant_id', $tenantId)->find($entityId);
            if (!$lead) {
                throw new \InvalidArgumentException("Lead #{$entityId} not found in current workspace.");
            }
            $title = $lead->title ?? $lead->first_name;
            $contextData = [
                'type' => 'lead',
                'name' => ($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''),
                'company' => $lead->company_name ?? 'N/A',
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => $lead->status,
                'lead_score' => $lead->lead_score ?? 50,
                'source' => $lead->source ?? 'organic',
                'notes' => $lead->notes ?? '',
                'custom_fields' => $lead->custom_fields ?? [],
            ];
        } elseif ($entityType === 'deal') {
            $deal = Deal::where('tenant_id', $tenantId)->find($entityId);
            if (!$deal) {
                throw new \InvalidArgumentException("Deal #{$entityId} not found in current workspace.");
            }
            $title = $deal->title;
            $contextData = [
                'type' => 'deal',
                'title' => $deal->title,
                'amount' => $deal->deal_value ?? $deal->amount ?? 0,
                'stage' => $deal->stage,
                'probability' => $deal->probability ?? 50,
                'expected_close_date' => $deal->expected_close_date,
                'lost_reason' => $deal->lost_reason ?? null,
            ];
        } else {
            throw new \InvalidArgumentException("Entity type '{$entityType}' is not supported for sales analysis.");
        }

        // Check for potential duplicate leads in workspace
        $duplicateLeadId = null;
        if ($entityType === 'lead') {
            $duplicate = Lead::where('tenant_id', $tenantId)
                ->where('id', '!=', $entityId)
                ->where(function ($q) use ($contextData) {
                    if (!empty($contextData['phone'])) {
                        $q->orWhere('phone', $contextData['phone']);
                    }
                    if (!empty($contextData['email'])) {
                        $q->orWhere('email', $contextData['email']);
                    }
                })->first();
            $duplicateLeadId = $duplicate?->id;
        }

        // Dispatch to AI gateway
        $systemPrompt = "You are an enterprise Sales Intelligence AI. Analyze this CRM record. Respond ONLY with a valid JSON object matching the requested schema.";
        $userPrompt = "Analyze this CRM " . strtoupper($entityType) . " record:\n" . json_encode($contextData, JSON_PRETTY_PRINT) . "\n\n" .
            "Generate JSON with: summary (string), extracted_requirements (array of strings), qualification_status (qualified/unqualified/under_review), " .
            "temperature (hot/warm/cold), intent_score (0-100 integer), confidence_score (0.0 to 1.0 float), confidence_rationale (string), " .
            "suggested_next_action (string), objections_detected (array of strings), deal_risk_factors (array of strings), sales_pitch (string), " .
            "suggested_follow_up_message (string).";

        $response = $this->aiGateway->generateCompletion($tenantId, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], 'sales_assistant', [
            'user_id' => $user?->id,
            'temperature' => 0.4,
        ]);

        $rawJson = $response['content'];
        $cleanJson = trim(preg_replace('/^```json|```$/m', '', $rawJson));
        $parsed = json_decode($cleanJson, true) ?? [];

        // Save or update existing insight record
        $insight = AiSalesInsight::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'summary' => $parsed['summary'] ?? "Prospect analysis for {$title}",
                'extracted_requirements' => $parsed['extracted_requirements'] ?? ['Standard CRM features'],
                'qualification_status' => $parsed['qualification_status'] ?? 'qualified',
                'temperature' => $parsed['temperature'] ?? 'warm',
                'intent_score' => (int) ($parsed['intent_score'] ?? 75),
                'confidence_score' => (float) ($parsed['confidence_score'] ?? 0.85),
                'confidence_rationale' => $parsed['confidence_rationale'] ?? 'High engagement indicators observed.',
                'suggested_next_action' => $parsed['suggested_next_action'] ?? 'Schedule follow-up call',
                'objections_detected' => $parsed['objections_detected'] ?? [],
                'deal_risk_factors' => $parsed['deal_risk_factors'] ?? [],
                'sales_pitch' => $parsed['sales_pitch'] ?? 'Emphasize fast deployment and responsive support.',
                'suggested_follow_up_message' => $parsed['suggested_follow_up_message'] ?? 'Hi, following up on your CRM requirements.',
                'suggested_duplicate_lead_id' => $duplicateLeadId,
                'analyzed_at' => now(),
            ]
        );

        return $insight;
    }

    /**
     * Get existing insights or return null
     */
    public function getInsight(int $tenantId, string $entityType, int $entityId): ?AiSalesInsight
    {
        return AiSalesInsight::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();
    }
}
