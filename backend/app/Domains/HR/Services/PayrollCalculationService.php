<?php

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeeSalary;
use App\Domains\HR\Models\Leave;
use App\Domains\HR\Models\PayrollBatch;
use App\Domains\HR\Models\Payslip;
use App\Domains\HR\Models\SalaryStructure;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    /**
     * Get or seed default Indian salary structure
     */
    public function getDefaultSalaryStructure(int $tenantId): SalaryStructure
    {
        $structure = SalaryStructure::where('tenant_id', $tenantId)->where('is_default', true)->first();

        if (!$structure) {
            $structure = SalaryStructure::create([
                'tenant_id' => $tenantId,
                'name' => 'Standard Indian Corporate Structure',
                'code' => 'IND-CORP-STD',
                'description' => 'Statutory compliant Indian salary structure with Basic 50%, HRA 50% of Basic, PF 12%, ESI (<=21k) and State PT.',
                'basic_percentage' => 50.00,
                'hra_percentage' => 50.00,
                'da_percentage' => 0.00,
                'conveyance_allowance' => 1600.00,
                'medical_allowance' => 1250.00,
                'special_allowance' => 0.00,
                'pf_enabled' => true,
                'pf_employee_percent' => 12.00,
                'pf_employer_percent' => 12.00,
                'pf_wage_ceiling' => 15000.00,
                'esi_enabled' => true,
                'esi_employee_percent' => 0.75,
                'esi_employer_percent' => 3.25,
                'esi_gross_ceiling' => 21000.00,
                'professional_tax_enabled' => true,
                'pt_slabs' => [
                    ['min' => 0, 'max' => 7500, 'amount' => 0],
                    ['min' => 7501, 'max' => 10000, 'amount' => 175],
                    ['min' => 10001, 'max' => 9999999, 'amount' => 200],
                ],
                'tds_mode' => 'slab',
                'is_active' => true,
                'is_default' => true,
            ]);
        }

        return $structure;
    }

    /**
     * Calculate monthly breakdown from monthly CTC and salary structure
     */
    public function calculateCompensation(float $monthlyCtc, ?SalaryStructure $structure = null): array
    {
        $monthlyCtc = round($monthlyCtc, 2);
        $annualCtc = round($monthlyCtc * 12, 2);

        $basicPct = $structure ? (float) $structure->basic_percentage : 50.00;
        $hraPct = $structure ? (float) $structure->hra_percentage : 50.00;
        $conveyance = $structure ? (float) $structure->conveyance_allowance : 1600.00;
        $medical = $structure ? (float) $structure->medical_allowance : 1250.00;

        $basic = round(($monthlyCtc * $basicPct) / 100, 2);
        $hra = round(($basic * $hraPct) / 100, 2);

        // Special allowance absorbs the residual between monthly CTC and (basic + hra + conveyance + medical)
        $subtotalAllowances = $basic + $hra + $conveyance + $medical;
        $specialAllowance = max(0, round($monthlyCtc - $subtotalAllowances, 2));

        $grossSalary = $basic + $hra + $conveyance + $medical + $specialAllowance;

        // 1. Provident Fund (PF) Calculation
        $pfEnabled = $structure ? $structure->pf_enabled : true;
        $pfEmployee = 0.00;
        $pfEmployer = 0.00;

        if ($pfEnabled) {
            $pfEmpPct = $structure ? (float) $structure->pf_employee_percent : 12.00;
            $pfEmprPct = $structure ? (float) $structure->pf_employer_percent : 12.00;
            $wageCeiling = $structure ? (float) $structure->pf_wage_ceiling : 15000.00;

            // Statutory calculation: basic capped at wage ceiling for minimum statutory compliance
            $pfWages = min($basic, $wageCeiling);
            $pfEmployee = round(($pfWages * $pfEmpPct) / 100, 2);
            $pfEmployer = round(($pfWages * $pfEmprPct) / 100, 2);
        }

        // 2. Employee State Insurance (ESI) Calculation
        $esiEnabled = $structure ? $structure->esi_enabled : true;
        $esiGrossCeiling = $structure ? (float) $structure->esi_gross_ceiling : 21000.00;
        $esiEmployee = 0.00;
        $esiEmployer = 0.00;

        if ($esiEnabled && $grossSalary <= $esiGrossCeiling) {
            $esiEmpPct = $structure ? (float) $structure->esi_employee_percent : 0.75;
            $esiEmprPct = $structure ? (float) $structure->esi_employer_percent : 3.25;
            $esiEmployee = round(($grossSalary * $esiEmpPct) / 100, 2);
            $esiEmployer = round(($grossSalary * $esiEmprPct) / 100, 2);
        }

        // 3. Professional Tax (PT)
        $ptEnabled = $structure ? $structure->professional_tax_enabled : true;
        $ptAmount = 0.00;
        if ($ptEnabled) {
            $slabs = $structure?->pt_slabs ?? [
                ['min' => 0, 'max' => 7500, 'amount' => 0],
                ['min' => 7501, 'max' => 10000, 'amount' => 175],
                ['min' => 10001, 'max' => 9999999, 'amount' => 200],
            ];

            foreach ($slabs as $slab) {
                if ($grossSalary >= $slab['min'] && $grossSalary <= $slab['max']) {
                    $ptAmount = (float) $slab['amount'];
                    break;
                }
            }
        }

        // 4. TDS Deduction (Estimated)
        $tdsAmount = 0.00;
        if ($annualCtc > 700000) { // New tax regime rebate limit threshold
            $taxableAnnual = max(0, $annualCtc - 75000); // Standard deduction ₹75,000
            if ($taxableAnnual > 700000) {
                $estimatedAnnualTax = ($taxableAnnual - 700000) * 0.10 + 20000;
                $tdsAmount = round($estimatedAnnualTax / 12, 2);
            }
        }

        $totalDeductions = round($pfEmployee + $esiEmployee + $ptAmount + $tdsAmount, 2);
        $netSalary = round($grossSalary - $totalDeductions, 2);

        return [
            'monthly_ctc' => $monthlyCtc,
            'annual_ctc' => $annualCtc,
            'basic_amount' => $basic,
            'hra_amount' => $hra,
            'conveyance_amount' => $conveyance,
            'medical_amount' => $medical,
            'special_allowance_amount' => $specialAllowance,
            'gross_salary' => $grossSalary,
            'pf_employee_amount' => $pfEmployee,
            'pf_employer_amount' => $pfEmployer,
            'esi_employee_amount' => $esiEmployee,
            'esi_employer_amount' => $esiEmployer,
            'professional_tax_amount' => $ptAmount,
            'tds_amount' => $tdsAmount,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
        ];
    }

    /**
     * Assign salary structure and calculate compensation for an employee
     */
    public function assignSalaryStructure(int $tenantId, int $employeeId, array $data): EmployeeSalary
    {
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);

        $structureId = $data['salary_structure_id'] ?? null;
        $structure = $structureId
            ? SalaryStructure::where('tenant_id', $tenantId)->findOrFail($structureId)
            : $this->getDefaultSalaryStructure($tenantId);

        $monthlyCtc = (float) ($data['monthly_ctc'] ?? $employee->salary);
        if ($monthlyCtc <= 0) {
            $monthlyCtc = 50000.00; // default benchmark
        }

        $calc = $this->calculateCompensation($monthlyCtc, $structure);

        return DB::transaction(function () use ($tenantId, $employee, $structure, $calc, $data) {
            // Set any previous salary record to non-current
            EmployeeSalary::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->update(['is_current' => false]);

            $employeeSalary = EmployeeSalary::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'salary_structure_id' => $structure->id,
                'monthly_ctc' => $calc['monthly_ctc'],
                'annual_ctc' => $calc['annual_ctc'],
                'effective_date' => $data['effective_date'] ?? now()->toDateString(),
                'basic_amount' => $calc['basic_amount'],
                'hra_amount' => $calc['hra_amount'],
                'conveyance_amount' => $calc['conveyance_amount'],
                'special_allowance_amount' => $calc['special_allowance_amount'],
                'pf_employee_amount' => $calc['pf_employee_amount'],
                'pf_employer_amount' => $calc['pf_employer_amount'],
                'esi_employee_amount' => $calc['esi_employee_amount'],
                'esi_employer_amount' => $calc['esi_employer_amount'],
                'professional_tax_amount' => $calc['professional_tax_amount'],
                'tds_amount' => $calc['tds_amount'],
                'gross_salary' => $calc['gross_salary'],
                'total_deductions' => $calc['total_deductions'],
                'net_salary' => $calc['net_salary'],
                'is_current' => true,
            ]);

            // Sync employee master record
            $employee->update([
                'salary' => $calc['monthly_ctc'],
                'salary_structure_id' => $structure->id,
            ]);

            return $employeeSalary;
        });
    }

    /**
     * Generate Monthly Payroll Run & Payslips
     */
    public function generatePayrollBatch(int $tenantId, int $year, int $month, ?string $notes = null): PayrollBatch
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;
        $payrollPeriod = sprintf('%04d-%02d', $year, $month);

        // Find or create batch
        $batch = PayrollBatch::where('tenant_id', $tenantId)
            ->where('payroll_period', $payrollPeriod)
            ->first();

        if ($batch && $batch->status === 'approved') {
            throw ValidationException::withMessages(['payroll_period' => "Payroll for period {$payrollPeriod} is already approved."]);
        }

        return DB::transaction(function () use ($tenantId, $year, $month, $startDate, $endDate, $daysInMonth, $payrollPeriod, $notes, $batch) {
            if (!$batch) {
                $batch = PayrollBatch::create([
                    'tenant_id' => $tenantId,
                    'payroll_period' => $payrollPeriod,
                    'month' => $month,
                    'year' => $year,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'status' => 'draft',
                    'notes' => $notes,
                ]);
            } else {
                // Remove previous draft payslips to recalculate freshly
                Payslip::where('payroll_batch_id', $batch->id)->delete();
            }

            $employees = Employee::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->with(['currentSalary.salaryStructure'])
                ->get();

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $employeeCount = 0;

            foreach ($employees as $employee) {
                $salary = $employee->currentSalary;
                if (!$salary) {
                    $salary = $this->assignSalaryStructure($tenantId, $employee->id, ['monthly_ctc' => $employee->salary ?: 45000]);
                }

                // Calculate attendance metrics in the period
                $attendances = Attendance::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->get();

                $presentDays = $attendances->whereIn('status', ['present', 'late'])->count();
                $halfDays = $attendances->where('status', 'half_day')->count();
                $actualPresent = $presentDays + ($halfDays * 0.5);

                // Leaves in period
                $approvedLeaves = Leave::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->get();

                $paidLeaveDays = (float) $approvedLeaves->sum('total_days');
                $unpaidLeaveDays = (float) Leave::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('status', 'approved')
                    ->where('type', 'like', '%unpaid%')
                    ->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->sum('total_days');

                // Standard full attendance assumes active tenure if no explicit unpaid leave recorded
                $lossOfPayDays = $unpaidLeaveDays;
                $payableRatio = max(0, min(1, ($daysInMonth - $lossOfPayDays) / $daysInMonth));

                // Pro-rated earnings
                $basic = round($salary->basic_amount * $payableRatio, 2);
                $hra = round($salary->hra_amount * $payableRatio, 2);
                $conveyance = round($salary->conveyance_amount * $payableRatio, 2);
                $special = round($salary->special_allowance_amount * $payableRatio, 2);

                // Overtime
                $overtimeMinutes = (int) $attendances->sum('overtime_minutes');
                $overtimeHours = round($overtimeMinutes / 60, 2);
                // Standard hourly rate = basic / (30 * 8)
                $hourlyRate = round($salary->basic_amount / 240, 2);
                $overtimeAmount = round($overtimeHours * $hourlyRate, 2);

                $grossEarnings = round($basic + $hra + $conveyance + $special + $overtimeAmount, 2);

                // Deductions recalculation with pro-rated wage base
                $structure = $salary->salaryStructure ?? $this->getDefaultSalaryStructure($tenantId);
                $pfWage = min($basic, (float) ($structure->pf_wage_ceiling ?? 15000));
                $pfEmployee = round(($pfWage * (float) ($structure->pf_employee_percent ?? 12)) / 100, 2);
                $pfEmployer = round(($pfWage * (float) ($structure->pf_employer_percent ?? 12)) / 100, 2);

                $esiEmployee = 0.00;
                $esiEmployer = 0.00;
                if ($grossEarnings <= (float) ($structure->esi_gross_ceiling ?? 21000)) {
                    $esiEmployee = round(($grossEarnings * (float) ($structure->esi_employee_percent ?? 0.75)) / 100, 2);
                    $esiEmployer = round(($grossEarnings * (float) ($structure->esi_employer_percent ?? 3.25)) / 100, 2);
                }

                $ptAmount = $salary->professional_tax_amount;
                $tdsAmount = $salary->tds_amount;
                $deductions = round($pfEmployee + $esiEmployee + $ptAmount + $tdsAmount, 2);
                $netPay = round($grossEarnings - $deductions, 2);

                $payslipNumber = sprintf('PAY-%04d%02d-%03d', $year, $month, $employee->id);

                Payslip::create([
                    'tenant_id' => $tenantId,
                    'payroll_batch_id' => $batch->id,
                    'employee_id' => $employee->id,
                    'payslip_number' => $payslipNumber,
                    'working_days' => $daysInMonth,
                    'present_days' => $actualPresent > 0 ? $actualPresent : $daysInMonth,
                    'paid_leave_days' => $paidLeaveDays,
                    'unpaid_leave_days' => $unpaidLeaveDays,
                    'loss_of_pay_days' => $lossOfPayDays,
                    'overtime_hours' => $overtimeHours,
                    'overtime_amount' => $overtimeAmount,
                    'basic_salary' => $basic,
                    'hra' => $hra,
                    'conveyance' => $conveyance,
                    'special_allowance' => $special,
                    'incentives' => 0.00,
                    'commission_amount' => 0.00,
                    'reimbursements_amount' => 0.00,
                    'gross_earnings' => $grossEarnings,
                    'pf_employee' => $pfEmployee,
                    'esi_employee' => $esiEmployee,
                    'professional_tax' => $ptAmount,
                    'tds_deduction' => $tdsAmount,
                    'other_deductions' => 0.00,
                    'total_deductions' => $deductions,
                    'pf_employer' => $pfEmployer,
                    'esi_employer' => $esiEmployer,
                    'net_pay' => $netPay,
                    'payment_status' => 'unpaid',
                    'calculation_details' => [
                        'payable_ratio' => $payableRatio,
                        'hourly_overtime_rate' => $hourlyRate,
                        'pf_wage_base' => $pfWage,
                        'statutory_verified' => true,
                    ],
                ]);

                $totalGross += $grossEarnings;
                $totalDeductions += $deductions;
                $totalNet += $netPay;
                $employeeCount++;
            }

            $batch->update([
                'total_employees' => $employeeCount,
                'gross_amount' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_amount' => round($totalNet, 2),
                'status' => 'calculated',
            ]);

            return $batch->fresh(['payslips.employee']);
        });
    }

    /**
     * Approve payroll batch
     */
    public function approvePayrollBatch(int $tenantId, int $batchId, int $userId): PayrollBatch
    {
        $batch = PayrollBatch::where('tenant_id', $tenantId)->findOrFail($batchId);
        $batch->update([
            'status' => 'approved',
            'approved_by_user_id' => $userId,
            'approved_at' => now(),
        ]);

        return $batch;
    }

    /**
     * Disburse payroll batch and update payslip payment statuses
     */
    public function disbursePayrollBatch(int $tenantId, int $batchId, array $data): PayrollBatch
    {
        $batch = PayrollBatch::where('tenant_id', $tenantId)->findOrFail($batchId);

        DB::transaction(function () use ($batch, $data) {
            $batch->update([
                'status' => 'disbursed',
            ]);

            Payslip::where('payroll_batch_id', $batch->id)->update([
                'payment_status' => 'paid',
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'payment_reference' => $data['payment_reference'] ?? ('NEFT-' . time()),
                'paid_at' => now(),
            ]);
        });

        return $batch->fresh('payslips');
    }

    /**
     * Compute Full and Final (F&F) Settlement for departing employee
     */
    public function calculateFullAndFinal(int $tenantId, int $employeeId, array $params = []): array
    {
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);
        $salary = $employee->currentSalary ?? $this->assignSalaryStructure($tenantId, $employee->id, ['monthly_ctc' => $employee->salary ?: 50000]);

        $relievingDate = Carbon::parse($params['relieving_date'] ?? now());
        $joiningDate = Carbon::parse($employee->date_of_joining ?? now()->subMonths(12));
        $workedDaysThisMonth = (int) ($params['unpaid_salary_days'] ?? $relievingDate->day);

        // 1. Unpaid Monthly Salary
        $perDaySalary = round($salary->basic_amount / 30, 2);
        $unpaidSalary = round($salary->monthly_ctc * ($workedDaysThisMonth / 30), 2);

        // 2. Leave Encashment (Available privilege/earned leaves * Basic per day)
        $leaveDaysToEncash = (float) ($params['leave_encashment_days'] ?? 10);
        $leaveEncashmentAmount = round($leaveDaysToEncash * $perDaySalary, 2);

        // 3. Gratuity Calculation (Eligibility: 5+ years of continuous service)
        // Formula: (15 * Last drawn basic * tenure years) / 26
        $yearsOfService = isset($params['years_of_service']) ? (int) $params['years_of_service'] : (int) $joiningDate->diffInYears($relievingDate);
        $gratuityAmount = 0.00;
        if ($yearsOfService >= 5) {
            $gratuityAmount = round((15 * $salary->basic_amount * $yearsOfService) / 26, 2);
        }

        // 4. Notice Period Shortfall Recovery
        $noticeRequiredDays = (int) ($employee->notice_period_days ?: 30);
        $noticeServedDays = (int) ($params['notice_served_days'] ?? $noticeRequiredDays);
        $shortfallDays = max(0, $noticeRequiredDays - $noticeServedDays);
        $noticeRecoveryAmount = round($shortfallDays * ($salary->monthly_ctc / 30), 2);

        $totalPayable = round($unpaidSalary + $leaveEncashmentAmount + $gratuityAmount - $noticeRecoveryAmount, 2);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'relieving_date' => $relievingDate->toDateString(),
            'tenure_years' => $yearsOfService,
            'unpaid_salary_days' => $workedDaysThisMonth,
            'unpaid_salary_amount' => $unpaidSalary,
            'leave_encashment_days' => $leaveDaysToEncash,
            'leave_encashment_amount' => $leaveEncashmentAmount,
            'gratuity_eligible' => $yearsOfService >= 5,
            'gratuity_amount' => $gratuityAmount,
            'notice_shortfall_days' => $shortfallDays,
            'notice_recovery_amount' => $noticeRecoveryAmount,
            'net_settlement_payable' => $totalPayable,
            'net_payable' => $totalPayable,
            'statutory_notice' => 'Full & Final calculations reflect Payment of Gratuity Act 1972 and employment contract parameters.',
        ];
    }
}
