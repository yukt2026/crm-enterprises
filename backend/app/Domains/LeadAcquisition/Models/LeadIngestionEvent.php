<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadIngestionEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'lead_source_id',
        'provider',
        'event_id',
        'status',
        'raw_payload',
        'normalized_payload',
        'lead_id',
        'error_message',
        'retry_count',
        'max_retries',
        'ip_address',
        'user_agent',
        'processed_at',
    ];

    protected $casts = [
        'normalized_payload' => 'array',
        'processed_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeCanRetry(Builder $query): Builder
    {
        return $query->where('status', 'failed')->whereColumn('retry_count', '<', 'max_retries');
    }
}
