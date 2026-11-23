<?php

namespace App\Domains\CustomFields\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomFieldDefinition extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'name',
        'field_key',
        'type',
        'options',
        'is_required',
        'default_value',
        'display_order',
        'validation_rules',
        'visibility_roles',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'display_order' => 'integer',
        'validation_rules' => 'array',
        'visibility_roles' => 'array',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_definition_id');
    }
}
