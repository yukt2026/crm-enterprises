<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'inventory_settings';

    protected $fillable = [
        'tenant_id',
        'is_inventory_enabled',
        'valuation_method', // moving_average, fifo, standard
        'default_warehouse_id',
        'allow_negative_stock',
        'auto_reserve_on_order',
        'auto_deduct_on_invoice',
        'low_stock_notification',
        'default_reorder_lead_days',
    ];

    protected $casts = [
        'is_inventory_enabled' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'auto_reserve_on_order' => 'boolean',
        'auto_deduct_on_invoice' => 'boolean',
        'low_stock_notification' => 'boolean',
        'default_reorder_lead_days' => 'integer',
    ];

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }
}
