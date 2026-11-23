<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'half_day_minutes',
        'full_day_minutes',
        'break_duration_minutes',
        'working_days',
        'is_default',
        'status',
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_default' => 'boolean',
        'grace_period_minutes' => 'integer',
        'half_day_minutes' => 'integer',
        'full_day_minutes' => 'integer',
        'break_duration_minutes' => 'integer',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
