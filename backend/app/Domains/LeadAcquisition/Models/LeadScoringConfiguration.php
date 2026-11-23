<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadScoringConfiguration extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'hot_threshold',
        'warm_threshold',
        'weights',
        'is_active',
    ];

    protected $casts = [
        'hot_threshold' => 'integer',
        'warm_threshold' => 'integer',
        'weights' => 'array',
        'is_active' => 'boolean',
    ];
}
