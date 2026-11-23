<?php

namespace App\Domains\PlatformAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformAiProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_key',
        'name',
        'category',
        'is_active',
        'health_status',
        'encrypted_credentials',
        'available_models',
        'default_model',
        'routing_policy',
        'rate_limit_rpm',
        'rate_limit_tpm',
        'last_health_check_at',
        'health_check_message',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    protected $appends = [
        'is_configured',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'encrypted_credentials' => 'encrypted',
        'available_models' => 'array',
        'routing_policy' => 'array',
        'last_health_check_at' => 'datetime',
    ];

    public function getIsConfiguredAttribute(): bool
    {
        return !empty($this->encrypted_credentials);
    }
}
