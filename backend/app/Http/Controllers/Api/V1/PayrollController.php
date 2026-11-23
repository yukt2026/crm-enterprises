<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\PayrollBatch;
use App\Domains\HR\Models\Payslip;
use App\Domains\HR\Models\SalaryStructure;
use App\Domains\HR\Services\PayrollCalculationService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected PayrollCalculationService $payrollService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List configured salary structures.
     */
    public function getStructures(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        // Seed default if empty
        $this->payrollService->getDefaultSalaryStructure($tenantId);

        $structures = SalaryStructure::where('tenant_id', $tenantId)
            ->orderBy('is_default', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $structures,
        ]);
    }

    /**
     * Create custom salary structure.
     */
    public function storeStructure(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'basic_percentage' => 'nullable|numeric|between:10,80',
            'hra_percentage' => 'nullable|numeric|between:0,100',
            'da_percentage' => 'nullable|numeric|between:0,100',
            'conveyance_allowance' => 'nullable|numeric|min:0',
            'medical_allowance' => 'nullable|numeric|min:0',
            'special_allowance' => 'nullable|numeric|min:0',
            'pf_enabled' => 'boolean',
            'pf_employee_percent' => 'nullable|numeric|between:0,20',
            'pf_employer_percent' => 'nullable|numeric|between:0,20',
            'pf_wage_ceiling' => 'nullable|numeric|min:0',
            'esi_enabled' => 'boolean',
            'esi_employee_percent' => 'nullable|numeric|between:0,10',
            'esi_employer_percent' => 'nullable|numeric|between:0,10',
            'esi_gross_ceiling' => 'nullable|numeric|min:0',
            'professional_tax_enabled' => 'boolean',
            'pt_slabs' => 'nullable|array',
            'tds_mode' => 'nullable|in:slab,fixed_percentage,exempt',
            'is_default' => 'boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $structure = SalaryStructure::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Salary structure created successfully.',
            'data' => $structure,
        ], 201);
    }

    /**
     * Get employee salary breakdown.
     */
    public function getEmployeeSalary(int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);
        $salary = $employee->currentSalary()->with('salaryStructure')->first();

        if (!$salary) {
            $salary = $this->payrollService->assignSalaryStructure($tenantId, $employee->id, [
                'monthly_ctc' => $employee->salary ?: 50000,
            ]);
            $salary->load('salaryStructure');
        }

        return response()->json([
            'status' => 'success',
            'data' => $salary,
        ]);
    }

    /**
     * Assign / update salary structure for an employee.
     */
    public function assignSalary(Request $request, int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        if ($request->has('ctc_monthly') && !$request->has('monthly_ctc')) {
            $request->merge(['monthly_ctc' => $request->input('ctc_monthly')]);
        }
        if ($request->has('effective_from') && !$request->has('effective_date')) {
            $request->merge(['effective_date' => $request->input('effective_from')]);
        }

        $validated = $request->validate([
            'salary_structure_id' => 'nullable|integer',
            'monthly_ctc' => 'required|numeric|min:1000',
            'effective_date' => 'nullable|date',
        ]);

        $salary = $this->payrollService->assignSalaryStructure($tenantId, $employeeId, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Salary structure assigned and compensation calculated.',
            'data' => $salary->load(['employee', 'salaryStructure']),
        ]);
    }

    /**
     * List payroll runs / batches.
     */
    public function getBatches(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = PayrollBatch::where('tenant_id', $tenantId)->with('approvedByUser:id,name');

        if ($request->has('year')) {
            $query->where('year', $request->input('year'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('year', 'desc')->orderBy('month', 'desc')->get(),
        ]);
    }

    /**
     * Generate or recalculate monthly payroll batch.
     */
    public function generateBatch(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2035',
            'month' => 'required|integer|between:1,12',
            'notes' => 'nullable|string',
        ]);

        $batch = $this->payrollService->generatePayrollBatch(
            $tenantId,
            (int) $validated['year'],
            (int) $validated['month'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => "Payroll batch generated for period {$batch->payroll_period}.",
            'data' => $batch,
        ], 201);
    }

    /**
     * Get payroll batch with payslips.
     */
    public function getBatch(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $batch = PayrollBatch::where('tenant_id', $tenantId)
            ->with(['payslips.employee.departmentRel', 'approvedByUser:id,name'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $batch,
        ]);
    }

    /**
     * Approve payroll batch.
     */
    public function approveBatch(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $userId = $request->user()?->id ?? 1;

        $batch = $this->payrollService->approvePayrollBatch($tenantId, $id, $userId);

        return response()->json([
            'status' => 'success',
            'message' => 'Payroll batch successfully approved for disbursement.',
            'data' => $batch,
        ]);
    }

    /**
     * Disburse payroll batch (record payments).
     */
    public function disburseBatch(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $validated = $request->validate([
            'payment_method' => 'nullable|string|in:bank_transfer,neft,rtgs,imps,cheque,upi',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $batch = $this->payrollService->disbursePayrollBatch($tenantId, $id, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Payroll disbursed and all employee payslips marked paid.',
            'data' => $batch,
        ]);
    }

    /**
     * List / Filter individual payslips.
     */
    public function getPayslips(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = Payslip::where('tenant_id', $tenantId)->with(['employee.departmentRel', 'payrollBatch']);

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }
        if ($request->has('payroll_batch_id')) {
            $query->where('payroll_batch_id', $request->input('payroll_batch_id'));
        }
        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Get single payslip details.
     */
    public function getPayslip(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $payslip = Payslip::where('tenant_id', $tenantId)
            ->with(['employee.departmentRel', 'payrollBatch'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $payslip,
        ]);
    }

    /**
     * Calculate Full & Final (F&F) Settlement.
     */
    public function calculateFullAndFinal(Request $request, ?int $employeeId = null): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $targetEmployeeId = $employeeId ?: (int) $request->input('employee_id');

        $validated = $request->validate([
            'employee_id' => 'nullable|integer',
            'relieving_date' => 'nullable|date',
            'resignation_date' => 'nullable|date',
            'last_working_date' => 'nullable|date',
            'unpaid_salary_days' => 'nullable|integer|between:0,31',
            'leave_encashment_days' => 'nullable|numeric|min:0',
            'notice_required_days' => 'nullable|integer|min:0',
            'notice_served_days' => 'nullable|integer|min:0',
            'years_of_service' => 'nullable|integer|min:0',
            'gratuity_eligible' => 'nullable|boolean',
        ]);

        if (isset($validated['last_working_date']) && !isset($validated['relieving_date'])) {
            $validated['relieving_date'] = $validated['last_working_date'];
        }

        $settlement = $this->payrollService->calculateFullAndFinal($tenantId, $targetEmployeeId, $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Full and final settlement computed successfully.',
            'data' => $settlement,
        ]);
    }
}
