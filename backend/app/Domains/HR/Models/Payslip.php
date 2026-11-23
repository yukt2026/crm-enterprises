<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'payroll_batch_id',
        'employee_id',
        'payslip_number',
        'working_days',
        'present_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'loss_of_pay_days',
        'overtime_hours',
        'overtime_amount',
        'basic_salary',
        'hra',
        'conveyance',
        'special_allowance',
        'incentives',
        'commission_amount',
        'reimbursements_amount',
        'gross_earnings',
        'pf_employee',
        'esi_employee',
        'professional_tax',
        'tds_deduction',
        'other_deductions',
        'total_deductions',
        'pf_employer',
        'esi_employer',
        'net_pay',
        'payment_status',
        'payment_method',
        'payment_reference',
        'paid_at',
        'calculation_details',
        'is_full_and_final',
    ];

    protected $casts = [
        'working_days' => 'decimal:1',
        'present_days' => 'decimal:1',
        'paid_leave_days' => 'decimal:1',
        'unpaid_leave_days' => 'decimal:1',
        'loss_of_pay_days' => 'decimal:1',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'basic_salary' => 'decimal:2',
        'hra' => 'decimal:2',
        'conveyance' => 'decimal:2',
        'special_allowance' => 'decimal:2',
        'incentives' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'reimbursements_amount' => 'decimal:2',
        'gross_earnings' => 'decimal:2',
        'pf_employee' => 'decimal:2',
        'esi_employee' => 'decimal:2',
        'professional_tax' => 'decimal:2',
        'tds_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'pf_employer' => 'decimal:2',
        'esi_employer' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'paid_at' => 'datetime',
        'calculation_details' => 'array',
        'is_full_and_final' => 'boolean',
    ];

    public function payrollBatch(): BelongsTo
    {
        return $this->belongsTo(PayrollBatch::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
