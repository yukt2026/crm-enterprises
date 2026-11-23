<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'provider',
        'name',
        'account_id',
        'status',
        'credentials',
        'config',
        'sync_status',
        'last_synced_at',
        'last_error',
        'created_by',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'config' => 'array',
        'last_synced_at' => 'datetime',
    ];

    // Hide sensitive provider tokens from standard serialization
    protected $hidden = [
        'credentials',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(LeadForm::class, 'lead_source_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(LeadFieldMapping::class, 'lead_source_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadIngestionEvent::class, 'lead_source_id');
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(AdCampaignMetric::class, 'lead_source_id');
    }

    // Scopes
    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', 'connected');
    }

    public function scopeForWorkspace(Builder $query, int $workspaceId): Builder
    {
        return $query->where('workspace_id', $workspaceId);
    }
}
