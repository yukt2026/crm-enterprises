<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Sales\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReconciliationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_reconciliation_id',
        'product_id',
        'product_variant_id',
        'system_quantity',
        'physical_quantity',
        'variance_quantity',
        'unit_cost',
        'variance_cost',
        'reconciliation_action', // adjusted, flagged, ignored
        'notes',
    ];

    protected $casts = [
        'system_quantity' => 'decimal:2',
        'physical_quantity' => 'decimal:2',
        'variance_quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'variance_cost' => 'decimal:2',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(StockReconciliation::class, 'stock_reconciliation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
