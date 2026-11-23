<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'billing_cycle',
        'price',
        'currency',
        'trial_days',
        'grace_period_days',
        'features',
        'limits',
        'included_allowance',
        'overage_pricing',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'trial_days' => 'integer',
        'grace_period_days' => 'integer',
        'features' => 'array',
        'limits' => 'array',
        'included_allowance' => 'array',
        'overage_pricing' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }
}
