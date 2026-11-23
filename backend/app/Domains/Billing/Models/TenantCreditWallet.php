<?php

namespace App\Domains\Billing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantCreditWallet extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'balance',
        'reserved_balance',
        'currency',
        'last_recharged_at',
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'reserved_balance' => 'decimal:4',
        'last_recharged_at' => 'datetime',
    ];

    protected $appends = [
        'available_balance',
    ];

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class, 'tenant_id', 'tenant_id');
    }

    public function getAvailableBalanceAttribute(): float
    {
        return max(0.0, (float) ($this->balance - $this->reserved_balance));
    }
}
