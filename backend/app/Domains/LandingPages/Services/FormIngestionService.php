<?php

namespace App\Domains\LandingPages\Services;

use App\Domains\LandingPages\Models\Form;
use App\Domains\LandingPages\Models\FormSubmission;
use App\Domains\LandingPages\Models\LandingPage;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Workflow\Services\WorkflowExecutionEngine;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class FormIngestionService
{
    public function __construct(
        protected ?WorkflowExecutionEngine $workflowEngine = null
    ) {
        $this->workflowEngine = $workflowEngine ?? app(WorkflowExecutionEngine::class);
    }

    /**
     * Ingest and validate a public form submission.
     */
    public function ingest(
        Form $form,
        array $submittedData,
        ?LandingPage $landingPage = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referrer = null
    ): array {
        $tenantId = $form->tenant_id;
        $settings = $form->settings ?? [];
        $spamSettings = $settings['spam_protection'] ?? [
            'honeypot_enabled' => true,
            'min_time_seconds' => 2,
            'rate_limit_per_minute' => 15,
        ];

        // 1. Rate Limiting Check
        if ($ipAddress) {
            $rateKey = "form_sub:{$form->id}:{$ipAddress}";
            $maxAttempts = (int) ($spamSettings['rate_limit_per_minute'] ?? 15);
            if (RateLimiter::tooManyAttempts($rateKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($rateKey);
                throw ValidationException::withMessages([
                    'rate_limit' => ["Too many submissions. Please wait {$seconds} seconds before trying again."],
                ]);
            }
            RateLimiter::hit($rateKey, 60);
        }

        // 2. Spam & Bot Protection (Honeypot & Submission Timestamp)
        $isSpam = false;
        $spamScore = 0.00;

        // Honeypot field (hidden input that humans won't fill)
        $honeypotField = $spamSettings['honeypot_field_name'] ?? '_hp_verify';
        if (!empty($submittedData[$honeypotField])) {
            $isSpam = true;
            $spamScore = 1.00;
        }

        // Timestamp check (ensure at least min_time_seconds spent on page)
        $renderedAt = (int) ($submittedData['_rendered_at'] ?? 0);
        $minTime = (int) ($spamSettings['min_time_seconds'] ?? 2);
        if ($renderedAt > 0) {
            $timeElapsed = time() - $renderedAt;
            if ($timeElapsed < $minTime) {
                $isSpam = true;
                $spamScore = max($spamScore, 0.85);
            }
        }

        if ($isSpam) {
            // Log submission as flagged spam without polluting CRM leads
            $submission = FormSubmission::create([
                'tenant_id' => $tenantId,
                'form_id' => $form->id,
                'landing_page_id' => $landingPage?->id,
                'data' => $submittedData,
                'utm_data' => $this->extractUtm($submittedData),
                'referrer' => $referrer,
                'consent_given' => false,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'spam_score' => $spamScore,
            ]);

            return [
                'status' => 'spam_blocked',
                'submission_id' => $submission->id,
                'message' => 'Submission received.',
            ];
        }

        // 3. Schema & Field Validation
        $schema = $form->schema ?? [];
        $fields = $schema['fields'] ?? [];
        $cleanData = [];
        $validationErrors = [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            if (!$name) continue;

            $val = $submittedData[$name] ?? null;
            $isRequired = !empty($field['required']);
            $type = $field['type'] ?? 'text';

            if ($isRequired && (is_null($val) || $val === '')) {
                $validationErrors[$name] = ["The {$field['label']} field is required."];
                continue;
            }

            if ($val !== null && $val !== '') {
                if ($type === 'email' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $validationErrors[$name] = ["The {$field['label']} must be a valid email address."];
                } elseif ($type === 'phone' && strlen(preg_replace('/[^0-9]/', '', $val)) < 7) {
                    $validationErrors[$name] = ["The {$field['label']} must be a valid phone number."];
                } elseif ($type === 'number' && !is_numeric($val)) {
                    $validationErrors[$name] = ["The {$field['label']} must be a valid number."];
                }
            }

            $cleanData[$name] = $val;
        }

        // 4. Consent Check
        $consentRequired = !empty($settings['require_consent']);
        $consentGiven = !empty($submittedData['consent_agreed']) || !empty($submittedData['terms_accepted']);
        if ($consentRequired && !$consentGiven) {
            $validationErrors['consent_agreed'] = ['You must agree to the privacy policy before submitting.'];
        }

        if (!empty($validationErrors)) {
            throw ValidationException::withMessages($validationErrors);
        }

        // 5. Extract UTM Attribution
        $utmData = $this->extractUtm($submittedData);

        // 6. Record Form Submission
        $submission = FormSubmission::create([
            'tenant_id' => $tenantId,
            'form_id' => $form->id,
            'landing_page_id' => $landingPage?->id,
            'data' => $cleanData,
            'utm_data' => $utmData,
            'referrer' => $referrer,
            'consent_given' => $consentGiven,
            'consent_text' => $settings['consent_text'] ?? 'Customer consented to contact via web form.',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'spam_score' => 0.00,
        ]);

        $form->increment('submission_count');
        if ($landingPage) {
            $landingPage->increment('submission_count');
        }

        // 7. Central CRM Lead Ingestion Pipeline
        $lead = $this->ingestIntoCrmLead($tenantId, $cleanData, $utmData, $form, $landingPage, $referrer);
        if ($lead) {
            $submission->update(['lead_id' => $lead->id]);
        }

        // 8. Trigger Workflow Automation ('form.submitted', 'lead.created')
        if ($this->workflowEngine) {
            try {
                $this->workflowEngine->trigger($tenantId, 'form.submitted', [
                    'form' => [
                        'id' => $form->id,
                        'name' => $form->name,
                        'slug' => $form->slug,
                    ],
                    'submission' => [
                        'id' => $submission->id,
                        'data' => $cleanData,
                        'utm' => $utmData,
                    ],
                    'lead' => $lead ? $lead->toArray() : null,
                ]);
            } catch (\Throwable $e) {
                // Automation failure does not break successful public submission
            }
        }

        return [
            'status' => 'success',
            'submission_id' => $submission->id,
            'lead_id' => $lead?->id,
            'success_type' => $settings['success_type'] ?? 'message',
            'message' => $settings['success_message'] ?? 'Thank you! Your information has been received.',
            'redirect_url' => $settings['redirect_url'] ?? null,
        ];
    }

    /**
     * Map submitted form fields to CRM Lead model.
     */
    protected function ingestIntoCrmLead(
        int $tenantId,
        array $cleanData,
        array $utmData,
        Form $form,
        ?LandingPage $landingPage,
        ?string $referrer
    ): Lead {
        $email = strtolower(trim($cleanData['email'] ?? ($cleanData['work_email'] ?? '')));
        $phone = trim($cleanData['phone'] ?? ($cleanData['mobile'] ?? ''));
        $name = trim($cleanData['name'] ?? ($cleanData['full_name'] ?? ''));
        if (empty($name) && (!empty($cleanData['first_name']) || !empty($cleanData['last_name']))) {
            $name = trim(($cleanData['first_name'] ?? '') . ' ' . ($cleanData['last_name'] ?? ''));
        }
        if (empty($name)) {
            $name = $email ?: ($phone ?: 'Website Lead');
        }

        $company = $cleanData['company'] ?? ($cleanData['organization'] ?? null);
        $requirement = $cleanData['requirement'] ?? ($cleanData['message'] ?? ($cleanData['notes'] ?? null));

        // Duplicate Check in Tenant
        $existingLead = null;
        if (!empty($email)) {
            $existingLead = Lead::where('tenant_id', $tenantId)->where('email', $email)->first();
        }
        if (!$existingLead && !empty($phone)) {
            $existingLead = Lead::where('tenant_id', $tenantId)->where('phone', $phone)->first();
        }

        $tags = ['web-form', "form:{$form->slug}"];
        if ($landingPage) {
            $tags[] = "page:{$landingPage->slug}";
        }

        if ($existingLead) {
            // Update existing lead with last-touch attribution and merge tags
            $existingTags = (array) ($existingLead->tags ?? []);
            $mergedTags = array_values(array_unique(array_merge($existingTags, $tags)));

            $existingLead->update([
                'last_touch_attribution' => array_merge($utmData, [
                    'source' => 'web_form',
                    'form_id' => $form->id,
                    'timestamp' => now()->toIso8601String(),
                ]),
                'tags' => $mergedTags,
                'requirement' => $requirement ?: $existingLead->requirement,
            ]);

            return $existingLead;
        }

        // Create new Lead
        return Lead::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'company_name' => $company,
            'requirement' => $requirement,
            'source' => 'landing_page',
            'status' => 'new',
            'lifecycle_stage' => 'lead',
            'priority' => 'medium',
            'score' => 50,
            'utm_source' => $utmData['utm_source'] ?? null,
            'utm_medium' => $utmData['utm_medium'] ?? null,
            'utm_campaign' => $utmData['utm_campaign'] ?? null,
            'utm_term' => $utmData['utm_term'] ?? null,
            'utm_content' => $utmData['utm_content'] ?? null,
            'landing_page_url' => $landingPage ? url("/p/{$landingPage->slug}") : $referrer,
            'first_touch_attribution' => array_merge($utmData, [
                'referrer' => $referrer,
                'form_id' => $form->id,
                'timestamp' => now()->toIso8601String(),
            ]),
            'tags' => $tags,
        ]);
    }

    /**
     * Extract UTM query parameters from input data.
     */
    protected function extractUtm(array $data): array
    {
        return [
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
        ];
    }
}
