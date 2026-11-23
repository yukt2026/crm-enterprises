<?php

namespace App\Domains\Sales\Models;

use App\Domains\Inventory\Models\ProductCategory;
use App\Domains\Inventory\Models\ProductStock;
use App\Domains\Inventory\Models\ProductVariant;
use App\Domains\Inventory\Models\StockMovement;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'type', // 'product' or 'service'
        'name',
        'sku',
        'category_id',
        'category',
        'brand',
        'unit',
        'price',
        'cost_price',
        'tax_rate',
        'hsn_sac_code',
        'barcode',
        'track_inventory',
        'allow_negative_stock',
        'service_duration',
        'service_delivery_type',
        'image_url',
        'status',
        'description',
        'pricing_rules',
        'attributes_schema',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'track_inventory' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'pricing_rules' => 'array',
        'attributes_schema' => 'array',
    ];

    protected $appends = [
        'total_stock',
        'available_stock',
    ];

    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getTotalStockAttribute(): float
    {
        if ($this->type === 'service' || !$this->track_inventory) {
            return 999999.0;
        }

        return (float) $this->stocks()->sum('current_stock');
    }

    public function getAvailableStockAttribute(): float
    {
        if ($this->type === 'service' || !$this->track_inventory) {
            return 999999.0;
        }

        $totalCurrent = (float) $this->stocks()->sum('current_stock');
        $totalReserved = (float) $this->stocks()->sum('reserved_stock');
        return max(0.0, $totalCurrent - $totalReserved);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeProducts(Builder $query): Builder
    {
        return $query->where('type', 'product');
    }

    public function scopeServices(Builder $query): Builder
    {
        return $query->where('type', 'service');
    }

    public function scopeTrackInventory(Builder $query): Builder
    {
        return $query->where('type', 'product')->where('track_inventory', true);
    }
}
