<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryStructure extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'basic_percentage',
        'hra_percentage',
        'da_percentage',
        'conveyance_allowance',
        'medical_allowance',
        'special_allowance',
        'pf_enabled',
        'pf_employee_percent',
        'pf_employer_percent',
        'pf_wage_ceiling',
        'esi_enabled',
        'esi_employee_percent',
        'esi_employer_percent',
        'esi_gross_ceiling',
        'professional_tax_enabled',
        'pt_slabs',
        'tds_mode',
        'rules',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'basic_percentage' => 'decimal:2',
        'hra_percentage' => 'decimal:2',
        'da_percentage' => 'decimal:2',
        'conveyance_allowance' => 'decimal:2',
        'medical_allowance' => 'decimal:2',
        'special_allowance' => 'decimal:2',
        'pf_enabled' => 'boolean',
        'pf_employee_percent' => 'decimal:2',
        'pf_employer_percent' => 'decimal:2',
        'pf_wage_ceiling' => 'decimal:2',
        'esi_enabled' => 'boolean',
        'esi_employee_percent' => 'decimal:2',
        'esi_employer_percent' => 'decimal:2',
        'esi_gross_ceiling' => 'decimal:2',
        'professional_tax_enabled' => 'boolean',
        'pt_slabs' => 'array',
        'rules' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function employeeSalaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }
}
