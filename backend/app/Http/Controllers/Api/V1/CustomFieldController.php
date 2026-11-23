<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AuditSecurity\Services\AuditLogger;
use App\Domains\CustomFields\Models\CustomFieldDefinition;
use App\Domains\CustomFields\Models\CustomFieldValue;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    /**
     * List custom field definitions for the tenant
     */
    public function index(Request $request): JsonResponse
    {
        $query = CustomFieldDefinition::query()
            ->orderBy('display_order', 'asc');

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        $fields = $query->get();

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Custom field definitions retrieved.',
            'data' => $fields,
        ]);
    }

    /**
     * Create a new custom field definition
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|in:lead,deal,contact,company,customer,project,task,ticket,employee,invoice',
            'name' => 'required|string|max:100',
            'field_key' => 'nullable|string|max:100',
            'type' => 'required|string|in:text,long_text,number,date,select,multi_select,checkbox,email,phone,url,currency,file',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'default_value' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'validation_rules' => 'nullable|array',
            'visibility_roles' => 'nullable|array',
        ]);

        $fieldKey = $request->input('field_key')
            ? Str::slug($request->input('field_key'), '_')
            : Str::slug($request->input('name'), '_');

        $tenantId = TenantContext::getTenantId() ?? 1;

        // Check uniqueness per tenant and entity
        $exists = CustomFieldDefinition::where('tenant_id', $tenantId)
            ->where('entity_type', $request->input('entity_type'))
            ->where('field_key', $fieldKey)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'status_code' => 422,
                'message' => "The field key '{$fieldKey}' already exists for entity '{$request->input('entity_type')}'.",
                'errors' => ['field_key' => ["Field key '{$fieldKey}' is already taken."]],
            ], 422);
        }

        $definition = CustomFieldDefinition::create([
            'tenant_id' => $tenantId,
            'entity_type' => $request->input('entity_type'),
            'name' => $request->input('name'),
            'field_key' => $fieldKey,
            'type' => $request->input('type'),
            'options' => $request->input('options'),
            'is_required' => $request->boolean('is_required', false),
            'default_value' => $request->input('default_value'),
            'display_order' => $request->input('display_order', 0),
            'validation_rules' => $request->input('validation_rules'),
            'visibility_roles' => $request->input('visibility_roles'),
        ]);

        AuditLogger::log('created_custom_field', $definition, null, $definition->toArray());

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => 'Custom field definition created.',
            'data' => $definition,
        ], 201);
    }

    /**
     * Update an existing custom field definition
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $definition = CustomFieldDefinition::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'type' => 'sometimes|required|string|in:text,long_text,number,date,select,multi_select,checkbox,email,phone,url,currency,file',
            'options' => 'nullable|array',
            'is_required' => 'nullable|boolean',
            'default_value' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'validation_rules' => 'nullable|array',
            'visibility_roles' => 'nullable|array',
        ]);

        $old = $definition->toArray();
        $definition->update($request->only([
            'name',
            'type',
            'options',
            'is_required',
            'default_value',
            'display_order',
            'validation_rules',
            'visibility_roles',
        ]));

        AuditLogger::log('updated_custom_field', $definition, $old, $definition->toArray());

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Custom field definition updated.',
            'data' => $definition,
        ]);
    }

    /**
     * Delete custom field definition
     */
    public function destroy(int $id): JsonResponse
    {
        $definition = CustomFieldDefinition::findOrFail($id);
        AuditLogger::log('deleted_custom_field', $definition, $definition->toArray(), null);

        $definition->delete();

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Custom field definition deleted.',
        ]);
    }

    /**
     * Get custom field values for an entity instance
     */
    public function getEntityValues(string $entityType, int $entityId): JsonResponse
    {
        $tenantId = TenantContext::getTenantId() ?? 1;

        $definitions = CustomFieldDefinition::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->orderBy('display_order')
            ->get();

        $values = CustomFieldValue::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->get()
            ->keyBy('custom_field_definition_id');

        $result = $definitions->map(function ($def) use ($values) {
            $val = $values->get($def->id);
            return [
                'definition_id' => $def->id,
                'name' => $def->name,
                'field_key' => $def->field_key,
                'type' => $def->type,
                'options' => $def->options,
                'is_required' => $def->is_required,
                'value' => $val ? $val->value : $def->default_value,
            ];
        });

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Entity custom field values retrieved.',
            'data' => $result,
        ]);
    }

    /**
     * Save custom field values for an entity instance
     */
    public function saveEntityValues(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $request->validate([
            'fields' => 'required|array',
        ]);

        $tenantId = TenantContext::getTenantId() ?? 1;
        $fields = $request->input('fields'); // key => value map

        foreach ($fields as $fieldKey => $val) {
            $def = CustomFieldDefinition::where('tenant_id', $tenantId)
                ->where('entity_type', $entityType)
                ->where('field_key', $fieldKey)
                ->first();

            if ($def) {
                $storedVal = is_array($val) ? json_encode($val) : (string) $val;

                CustomFieldValue::updateOrCreate(
                    [
                        'custom_field_definition_id' => $def->id,
                        'entity_id' => $entityId,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'entity_type' => $entityType,
                        'value' => $storedVal,
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Custom field values saved.',
        ]);
    }
}
