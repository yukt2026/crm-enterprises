<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Services\EmployeeService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $user = $request->user();

        $query = Employee::where('tenant_id', $tenantId)
            ->with(['departmentRel:id,name,code', 'manager:id,name,designation', 'shift:id,name,start_time,end_time'])
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->query('department_id'), fn($q, $d) => $q->where('department_id', $d))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%")
                        ->orWhere('designation', 'like', "%{$search}%");
                });
            });

        $employees = $query->orderBy('name')->get();

        // Protect sensitive data for non-HR / non-Owner / non-Admin users
        $canViewSensitive = in_array($user->role, ['Business Owner', 'Business Admin', 'HR', 'Platform Super Admin']);

        if (!$canViewSensitive) {
            $employees->transform(function ($emp) use ($user) {
                // If it's not the user's own profile, hide sensitive fields
                if ($emp->user_id !== $user->id) {
                    $emp->makeHidden([
                        'salary',
                        'emergency_contact',
                        'pan_number',
                        'aadhaar_number',
                        'uan_number',
                        'esi_number',
                        'bank_account_number',
                        'bank_ifsc',
                    ]);
                }
                return $emp;
            });
        }

        return $this->successResponse($employees, 'Employees retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'employee_id' => 'nullable|string|max:50',
            'user_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|integer',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
            'department' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:100',
            'reporting_manager_id' => 'nullable|exists:employees,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'date_of_joining' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'notice_period_days' => 'nullable|integer|min:0',
            'salary' => 'nullable|numeric|min:0',
            'salary_structure_id' => 'nullable|integer',
            'status' => 'nullable|in:active,probation,suspended,terminated,resigned',
            'employment_type' => 'nullable|in:full_time,part_time,contract,intern',
            'work_location' => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|array',
            'skills' => 'nullable|array',
            'documents' => 'nullable|array',
            'pan_number' => 'nullable|string|max:50',
            'aadhaar_number' => 'nullable|string|max:50',
            'uan_number' => 'nullable|string|max:50',
            'esi_number' => 'nullable|string|max:50',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'bank_ifsc' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $employee = $this->employeeService->createEmployee($request->user()->tenant_id, $validated);

        return $this->successResponse($employee, 'Employee created successfully', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $user = $request->user();

        $employee = Employee::where('tenant_id', $tenantId)
            ->with([
                'departmentRel',
                'manager:id,name,designation,email',
                'directReports:id,name,designation,department',
                'shift',
                'leaveBalances.leaveType',
                'attendances' => fn($q) => $q->orderBy('date', 'desc')->limit(10),
                'leaves' => fn($q) => $q->orderBy('start_date', 'desc')->limit(10),
                'transfers.fromDepartment',
                'transfers.toDepartment',
            ])
            ->findOrFail($id);

        $canViewSensitive = in_array($user->role, ['Business Owner', 'Business Admin', 'HR', 'Platform Super Admin']) || ($employee->user_id && (int)$employee->user_id === (int)$user->id);

        if (!$canViewSensitive) {
            $employee->makeHidden([
                'salary',
                'emergency_contact',
                'pan_number',
                'aadhaar_number',
                'uan_number',
                'esi_number',
                'bank_account_number',
                'bank_ifsc',
            ]);
            $employee->salary = null;
            $employee->pan_number = null;
            $employee->aadhaar_number = null;
            $employee->uan_number = null;
            $employee->esi_number = null;
            $employee->bank_account_number = null;
            $employee->bank_ifsc = null;
            $employee->emergency_contact = null;
        }

        return $this->successResponse($employee, 'Employee profile retrieved successfully');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'employee_id' => 'nullable|string|max:50',
            'user_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|integer',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'department_id' => 'nullable|exists:departments,id',
            'department' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:100',
            'reporting_manager_id' => 'nullable|exists:employees,id',
            'shift_id' => 'nullable|exists:shifts,id',
            'date_of_joining' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'notice_period_days' => 'nullable|integer|min:0',
            'salary' => 'nullable|numeric|min:0',
            'salary_structure_id' => 'nullable|integer',
            'status' => 'nullable|in:active,probation,suspended,terminated,resigned',
            'employment_type' => 'nullable|in:full_time,part_time,contract,intern',
            'work_location' => 'nullable|string|max:100',
            'emergency_contact' => 'nullable|array',
            'skills' => 'nullable|array',
            'documents' => 'nullable|array',
            'pan_number' => 'nullable|string|max:50',
            'aadhaar_number' => 'nullable|string|max:50',
            'uan_number' => 'nullable|string|max:50',
            'esi_number' => 'nullable|string|max:50',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'bank_ifsc' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $employee->update($validated);

        return $this->successResponse($employee, 'Employee profile updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($id);
        $employee->delete();

        return $this->successResponse(null, 'Employee deleted successfully');
    }

    public function transfer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'to_department_id' => 'nullable|exists:departments,id',
            'to_designation' => 'nullable|string|max:100',
            'to_manager_id' => 'nullable|exists:employees,id',
            'transfer_date' => 'nullable|date',
            'reason' => 'nullable|string',
        ]);

        $transfer = $this->employeeService->transferEmployee($id, $validated, $request->user()->id);

        return $this->successResponse($transfer, 'Employee transferred successfully');
    }

    public function hierarchy(Request $request): JsonResponse
    {
        $hierarchy = $this->employeeService->getOrgHierarchy($request->user()->tenant_id);
        return $this->successResponse($hierarchy, 'Organization hierarchy retrieved successfully');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $metrics = $this->employeeService->getDashboardMetrics($request->user()->tenant_id);
        return $this->successResponse($metrics, 'HR dashboard metrics retrieved successfully');
    }
}
