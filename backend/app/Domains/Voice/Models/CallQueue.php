<?php

namespace App\Domains\Voice\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallQueue extends Model
{
    protected $table = 'call_queues';

    protected $fillable = [
        'tenant_id',
        'name',
        'department',
        'branch_id',
        'strategy',
        'max_wait_time_seconds',
        'music_on_hold',
        'assigned_agent_ids',
        'is_active',
    ];

    protected $casts = [
        'assigned_agent_ids' => 'array',
        'max_wait_time_seconds' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
