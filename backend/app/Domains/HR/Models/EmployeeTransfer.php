<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTransfer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'from_department_id',
        'to_department_id',
        'from_designation',
        'to_designation',
        'from_manager_id',
        'to_manager_id',
        'transfer_date',
        'reason',
        'transferred_by_user_id',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_manager_id');
    }

    public function toManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_manager_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by_user_id');
    }
}
