<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'salary_structure_id',
        'monthly_ctc',
        'annual_ctc',
        'effective_date',
        'basic_amount',
        'hra_amount',
        'conveyance_amount',
        'special_allowance_amount',
        'pf_employee_amount',
        'pf_employer_amount',
        'esi_employee_amount',
        'esi_employer_amount',
        'professional_tax_amount',
        'tds_amount',
        'gross_salary',
        'total_deductions',
        'net_salary',
        'is_current',
    ];

    protected $casts = [
        'monthly_ctc' => 'decimal:2',
        'annual_ctc' => 'decimal:2',
        'effective_date' => 'date',
        'basic_amount' => 'decimal:2',
        'hra_amount' => 'decimal:2',
        'conveyance_amount' => 'decimal:2',
        'special_allowance_amount' => 'decimal:2',
        'pf_employee_amount' => 'decimal:2',
        'pf_employer_amount' => 'decimal:2',
        'esi_employee_amount' => 'decimal:2',
        'esi_employer_amount' => 'decimal:2',
        'professional_tax_amount' => 'decimal:2',
        'tds_amount' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'is_current' => 'boolean',
    ];

    protected $appends = [
        'basic_salary',
        'hra',
        'monthly_net_take_home',
    ];

    public function getBasicSalaryAttribute()
    {
        return (float) $this->basic_amount;
    }

    public function getHraAttribute()
    {
        return (float) $this->hra_amount;
    }

    public function getMonthlyNetTakeHomeAttribute()
    {
        return (float) $this->net_salary;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }
}
