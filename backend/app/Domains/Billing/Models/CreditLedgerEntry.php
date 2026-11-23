<?php

namespace App\Domains\Billing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditLedgerEntry extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'entry_id',
        'tenant_id',
        'entry_type',
        'amount',
        'balance_after',
        'reference_id',
        'description',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Enforce immutability: prevent updating or deleting ledger records
     */
    protected static function booted()
    {
        static::updating(function () {
            throw new \RuntimeException('Credit ledger entries are strictly immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Credit ledger entries are strictly immutable and cannot be deleted.');
        });
    }
}
