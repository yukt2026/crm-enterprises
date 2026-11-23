<?php

namespace App\Domains\AI\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantAiConfiguration extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'provider_name',
        'display_name',
        'api_key_encrypted',
        'base_url',
        'default_model',
        'fallback_model',
        'available_models',
        'is_active',
        'is_default',
        'temperature',
        'max_tokens',
        'rate_limit_rpm',
        'rate_limit_tpm',
        'cost_per_1k_input_tokens',
        'cost_per_1k_output_tokens',
        'health_status',
        'last_health_checked_at',
        'last_error_message',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'available_models' => 'array',
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'rate_limit_rpm' => 'integer',
        'rate_limit_tpm' => 'integer',
        'cost_per_1k_input_tokens' => 'float',
        'cost_per_1k_output_tokens' => 'float',
        'last_health_checked_at' => 'datetime',
    ];

    protected $appends = [
        'has_api_key',
    ];

    public function getHasApiKeyAttribute(): bool
    {
        return !empty($this->api_key_encrypted);
    }
}
