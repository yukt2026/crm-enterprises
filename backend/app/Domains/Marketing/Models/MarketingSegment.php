<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingSegment extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'type', // dynamic, static
        'target_entity', // lead, contact, customer
        'rules',
        'inclusion_ids',
        'exclusion_ids',
        'cached_count',
        'last_calculated_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'rules' => 'array',
        'inclusion_ids' => 'array',
        'exclusion_ids' => 'array',
        'last_calculated_at' => 'datetime',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class, 'segment_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
