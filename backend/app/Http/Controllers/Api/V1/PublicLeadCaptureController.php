<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LeadAcquisition\Models\LeadForm;
use App\Domains\LeadAcquisition\Models\LeadSource;
use App\Domains\LeadAcquisition\Services\LeadIngestionPipeline;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicLeadCaptureController extends Controller
{
    public function __construct(protected LeadIngestionPipeline $pipeline) {}

    /**
     * Get public form schema for hosted landing page or embedded form.
     */
    public function show(string $uuid): JsonResponse
    {
        $form = LeadForm::where('public_uuid', $uuid)->where('is_active', true)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => [
                'uuid' => $form->public_uuid,
                'name' => $form->name,
                'fields' => $form->fields_config ?? [],
                'submit_url' => url("/api/v1/public/forms/{$form->public_uuid}/submit"),
            ],
        ]);
    }

    /**
     * Submit an enquiry from an embedded or hosted lead form.
     */
    public function submit(Request $request, string $uuid): JsonResponse
    {
        $form = LeadForm::where('public_uuid', $uuid)->where('is_active', true)->firstOrFail();

        // 1. Spam honeypot check
        if (!empty($request->input('_hp_check')) || !empty($request->input('_honeypot'))) {
            return response()->json([
                'status' => 'success',
                'message' => 'Thank you for your submission.',
            ]); // Quietly drop bots
        }

        // 2. Resolve or fallback lead source
        $source = $form->source;
        if (!$source) {
            $source = LeadSource::firstOrCreate(
                [
                    'workspace_id' => $form->workspace_id,
                    'provider' => 'website_form',
                ],
                [
                    'name' => 'Default Website Forms',
                    'status' => 'connected',
                ]
            );
        }

        $payload = $request->all();
        $payload['form_id'] = $form->public_uuid;
        $payload['form_name'] = $form->name;
        $payload['campaign_name'] = $payload['utm_campaign'] ?? $form->campaign_name;

        $result = $this->pipeline->ingest(
            $source,
            $payload,
            $request->headers->all(),
            $request->ip(),
            $request->userAgent()
        );

        if ($result['success']) {
            $form->increment('submissions_count');
            $form->update(['last_submission_at' => now()]);

            return response()->json([
                'status' => 'success',
                'message' => 'Your enquiry has been received successfully. A specialist will follow up shortly.',
                'data' => [
                    'lead_id' => $result['lead_id'] ?? null,
                ],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => $result['error'] ?? 'Submission failed. Please check your information and try again.',
        ], 422);
    }

    /**
     * Generate dynamic embedding JavaScript.
     */
    public function embedScript(string $uuid): Response
    {
        $form = LeadForm::where('public_uuid', $uuid)->where('is_active', true)->firstOrFail();

        $submitUrl = url("/api/v1/public/forms/{$form->public_uuid}/submit");
        $fieldsJson = json_encode($form->fields_config ?? []);

        $js = <<<JS
(function() {
    const container = document.getElementById("crm-lead-form-{$form->public_uuid}");
    if (!container) return;

    const fields = {$fieldsJson};
    const submitUrl = "{$submitUrl}";

    let formHtml = `<form id="crm-form-{$form->public_uuid}" style="font-family:sans-serif;max-width:480px;margin:auto;display:flex;flex-direction:column;gap:12px;">`;
    formHtml += `<input type="text" name="_hp_check" style="display:none;" tabindex="-1" autocomplete="off" />`;

    fields.forEach(f => {
        formHtml += `<div style="display:flex;flex-direction:column;gap:4px;">`;
        formHtml += `<label style="font-size:12px;font-weight:600;color:#334155;">\${f.label}\${f.required ? ' *' : ''}</label>`;
        if (f.type === 'textarea') {
            formHtml += `<textarea name="\${f.name}" \${f.required ? 'required' : ''} style="border:1px solid #cbd5e1;padding:8px 12px;border-radius:8px;font-size:13px;"></textarea>`;
        } else {
            formHtml += `<input type="\${f.type}" name="\${f.name}" \${f.required ? 'required' : ''} style="border:1px solid #cbd5e1;padding:8px 12px;border-radius:8px;font-size:13px;" />`;
        }
        formHtml += `</div>`;
    });

    formHtml += `<button type="submit" style="background:#10b981;color:white;font-weight:700;border:none;padding:10px 16px;border-radius:8px;cursor:pointer;font-size:14px;margin-top:8px;">Submit Enquiry</button>`;
    formHtml += `<div id="crm-form-msg-{$form->public_uuid}" style="font-size:13px;margin-top:8px;display:none;"></div>`;
    formHtml += `</form>`;

    container.innerHTML = formHtml;

    const formEl = document.getElementById("crm-form-{$form->public_uuid}");
    const msgEl = document.getElementById("crm-form-msg-{$form->public_uuid}");

    formEl.addEventListener("submit", async function(e) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(formEl).entries());
        data.referrer = document.referrer;
        data.page_url = window.location.href;

        // Auto UTM capture
        const urlParams = new URLSearchParams(window.location.search);
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'].forEach(p => {
            if (urlParams.get(p)) data[p] = urlParams.get(p);
        });

        try {
            msgEl.style.display = 'block';
            msgEl.style.color = '#64748b';
            msgEl.innerText = 'Submitting...';

            const res = await fetch(submitUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify(data)
            });
            const resJson = await res.json();
            if (res.ok) {
                msgEl.style.color = '#10b981';
                msgEl.innerText = resJson.message || 'Submitted successfully!';
                formEl.reset();
            } else {
                msgEl.style.color = '#ef4444';
                msgEl.innerText = resJson.message || 'Submission failed.';
            }
        } catch(err) {
            msgEl.style.color = '#ef4444';
            msgEl.innerText = 'Connection error. Please try again.';
        }
    });
})();
JS;

        return response($js, 200, ['Content-Type' => 'application/javascript']);
    }
}
