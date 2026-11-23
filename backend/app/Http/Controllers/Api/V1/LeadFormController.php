<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LeadAcquisition\Models\LeadForm;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeadFormController extends Controller
{
    public function __construct(protected TenantContext $tenantContext) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = $this->getTenantId();

        $forms = LeadForm::where('workspace_id', $workspaceId)
            ->with('source')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $forms,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'provider' => 'nullable|string|max:50',
            'lead_source_id' => 'nullable|integer',
            'external_form_id' => 'nullable|string|max:150',
            'fields_config' => 'nullable|array',
            'campaign_name' => 'nullable|string|max:150',
        ]);

        $workspaceId = $this->getTenantId();

        $defaultFields = [
            ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Work Email', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone Number', 'type' => 'tel', 'required' => false],
            ['name' => 'company_name', 'label' => 'Company Name', 'type' => 'text', 'required' => false],
            ['name' => 'requirement', 'label' => 'Project Requirements', 'type' => 'textarea', 'required' => false],
        ];

        $form = LeadForm::create([
            'workspace_id' => $workspaceId,
            'lead_source_id' => $validated['lead_source_id'] ?? null,
            'provider' => $validated['provider'] ?? 'website',
            'name' => $validated['name'],
            'external_form_id' => $validated['external_form_id'] ?? null,
            'public_uuid' => (string) Str::uuid(),
            'fields_config' => $validated['fields_config'] ?? $defaultFields,
            'campaign_name' => $validated['campaign_name'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lead form created successfully.',
            'data' => $form,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $workspaceId = $this->getTenantId();
        $form = LeadForm::where('workspace_id', $workspaceId)->with('source')->findOrFail($id);

        $embedScript = "<script src=\"" . url('/api/v1/public/forms/' . $form->public_uuid . '/embed.js') . "\" async></script>\n" .
            "<div id=\"crm-lead-form-{$form->public_uuid}\"></div>";

        $hostedUrl = url("/forms/{$form->public_uuid}");

        return response()->json([
            'status' => 'success',
            'data' => array_merge($form->toArray(), [
                'embed_snippet' => $embedScript,
                'hosted_url' => $hostedUrl,
                'submit_endpoint' => url('/api/v1/public/forms/' . $form->public_uuid . '/submit'),
            ]),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $workspaceId = $this->getTenantId();
        $form = LeadForm::where('workspace_id', $workspaceId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'fields_config' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'campaign_name' => 'nullable|string|max:150',
        ]);

        $form->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Form configuration updated.',
            'data' => $form,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $workspaceId = $this->getTenantId();
        $form = LeadForm::where('workspace_id', $workspaceId)->findOrFail($id);

        $form->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Form removed successfully.',
        ]);
    }
}
