<?php

namespace App\Domains\Billing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageReservation extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'reservation_id',
        'tenant_id',
        'meter_type',
        'estimated_units',
        'reserved_credit_amount',
        'status',
        'expires_at',
        'settled_at',
    ];

    protected $casts = [
        'estimated_units' => 'decimal:4',
        'reserved_credit_amount' => 'decimal:4',
        'expires_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
