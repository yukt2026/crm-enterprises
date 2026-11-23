<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'employee_id',
        'user_id',
        'name',
        'profile_image',
        'email',
        'phone',
        'department',
        'department_id',
        'designation',
        'reporting_manager_id',
        'shift_id',
        'date_of_joining',
        'probation_end_date',
        'contract_end_date',
        'notice_period_days',
        'salary',
        'salary_structure_id',
        'status',
        'employment_type',
        'work_location',
        'emergency_contact',
        'skills',
        'documents',
        'pan_number',
        'aadhaar_number',
        'uan_number',
        'esi_number',
        'bank_account_number',
        'bank_name',
        'bank_ifsc',
        'notes',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'probation_end_date' => 'date',
        'contract_end_date' => 'date',
        'notice_period_days' => 'integer',
        'salary' => 'decimal:2',
        'emergency_contact' => 'array',
        'skills' => 'array',
        'documents' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departmentRel(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'reporting_manager_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function attendanceCorrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(EmployeeTransfer::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function currentSalary()
    {
        return $this->hasOne(EmployeeSalary::class)->where('is_current', true);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(EmployeePerformanceReview::class);
    }

    public function productivityGoals(): HasMany
    {
        return $this->hasMany(EmployeeProductivityGoal::class);
    }
}
