<?php

namespace App\Domains\Finance\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstEntityVerification extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'gstin',
        'legal_name',
        'trade_name',
        'state_code',
        'state_name',
        'registered_address',
        'registration_status',
        'registration_type',
        'is_verified',
        'validation_source',
        'raw_response',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'raw_response' => 'array',
        'verified_at' => 'datetime',
    ];
}
