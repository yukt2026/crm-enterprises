<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Sales\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_return_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'tax_rate',
        'restock_warehouse_id',
        'is_restocked',
        'is_damaged',
        'condition_notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_restocked' => 'boolean',
        'is_damaged' => 'boolean',
    ];

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function restockWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'restock_warehouse_id');
    }
}
