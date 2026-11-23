<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'shift_id',
        'date',
        'check_in',
        'check_out',
        'break_start',
        'break_end',
        'total_work_minutes',
        'total_break_minutes',
        'status',
        'is_late',
        'late_minutes',
        'is_early_departure',
        'early_minutes',
        'overtime_minutes',
        'is_half_day',
        'ip_address',
        'location',
        'latitude',
        'longitude',
        'accuracy_meters',
        'is_within_geofence',
        'selfie_url',
        'verification_method',
        'is_field_checkin',
        'branch_id',
        'notes',
        'is_locked',
        'approved_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'total_work_minutes' => 'integer',
        'total_break_minutes' => 'integer',
        'is_late' => 'boolean',
        'late_minutes' => 'integer',
        'is_early_departure' => 'boolean',
        'early_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'is_half_day' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy_meters' => 'decimal:2',
        'is_within_geofence' => 'boolean',
        'is_field_checkin' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }
}
