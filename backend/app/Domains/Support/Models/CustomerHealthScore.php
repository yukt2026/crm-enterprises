<?php

namespace App\Domains\Support\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerHealthScore extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'health_score',
        'health_status', // healthy, neutral, at_risk, critical
        'churn_risk_level', // low, medium, high
        'onboarding_status', // not_started, in_progress, completed, stalled
        'renewal_date',
        'contract_value',
        'factors',
        'success_owner_id',
        'last_check_in_date',
        'next_check_in_date',
        'check_in_notes',
    ];

    protected $casts = [
        'health_score' => 'decimal:2',
        'contract_value' => 'decimal:2',
        'factors' => 'array',
        'renewal_date' => 'date',
        'last_check_in_date' => 'date',
        'next_check_in_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function successOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'success_owner_id');
    }
}
