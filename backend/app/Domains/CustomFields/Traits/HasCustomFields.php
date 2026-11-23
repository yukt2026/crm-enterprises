<?php

namespace App\Domains\CustomFields\Traits;

use App\Domains\CustomFields\Models\CustomFieldDefinition;
use App\Domains\CustomFields\Models\CustomFieldValue;
use App\Domains\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCustomFields
{
    public function customFieldValues(): HasMany
    {
        $entityType = strtolower(class_basename(self::class));
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', $entityType);
    }

    public function getCustomFieldsAttribute(): array
    {
        $entityType = strtolower(class_basename(self::class));
        $tenantId = $this->tenant_id ?? TenantContext::getTenantId();

        $definitions = CustomFieldDefinition::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->orderBy('display_order')
            ->get();

        $values = CustomFieldValue::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $this->getKey())
            ->get()
            ->keyBy('custom_field_definition_id');

        $result = [];
        foreach ($definitions as $def) {
            $valRecord = $values->get($def->id);
            $rawVal = $valRecord ? $valRecord->value : $def->default_value;

            // Decode JSON for multi_select / checkbox if applicable
            if (in_array($def->type, ['multi_select', 'checkbox']) && $rawVal) {
                $decoded = json_decode($rawVal, true);
                $rawVal = $decoded !== null ? $decoded : $rawVal;
            }

            $result[$def->field_key] = [
                'definition_id' => $def->id,
                'name' => $def->name,
                'type' => $def->type,
                'value' => $rawVal,
                'options' => $def->options,
                'is_required' => $def->is_required,
            ];
        }

        return $result;
    }

    public function setCustomField(string $fieldKey, mixed $value): void
    {
        $entityType = strtolower(class_basename(self::class));
        $tenantId = $this->tenant_id ?? TenantContext::getTenantId();

        $definition = CustomFieldDefinition::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('field_key', $fieldKey)
            ->first();

        if (!$definition) {
            return;
        }

        $storedValue = is_array($value) ? json_encode($value) : (string) $value;

        CustomFieldValue::updateOrCreate(
            [
                'custom_field_definition_id' => $definition->id,
                'entity_id' => $this->getKey(),
            ],
            [
                'tenant_id' => $tenantId,
                'entity_type' => $entityType,
                'value' => $storedValue,
            ]
        );
    }
}
