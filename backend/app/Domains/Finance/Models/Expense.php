<?php

namespace App\Domains\Finance\Models;

use App\Domains\Inventory\Models\Supplier;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'expenses';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'customer_id',
        'expense_number',
        'title',
        'description',
        'category', // travel, office_supplies, software_saas, marketing, utilities, professional_services, meals_entertainment, other
        'supplier_id',
        'expense_date',
        'amount',
        'tax_amount',
        'is_tax_inclusive',
        'currency',
        'receipt_url',
        'is_reimbursement',
        'employee_user_id',
        'status', // draft, pending_approval, approved, rejected, reimbursed, paid
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'branch_id',
        'department_id',
        'payment_method',
        'payment_reference',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'is_tax_inclusive' => 'boolean',
        'is_reimbursement' => 'boolean',
        'expense_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
