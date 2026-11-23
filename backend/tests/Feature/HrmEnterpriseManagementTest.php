<?php

namespace Tests\Feature;

use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\AttendanceCorrection;
use App\Domains\HR\Models\CompanyHoliday;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeeTransfer;
use App\Domains\HR\Models\Leave;
use App\Domains\HR\Models\LeaveBalance;
use App\Domains\HR\Models\LeaveType;
use App\Domains\HR\Models\Shift;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrmEnterpriseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $hrUser;
    protected User $regularEmployeeUser;
    protected User $tenantBUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Global Corp',
            'domain' => 'acme.enterprise-crm.test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Nexus Industries',
            'domain' => 'nexus.enterprise-crm.test',
            'status' => 'active',
        ]);

        $this->hrUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'HR',
            'name' => 'Alice HR Manager',
            'email' => 'alice.hr@acme.test',
        ]);
        $this->hrUser->workspaces()->attach($this->tenantA->id, ['role' => 'HR', 'status' => 'active']);

        $this->regularEmployeeUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Employee',
            'name' => 'Bob Engineer',
            'email' => 'bob.engineer@acme.test',
        ]);
        $this->regularEmployeeUser->workspaces()->attach($this->tenantA->id, ['role' => 'Employee', 'status' => 'active']);

        $this->tenantBUser = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'role' => 'HR',
            'name' => 'Charlie Tenant B HR',
            'email' => 'charlie.hr@nexus.test',
        ]);
        $this->tenantBUser->workspaces()->attach($this->tenantB->id, ['role' => 'HR', 'status' => 'active']);

        // Seed default leave types for Tenant A
        LeaveType::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Paid Time Off',
            'code' => 'PTO',
            'days_allowed_per_year' => 15.0,
            'is_paid' => true,
            'status' => 'active',
        ]);

        LeaveType::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sick Leave',
            'code' => 'SL',
            'days_allowed_per_year' => 10.0,
            'is_paid' => true,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function it_creates_employees_with_auto_generated_employee_id_and_initializes_leave_balances(): void
    {
        Sanctum::actingAs($this->hrUser);

        $dept = Department::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        $response = $this->postJson('/api/v1/crm/employees', [
            'name' => 'John Doe',
            'email' => 'john.doe@acme.test',
            'department_id' => $dept->id,
            'designation' => 'Staff Backend Engineer',
            'employment_type' => 'full_time',
            'work_location' => 'Bangalore Office',
            'salary' => 120000.00,
            'emergency_contact' => [
                'name' => 'Jane Doe',
                'relationship' => 'Spouse',
                'phone' => '+919876543210',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertEquals('EMP-001', $response->json('data.employee_id'));
        $this->assertEquals('Engineering', $response->json('data.department'));

        $employeeId = $response->json('data.id');
        $this->assertDatabaseHas('employees', [
            'id' => $employeeId,
            'tenant_id' => $this->tenantA->id,
            'employee_id' => 'EMP-001',
            'name' => 'John Doe',
        ]);

        // Verify leave balances automatically initialized
        $balances = LeaveBalance::where('employee_id', $employeeId)->get();
        $this->assertCount(2, $balances);
        $ptoBalance = $balances->firstWhere('total_entitled', 15.0);
        $this->assertNotNull($ptoBalance);
        $this->assertEquals(15.0, $ptoBalance->balance_days);
    }

    /** @test */
    public function it_protects_sensitive_employee_data_from_unauthorized_users(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'employee_id' => 'EMP-100',
            'name' => 'Secret Exec',
            'email' => 'secret@acme.test',
            'salary' => 250000.00,
            'emergency_contact' => ['name' => 'VIP Contact', 'phone' => '+15551234567'],
        ]);

        // Regular employee viewing directory
        Sanctum::actingAs($this->regularEmployeeUser);
        $res = $this->getJson("/api/v1/crm/employees/{$employee->id}");
        $res->assertOk();
        $this->assertNull($res->json('data.salary'));
        $this->assertNull($res->json('data.emergency_contact'));

        // HR user viewing profile
        Sanctum::actingAs($this->hrUser);
        $hrRes = $this->getJson("/api/v1/crm/employees/{$employee->id}");
        $hrRes->assertOk();
        $this->assertEquals(250000.00, (float) $hrRes->json('data.salary'));
        $this->assertEquals('VIP Contact', $hrRes->json('data.emergency_contact.name'));
    }

    /** @test */
    public function it_manages_departments_and_organization_hierarchy(): void
    {
        Sanctum::actingAs($this->hrUser);

        $headEmp = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Engineering VP',
            'designation' => 'VP of Engineering',
        ]);

        $deptRes = $this->postJson('/api/v1/crm/departments', [
            'name' => 'Product Engineering',
            'code' => 'PROD-ENG',
            'department_head_id' => $headEmp->id,
            'description' => 'Core software systems and infrastructure',
        ]);
        $deptRes->assertStatus(201);
        $deptId = $deptRes->json('data.id');

        $staffEmp = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Junior Dev',
            'department_id' => $deptId,
            'reporting_manager_id' => $headEmp->id,
        ]);

        $hierarchyRes = $this->getJson('/api/v1/crm/hr/hierarchy');
        $hierarchyRes->assertOk();
        $departments = $hierarchyRes->json('data.departments');
        $this->assertNotEmpty($departments);
        $this->assertEquals('Product Engineering', $departments[0]['name']);
        $this->assertEquals('Engineering VP', $departments[0]['head']['name']);
    }

    /** @test */
    public function it_handles_employee_department_transfer_and_records_audit_history(): void
    {
        Sanctum::actingAs($this->hrUser);

        $deptA = Department::create(['tenant_id' => $this->tenantA->id, 'name' => 'Support']);
        $deptB = Department::create(['tenant_id' => $this->tenantA->id, 'name' => 'Product Management']);

        $managerA = Employee::create(['tenant_id' => $this->tenantA->id, 'name' => 'Support Lead']);
        $managerB = Employee::create(['tenant_id' => $this->tenantA->id, 'name' => 'VP Product']);

        $emp = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'David Lee',
            'department_id' => $deptA->id,
            'department' => 'Support',
            'designation' => 'Support Tier 2',
            'reporting_manager_id' => $managerA->id,
        ]);

        $transferRes = $this->postJson("/api/v1/crm/employees/{$emp->id}/transfer", [
            'to_department_id' => $deptB->id,
            'to_designation' => 'Associate Product Manager',
            'to_manager_id' => $managerB->id,
            'transfer_date' => now()->toDateString(),
            'reason' => 'Internal lateral promotion based on product knowledge',
        ]);

        $transferRes->assertOk();

        // Verify transfer audit record
        $this->assertDatabaseHas('employee_transfers', [
            'employee_id' => $emp->id,
            'from_department_id' => $deptA->id,
            'to_department_id' => $deptB->id,
            'to_designation' => 'Associate Product Manager',
            'transferred_by_user_id' => $this->hrUser->id,
        ]);

        // Verify employee profile was updated
        $emp->refresh();
        $this->assertEquals($deptB->id, $emp->department_id);
        $this->assertEquals('Associate Product Manager', $emp->designation);
        $this->assertEquals($managerB->id, $emp->reporting_manager_id);
    }

    /** @test */
    public function it_handles_shift_rules_grace_period_and_late_attendance_calculations(): void
    {
        $shift = Shift::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Standard General Shift',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'grace_period_minutes' => 15,
            'half_day_minutes' => 240,
            'full_day_minutes' => 480,
            'is_default' => true,
        ]);

        $emp = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->regularEmployeeUser->id,
            'name' => $this->regularEmployeeUser->name,
            'shift_id' => $shift->id,
        ]);

        Sanctum::actingAs($this->regularEmployeeUser);

        // Case 1: Check-in at 09:12 (within 15 min grace period) -> present, not late
        $checkInOnTime = $this->postJson('/api/v1/crm/attendance/checkin', [
            'date' => '2026-11-23',
            'check_in' => '09:12:00',
        ]);
        $checkInOnTime->assertOk();
        $this->assertEquals('present', $checkInOnTime->json('data.status'));
        $this->assertFalse($checkInOnTime->json('data.isLate'));

        // Case 2: Checkout at 18:00 -> computes 528 minutes work, status remains present
        $checkOutFullDay = $this->postJson('/api/v1/crm/attendance/checkout', [
            'date' => '2026-11-23',
            'check_out' => '18:00:00',
        ]);
        $checkOutFullDay->assertOk();
        $this->assertEquals(528, $checkOutFullDay->json('data.totalWorkMinutes'));
        $this->assertFalse($checkOutFullDay->json('data.isHalfDay'));

        // Case 3: Check-in next day at 09:30 (exceeds 15 min grace period) -> late
        $checkInLate = $this->postJson('/api/v1/crm/attendance/checkin', [
            'date' => '2026-11-24',
            'check_in' => '09:30:00',
        ]);
        $checkInLate->assertOk();
        $this->assertEquals('late', $checkInLate->json('data.status'));
        $this->assertTrue($checkInLate->json('data.isLate'));
        $this->assertEquals(30, $checkInLate->json('data.lateMinutes'));

        // Check out at 14:00 (4.5 hours = 270 minutes, which is between 240 and 480 min) -> half_day
        $checkOutHalfDay = $this->postJson('/api/v1/crm/attendance/checkout', [
            'date' => '2026-11-24',
            'check_out' => '14:00:00',
        ]);
        $checkOutHalfDay->assertOk();
        $this->assertTrue($checkOutHalfDay->json('data.isHalfDay'));
        $this->assertEquals('half_day', $checkOutHalfDay->json('data.status'));
    }

    /** @test */
    public function it_enforces_attendance_correction_workflow_with_reviewer_authorization(): void
    {
        $emp = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->regularEmployeeUser->id,
            'name' => $this->regularEmployeeUser->name,
        ]);

        Sanctum::actingAs($this->regularEmployeeUser);

        // Employee requests correction for missing clock-in yesterday
        $correctionRes = $this->postJson('/api/v1/crm/attendance/corrections', [
            'date' => '2026-11-20',
            'requested_check_in' => '09:05',
            'requested_check_out' => '18:15',
            'reason' => 'Biometric sensor failure at main gate',
        ]);
        $correctionRes->assertStatus(201);
        $correctionId = $correctionRes->json('data.id');

        // HR approves correction
        Sanctum::actingAs($this->hrUser);
        $reviewRes = $this->postJson("/api/v1/crm/attendance/corrections/{$correctionId}/review", [
            'status' => 'approved',
            'reviewer_notes' => 'Verified with CCTV log',
        ]);
        $reviewRes->assertOk();
        $this->assertEquals('approved', $reviewRes->json('data.status'));

        // Attendance record should now exist and be locked
        $attendance = Attendance::where('employee_id', $emp->id)->whereDate('date', '2026-11-20')->first();
        $this->assertNotNull($attendance);
        $this->assertStringContainsString('09:05', $attendance->check_in);
        $this->assertStringContainsString('18:15', $attendance->check_out);
        $this->assertTrue($attendance->is_locked);
        $this->assertEquals($this->hrUser->id, $attendance->approved_by_user_id);
    }

    /** @test */
    public function it_manages_leave_requests_deducts_balances_and_prevents_exceeding_entitlement(): void
    {
        $ptoType = LeaveType::where('tenant_id', $this->tenantA->id)->where('code', 'PTO')->first();

        $emp = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->regularEmployeeUser->id,
            'name' => $this->regularEmployeeUser->name,
        ]);

        LeaveBalance::create([
            'tenant_id' => $this->tenantA->id,
            'employee_id' => $emp->id,
            'leave_type_id' => $ptoType->id,
            'year' => 2026,
            'total_entitled' => 10.0,
            'taken_days' => 0.0,
            'pending_days' => 0.0,
            'balance_days' => 10.0,
        ]);

        Sanctum::actingAs($this->regularEmployeeUser);

        // 1. Submit leave request for 3 days
        $applyRes = $this->postJson('/api/v1/crm/leaves', [
            'leave_type_id' => $ptoType->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-03',
            'reason' => 'Annual family vacation',
        ]);
        $applyRes->assertStatus(201);
        $leaveId = $applyRes->json('data.id');

        // Check balance pending days reserved
        $balance = LeaveBalance::where('employee_id', $emp->id)->where('leave_type_id', $ptoType->id)->first();
        $this->assertEquals(3.0, (float) $balance->pending_days);

        // 2. Reject request that exceeds balance
        $overRes = $this->postJson('/api/v1/crm/leaves', [
            'leave_type_id' => $ptoType->id,
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-25', // 16 days
            'reason' => 'Extended trek',
        ]);
        $overRes->assertStatus(422);

        // 3. Manager approves leave
        Sanctum::actingAs($this->hrUser);
        $approveRes = $this->postJson("/api/v1/crm/leaves/{$leaveId}/approve", [
            'manager_notes' => 'Approved, have a great vacation!',
        ]);
        $approveRes->assertOk();

        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->pending_days);
        $this->assertEquals(3.0, (float) $balance->taken_days);
        $this->assertEquals(7.0, (float) $balance->balance_days);

        // 4. Cancel approved leave and assert balance restored
        $cancelRes = $this->postJson("/api/v1/crm/leaves/{$leaveId}/cancel");
        $cancelRes->assertOk();

        $balance->refresh();
        $this->assertEquals(0.0, (float) $balance->taken_days);
        $this->assertEquals(10.0, (float) $balance->balance_days);
    }

    /** @test */
    public function it_supports_manual_leave_balance_adjustments_with_audit_trail(): void
    {
        $ptoType = LeaveType::where('tenant_id', $this->tenantA->id)->where('code', 'PTO')->first();
        $emp = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Award Winner',
        ]);

        LeaveBalance::create([
            'tenant_id' => $this->tenantA->id,
            'employee_id' => $emp->id,
            'leave_type_id' => $ptoType->id,
            'year' => now()->year,
            'total_entitled' => 12.0,
            'taken_days' => 2.0,
            'pending_days' => 0.0,
            'balance_days' => 10.0,
        ]);

        Sanctum::actingAs($this->hrUser);

        $adjustRes = $this->postJson('/api/v1/crm/leaves/balances/adjust', [
            'employee_id' => $emp->id,
            'leave_type_id' => $ptoType->id,
            'adjustment_days' => 2.5,
            'reason' => 'Annual performance spot bonus (+2.5 extra paid days)',
        ]);

        $adjustRes->assertOk();

        $this->assertDatabaseHas('leave_balance_adjustments', [
            'employee_id' => $emp->id,
            'leave_type_id' => $ptoType->id,
            'adjustment_days' => 2.5,
            'previous_balance' => 10.0,
            'new_balance' => 12.5,
            'adjusted_by_user_id' => $this->hrUser->id,
        ]);
    }

    /** @test */
    public function it_strictly_enforces_cross_tenant_isolation_on_hrm_records(): void
    {
        $empA = Employee::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Internal Staff',
            'employee_id' => 'EMP-A',
        ]);

        $deptA = Department::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Only Dept',
        ]);

        // Tenant B HR attempts to access Tenant A employee
        Sanctum::actingAs($this->tenantBUser);

        $resEmp = $this->getJson("/api/v1/crm/employees/{$empA->id}");
        $resEmp->assertStatus(404);

        $resDept = $this->getJson("/api/v1/crm/departments/{$deptA->id}");
        $resDept->assertStatus(404);

        // Tenant B listing should not include Tenant A records
        $listRes = $this->getJson('/api/v1/crm/employees');
        $listRes->assertOk();
        $this->assertEmpty($listRes->json('data'));
    }
}
