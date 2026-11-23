<?php

namespace App\Domains\Finance\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'discount_rules';

    protected $fillable = [
        'tenant_id',
        'role_name',
        'max_discount_percentage',
        'min_deal_value',
        'requires_manager_approval',
        'requires_admin_approval',
        'is_active',
    ];

    protected $casts = [
        'max_discount_percentage' => 'decimal:2',
        'min_deal_value' => 'decimal:2',
        'requires_manager_approval' => 'boolean',
        'requires_admin_approval' => 'boolean',
        'is_active' => 'boolean',
    ];
}
