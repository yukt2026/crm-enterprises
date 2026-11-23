<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'days_allowed_per_year',
        'is_paid',
        'requires_attachment',
        'carry_forward_days',
        'status',
    ];

    protected $casts = [
        'days_allowed_per_year' => 'decimal:2',
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'carry_forward_days' => 'integer',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }
}
