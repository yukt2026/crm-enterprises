<?php

namespace App\Domains\Sales\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionPlan extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'commission_plans';

    protected $fillable = [
        'tenant_id',
        'name',
        'plan_type', // fixed, percentage, tiered
        'calculation_basis', // deal_won, payment_collected
        'rate_percentage',
        'fixed_amount',
        'tier_rules', // [{"min": 0, "max": 100000, "rate": 5}, {"min": 100000, "rate": 8}]
        'target_quota',
        'is_active',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'rate_percentage' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'target_quota' => 'decimal:2',
        'tier_rules' => 'array',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function commissions(): HasMany
    {
        return $this->hasMany(SalesCommission::class);
    }
}
