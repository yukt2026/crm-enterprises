<?php

namespace App\Domains\CRM\Models;

use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'branches';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'city',
        'state',
        'country',
        'timezone',
        'is_active',
        'manager_user_id',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'pincode',
        'latitude',
        'longitude',
        'business_hours',
        'working_days',
        'connected_channels',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'business_hours' => 'array',
        'working_days' => 'array',
        'connected_channels' => 'array',
        'settings' => 'array',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'branch_departments', 'branch_id', 'department_id')
            ->withTimestamps();
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'branch_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'branch_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'branch_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'branch_id');
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'branch_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'branch_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'branch_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'branch_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'branch_id');
    }

    public function salesTargets(): HasMany
    {
        return $this->hasMany(BranchSalesTarget::class, 'branch_id');
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(BranchTransfer::class, 'from_branch_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(BranchTransfer::class, 'to_branch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
