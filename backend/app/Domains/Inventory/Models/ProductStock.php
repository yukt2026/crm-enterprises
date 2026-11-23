<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Sales\Models\Product;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStock extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_variant_id',
        'warehouse_id',
        'current_stock',
        'reserved_stock',
        'incoming_stock',
        'minimum_stock',
        'damaged_stock',
        'returned_stock',
        'reorder_level',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'reserved_stock' => 'decimal:2',
        'incoming_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'damaged_stock' => 'decimal:2',
        'returned_stock' => 'decimal:2',
        'reorder_level' => 'decimal:2',
    ];

    protected $appends = [
        'available_stock',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getAvailableStockAttribute(): float
    {
        return max(0.0, (float) ($this->current_stock - $this->reserved_stock));
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('current_stock', '<=', 'minimum_stock')
            ->where('minimum_stock', '>', 0);
    }
}
