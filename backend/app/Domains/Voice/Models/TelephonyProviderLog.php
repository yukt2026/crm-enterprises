<?php

namespace App\Domains\Voice\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyProviderLog extends Model
{
    protected $table = 'telephony_provider_logs';

    protected $fillable = [
        'tenant_id',
        'provider',
        'provider_call_id',
        'event_type',
        'status',
        'payload',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
