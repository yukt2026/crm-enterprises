<?php

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\AttendanceCorrection;
use App\Domains\HR\Models\CompanyHoliday;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeeTransfer;
use App\Domains\HR\Models\Leave;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        protected LeaveService $leaveService
    ) {}

    /**
     * Create an employee profile with auto employee_id and initial balances
     */
    public function createEmployee(int $tenantId, array $data): Employee
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $employeeCode = $data['employee_id'] ?? null;
            if (!$employeeCode) {
                $count = Employee::where('tenant_id', $tenantId)->count() + 1;
                $employeeCode = 'EMP-' . str_pad($count, 3, '0', STR_PAD_LEFT);
            }

            $departmentId = $data['department_id'] ?? null;
            $departmentName = $data['department'] ?? 'Operations';

            if ($departmentId && empty($data['department'])) {
                $dept = Department::find($departmentId);
                if ($dept) {
                    $departmentName = $dept->name;
                }
            }

            $employee = Employee::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeCode,
                'user_id' => $data['user_id'] ?? null,
                'name' => $data['name'],
                'profile_image' => $data['profile_image'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'department' => $departmentName,
                'department_id' => $departmentId,
                'designation' => $data['designation'] ?? 'Executive',
                'reporting_manager_id' => $data['reporting_manager_id'] ?? null,
                'shift_id' => $data['shift_id'] ?? null,
                'date_of_joining' => $data['date_of_joining'] ?? now()->toDateString(),
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'contract_end_date' => $data['contract_end_date'] ?? null,
                'notice_period_days' => $data['notice_period_days'] ?? 30,
                'salary' => $data['salary'] ?? 0,
                'salary_structure_id' => $data['salary_structure_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'status' => $data['status'] ?? 'active',
                'employment_type' => $data['employment_type'] ?? 'full_time',
                'work_location' => $data['work_location'] ?? 'Office',
                'emergency_contact' => $data['emergency_contact'] ?? null,
                'skills' => $data['skills'] ?? null,
                'documents' => $data['documents'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'aadhaar_number' => $data['aadhaar_number'] ?? null,
                'uan_number' => $data['uan_number'] ?? null,
                'esi_number' => $data['esi_number'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_ifsc' => $data['bank_ifsc'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Initialize leave balances for current year
            $this->leaveService->initializeYearlyBalances($employee->id, now()->year);

            return $employee;
        });
    }

    /**
     * Record employee transfer
     */
    public function transferEmployee(int $employeeId, array $data, int $transferredByUserId): EmployeeTransfer
    {
        $employee = Employee::findOrFail($employeeId);

        return DB::transaction(function () use ($employee, $data, $transferredByUserId) {
            $transfer = EmployeeTransfer::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'from_department_id' => $employee->department_id,
                'to_department_id' => $data['to_department_id'] ?? $employee->department_id,
                'from_designation' => $employee->designation,
                'to_designation' => $data['to_designation'] ?? $employee->designation,
                'from_manager_id' => $employee->reporting_manager_id,
                'to_manager_id' => $data['to_manager_id'] ?? $employee->reporting_manager_id,
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'transferred_by_user_id' => $transferredByUserId,
            ]);

            // Apply transfer to employee profile
            if (isset($data['to_department_id'])) {
                $employee->department_id = $data['to_department_id'];
                $dept = Department::find($data['to_department_id']);
                if ($dept) {
                    $employee->department = $dept->name;
                }
            }
            if (isset($data['to_designation'])) {
                $employee->designation = $data['to_designation'];
            }
            if (isset($data['to_manager_id'])) {
                $employee->reporting_manager_id = $data['to_manager_id'];
            }
            $employee->save();

            return $transfer;
        });
    }

    /**
     * Organization structure and reporting hierarchy
     */
    public function getOrgHierarchy(int $tenantId): array
    {
        $departments = Department::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with(['head:id,name,designation', 'employees:id,name,designation,department_id,reporting_manager_id,status'])
            ->get();

        return [
            'departments' => $departments,
        ];
    }

    /**
     * Comprehensive HR Dashboard Metrics
     */
    public function getDashboardMetrics(int $tenantId): array
    {
        $today = now()->toDateString();

        $totalEmployees = Employee::where('tenant_id', $tenantId)->count();
        $activeEmployees = Employee::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $probationEmployees = Employee::where('tenant_id', $tenantId)->where('status', 'probation')->count();

        // Department Breakdown
        $departmentBreakdown = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->groupBy('department')
            ->select('department', DB::raw('count(*) as count'))
            ->pluck('count', 'department')
            ->toArray();

        // Employment Type Breakdown
        $employmentTypeBreakdown = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->groupBy('employment_type')
            ->select('employment_type', DB::raw('count(*) as count'))
            ->pluck('count', 'employment_type')
            ->toArray();

        // Today's Attendance
        $attendancesToday = Attendance::where('tenant_id', $tenantId)
            ->where('date', $today)
            ->get();

        $presentCount = $attendancesToday->whereIn('status', ['present', 'late', 'half_day'])->count();
        $lateCount = $attendancesToday->where('is_late', true)->count();
        $halfDayCount = $attendancesToday->where('is_half_day', true)->count();

        // Leaves active today
        $leavesTodayCount = Leave::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();

        $absentCount = max(0, $activeEmployees - $presentCount - $leavesTodayCount);

        // Pending queues
        $pendingLeaves = Leave::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $pendingCorrections = AttendanceCorrection::where('tenant_id', $tenantId)->where('status', 'pending')->count();

        // Upcoming holidays
        $upcomingHolidays = CompanyHoliday::where('tenant_id', $tenantId)
            ->where('date', '>=', $today)
            ->orderBy('date', 'asc')
            ->limit(5)
            ->get();

        return [
            'headcount' => [
                'total' => $totalEmployees,
                'active' => $activeEmployees,
                'probation' => $probationEmployees,
                'by_employment_type' => $employmentTypeBreakdown,
            ],
            'department_distribution' => $departmentBreakdown,
            'attendance_today' => [
                'present' => $presentCount,
                'late' => $lateCount,
                'half_day' => $halfDayCount,
                'on_leave' => $leavesTodayCount,
                'absent' => $absentCount,
            ],
            'pending_actions' => [
                'leave_approvals' => $pendingLeaves,
                'attendance_corrections' => $pendingCorrections,
            ],
            'upcoming_holidays' => $upcomingHolidays,
        ];
    }
}
