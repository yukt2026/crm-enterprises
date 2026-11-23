<?php

namespace App\Domains\CRM\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'pipeline_id',
        'name',
        'code',
        'probability',
        'order_column',
        'color',
        'is_won',
        'is_lost',
        'is_default',
        'requires_approval',
        'allowed_transitions',
        'required_fields',
        'stale_days_threshold',
    ];

    protected $casts = [
        'probability' => 'integer',
        'order_column' => 'integer',
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
        'is_default' => 'boolean',
        'requires_approval' => 'boolean',
        'allowed_transitions' => 'array',
        'required_fields' => 'array',
        'stale_days_threshold' => 'integer',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function scopeWon(Builder $query): Builder
    {
        return $query->where('is_won', true);
    }

    public function scopeLost(Builder $query): Builder
    {
        return $query->where('is_lost', true);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_won', false)->where('is_lost', false);
    }
}
