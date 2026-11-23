<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantApiRequestLog extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'api_key_id',
        'method',
        'endpoint',
        'status_code',
        'ip_address',
        'user_agent',
        'response_time_ms',
        'request_payload_summary',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(TenantApiKey::class, 'api_key_id');
    }
}
