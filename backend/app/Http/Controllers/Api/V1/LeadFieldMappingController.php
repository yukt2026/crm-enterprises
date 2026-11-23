<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LeadAcquisition\Models\LeadFieldMapping;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadFieldMappingController extends Controller
{
    public function __construct(protected TenantContext $tenantContext) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $workspaceId = $this->getTenantId();
        $provider = $request->query('provider');

        $query = LeadFieldMapping::where('workspace_id', $workspaceId)->with('source');
        if ($provider) {
            $query->where('provider', $provider);
        }

        $mappings = $query->orderBy('provider')->get();

        $crmLeadFields = [
            ['key' => 'name', 'label' => 'Full Name', 'type' => 'string'],
            ['key' => 'first_name', 'label' => 'First Name', 'type' => 'string'],
            ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'string'],
            ['key' => 'email', 'label' => 'Email Address', 'type' => 'string'],
            ['key' => 'phone', 'label' => 'Primary Phone', 'type' => 'string'],
            ['key' => 'alternate_phone', 'label' => 'Alternate Phone', 'type' => 'string'],
            ['key' => 'company_name', 'label' => 'Company / Account Name', 'type' => 'string'],
            ['key' => 'designation', 'label' => 'Job Title / Designation', 'type' => 'string'],
            ['key' => 'requirement', 'label' => 'Project Requirement / Notes', 'type' => 'string'],
            ['key' => 'estimated_value', 'label' => 'Budget / Contract Value', 'type' => 'decimal'],
            ['key' => 'city', 'label' => 'City', 'type' => 'string'],
            ['key' => 'country', 'label' => 'Country', 'type' => 'string'],
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'mappings' => $mappings,
                'available_crm_fields' => $crmLeadFields,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string|max:50',
            'lead_source_id' => 'nullable|integer',
            'source_field' => 'required|string|max:100',
            'crm_field' => 'required|string|max:100',
            'transform_rule' => 'nullable|string|in:trim,lowercase,uppercase,phone_normalize,default',
            'default_value' => 'nullable|string',
            'is_required' => 'nullable|boolean',
        ]);

        $workspaceId = $this->getTenantId();

        $mapping = LeadFieldMapping::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'provider' => $validated['provider'],
                'source_field' => $validated['source_field'],
            ],
            [
                'lead_source_id' => $validated['lead_source_id'] ?? null,
                'crm_field' => $validated['crm_field'],
                'transform_rule' => $validated['transform_rule'] ?? 'trim',
                'default_value' => $validated['default_value'] ?? null,
                'is_required' => $validated['is_required'] ?? false,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Field mapping rule saved.',
            'data' => $mapping,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $workspaceId = $this->getTenantId();
        $mapping = LeadFieldMapping::where('workspace_id', $workspaceId)->findOrFail($id);

        $mapping->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mapping removed.',
        ]);
    }
}
