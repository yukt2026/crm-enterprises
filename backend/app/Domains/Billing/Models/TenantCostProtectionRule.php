<?php

namespace App\Domains\Billing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantCostProtectionRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'daily_spending_cap',
        'monthly_spending_cap',
        'ai_provider_spending_cap',
        'auto_pause_on_breach',
        'low_balance_alert_threshold',
        'is_ai_calling_unlimited_allowed',
        'is_paused',
        'pause_reason',
    ];

    protected $casts = [
        'daily_spending_cap' => 'decimal:2',
        'monthly_spending_cap' => 'decimal:2',
        'ai_provider_spending_cap' => 'decimal:2',
        'auto_pause_on_breach' => 'boolean',
        'low_balance_alert_threshold' => 'decimal:2',
        'is_ai_calling_unlimited_allowed' => 'boolean',
        'is_paused' => 'boolean',
    ];
}
