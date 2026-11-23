<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollBatch extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'payroll_period',
        'month',
        'year',
        'start_date',
        'end_date',
        'total_employees',
        'gross_amount',
        'total_deductions',
        'net_amount',
        'status',
        'approved_by_user_id',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'total_employees' => 'integer',
        'gross_amount' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'total_gross_pay',
        'total_net_pay',
    ];

    public function getTotalGrossPayAttribute()
    {
        return (float) $this->gross_amount;
    }

    public function getTotalNetPayAttribute()
    {
        return (float) $this->net_amount;
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
