<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReconciliation extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reconciliation_number',
        'warehouse_id',
        'counted_by',
        'reconciled_by',
        'status', // in_progress, completed, cancelled
        'total_variance_cost',
        'notes',
        'reconciled_at',
    ];

    protected $casts = [
        'total_variance_cost' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReconciliationItem::class);
    }
}
