<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'transfer_number',
        'status',
        'user_id',
        'approved_by',
        'approved_at',
        'dispatched_by',
        'dispatched_at',
        'received_by',
        'damaged_quantity',
        'damage_notes',
        'shipped_at',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'damaged_quantity' => 'decimal:2',
    ];

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }
}
