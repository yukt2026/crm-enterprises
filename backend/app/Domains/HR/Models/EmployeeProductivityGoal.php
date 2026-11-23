<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProductivityGoal extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'metric_name',
        'target_value',
        'achieved_value',
        'period_start',
        'period_end',
        'status',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'achieved_value' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    protected $appends = ['current_value', 'start_date', 'target_date'];

    public function getCurrentValueAttribute()
    {
        return (float) $this->achieved_value;
    }

    public function getStartDateAttribute()
    {
        return $this->period_start?->toDateString();
    }

    public function getTargetDateAttribute()
    {
        return $this->period_end?->toDateString();
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
