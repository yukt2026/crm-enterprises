<?php

namespace App\Domains\Billing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reservation_id',
        'meter_type',
        'units',
        'tenant_charge_amount',
        'provider_cost_amount',
        'is_overage',
        'billing_period_start',
        'billing_period_end',
        'metadata',
    ];

    protected $casts = [
        'units' => 'decimal:4',
        'tenant_charge_amount' => 'decimal:4',
        'provider_cost_amount' => 'decimal:4',
        'is_overage' => 'boolean',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'metadata' => 'array',
    ];
}
