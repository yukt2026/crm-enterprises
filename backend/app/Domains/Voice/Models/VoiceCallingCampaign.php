<?php

namespace App\Domains\Voice\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceCallingCampaign extends Model
{
    protected $table = 'voice_calling_campaigns';

    protected $fillable = [
        'tenant_id',
        'voice_agent_id',
        'name',
        'description',
        'status',
        'target_filters',
        'max_retries',
        'retry_interval_hours',
        'calls_per_minute_limit',
        'total_contacts_queued',
        'total_contacts_called',
        'total_contacts_answered',
        'total_contacts_qualified',
        'scheduled_start_at',
        'completed_at',
    ];

    protected $casts = [
        'target_filters' => 'array',
        'max_retries' => 'integer',
        'retry_interval_hours' => 'integer',
        'calls_per_minute_limit' => 'integer',
        'total_contacts_queued' => 'integer',
        'total_contacts_called' => 'integer',
        'total_contacts_answered' => 'integer',
        'total_contacts_qualified' => 'integer',
        'scheduled_start_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(VoiceAiAgent::class, 'voice_agent_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(VoiceCall::class, 'campaign_id');
    }
}
