<?php

namespace Tests\Feature;

use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\CompanyHoliday;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\LeaveType;
use App\Domains\HR\Models\PayrollBatch;
use App\Domains\HR\Models\SalaryStructure;
use App\Domains\HR\Models\Shift;
use App\Domains\HR\Services\PayrollCalculationService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseHrAndPayrollTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $staffUser;
    protected Department $department;
    protected Shift $shift;
    protected string $adminToken;
    protected string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tata Technologies Enterprise',
            'slug' => 'tata-technologies',
            'status' => 'active',
        ]);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Business Admin',
        ]);
        $this->adminUser->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
            'status' => 'active',
        ]);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Team Member',
        ]);
        $this->staffUser->workspaces()->attach($this->tenant->id, [
            'role' => 'Team Member',
            'status' => 'active',
        ]);
        $this->staffToken = $this->staffUser->createToken('staff-token')->plainTextToken;

        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engineering & Cloud Solutions',
            'code' => 'ENG-CLOUD',
        ]);

        $this->shift = Shift::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'General Corporate Shift',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'grace_period_minutes' => 15,
            'full_day_minutes' => 480,
            'half_day_minutes' => 240,
            'is_default' => true,
        ]);

        LeaveType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Earned Leave (EL)',
            'code' => 'EL',
            'days_allowed_per_year' => 18,
            'is_paid' => true,
            'status' => 'active',
        ]);
    }

    protected function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    protected function staffHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->staffToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_employee_creation_with_branch_and_sensitive_info_masking(): void
    {
        $res = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/crm/employees', [
            'name' => 'Aditya Sharma',
            'email' => 'aditya.sharma@tatatech.com',
            'phone' => '+919876543210',
            'department_id' => $this->department->id,
            'designation' => 'Lead Cloud Architect',
            'date_of_joining' => '2026-01-15',
            'probation_end_date' => '2026-07-15',
            'contract_end_date' => '2027-01-14',
            'notice_period_days' => 60,
            'salary' => 125000,
            'pan_number' => 'ABCPS1234D',
            'aadhaar_number' => '998877665544',
            'bank_account_number' => '00991122334455',
            'bank_name' => 'HDFC Bank Ltd',
            'bank_ifsc' => 'HDFC0001234',
            'work_location' => 'Bangalore Tech Park',
            'status' => 'active',
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'Aditya Sharma');
        $res->assertJsonPath('data.notice_period_days', 60);
        $this->assertNotEmpty($res->json('data.employee_id'));

        $employeeId = $res->json('data.id');

        // Staff without HR permission accessing employee profile should have PAN/Bank masked
        $staffGet = $this->actingAs($this->staffUser, 'sanctum')->withHeaders($this->staffHeaders())->getJson("/api/v1/crm/employees/{$employeeId}");
        $staffGet->assertStatus(200);
        $this->assertNull($staffGet->json('data.salary'));
        $this->assertNull($staffGet->json('data.pan_number'));
        $this->assertNull($staffGet->json('data.bank_account_number'));
    }

    public function test_employee_transfer_and_reporting_hierarchy(): void
    {
        $manager = Employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rajesh Verma',
            'email' => 'rajesh.verma@tatatech.com',
            'department_id' => $this->department->id,
            'department' => $this->department->name,
            'designation' => 'VP of Engineering',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Kavita Nair',
            'email' => 'kavita.nair@tatatech.com',
            'department_id' => $this->department->id,
            'department' => $this->department->name,
            'designation' => 'Software Engineer',
            'reporting_manager_id' => null,
            'status' => 'active',
        ]);

        $newDept = Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'DevOps & Reliability',
            'code' => 'DEVOPS',
        ]);

        $transferRes = $this->withHeaders($this->adminHeaders())->postJson("/api/v1/crm/employees/{$employee->id}/transfer", [
            'to_department_id' => $newDept->id,
            'to_designation' => 'Site Reliability Engineer',
            'to_manager_id' => $manager->id,
            'transfer_date' => now()->toDateString(),
            'reason' => 'Internal cross-functional transfer to Core Platform team',
        ]);

        $transferRes->assertStatus(200);

        $employee->refresh();
        $this->assertEquals($newDept->id, $employee->department_id);
        $this->assertEquals('Site Reliability Engineer', $employee->designation);
        $this->assertEquals($manager->id, $employee->reporting_manager_id);
    }

    public function test_mobile_attendance_with_gps_and_geofencing_validation(): void
    {
        // Office location: Bangalore MG Road (12.9716, 77.5946)
        $officeLat = 12.9716;
        $officeLon = 77.5946;

        // 1. Check-in inside geofence (within 100 meters)
        $insideRes = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/crm/attendance/checkin', [
            'date' => now()->toDateString(),
            'check_in' => '09:05:00',
            'latitude' => 12.9718,
            'longitude' => 77.5948,
            'accuracy_meters' => 12.5,
            'geofence_latitude' => $officeLat,
            'geofence_longitude' => $officeLon,
            'geofence_radius_meters' => 200,
            'verification_method' => 'mobile_gps',
            'is_field_checkin' => false,
        ]);

        $insideRes->assertStatus(200);
        $this->assertTrue($insideRes->json('data.isWithinGeofence'));
        $this->assertEquals('mobile_gps', $insideRes->json('data.verificationMethod'));
    }

    public function test_attendance_overtime_and_early_departure_calculation(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'name' => 'Devendra Joshi',
            'email' => 'devendra@tatatech.com',
            'shift_id' => $this->shift->id,
            'status' => 'active',
        ]);

        $today = now()->subDays(1)->toDateString();

        // Check in on time
        $attendance = Attendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'shift_id' => $this->shift->id,
            'date' => $today,
            'check_in' => '09:00:00',
            'status' => 'present',
        ]);

        // Check out at 20:30:00 (11.5 hours = 690 minutes -> Standard is 480 mins -> Overtime = 210 mins)
        $outRes = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/crm/attendance/checkout', [
            'date' => $today,
            'check_out' => '20:30:00',
        ]);

        $outRes->assertStatus(200);
        $this->assertEquals(690, $outRes->json('data.totalWorkMinutes'));
        $this->assertEquals(210, $outRes->json('data.overtimeMinutes'));
    }

    public function test_leave_request_deduction_and_holiday_conflict(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'name' => 'Pooja Iyer',
            'email' => 'pooja@tatatech.com',
            'status' => 'active',
        ]);

        $holidayDate = now()->addDays(5)->toDateString();
        CompanyHoliday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Independence Day Holiday',
            'date' => $holidayDate,
        ]);

        // Leave application on an official holiday should fail validation
        $conflictRes = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/crm/leaves', [
            'employee_id' => $employee->id,
            'start_date' => $holidayDate,
            'end_date' => $holidayDate,
            'reason' => 'Festival holiday leave',
        ]);

        $conflictRes->assertStatus(422);
        $errorContent = json_encode($conflictRes->json());
        $this->assertStringContainsString('holiday', strtolower($errorContent));
    }

    public function test_indian_salary_structure_and_ctc_component_calculation(): void
    {
        $service = app(PayrollCalculationService::class);
        $structure = $service->getDefaultSalaryStructure($this->tenant->id);

        $this->assertNotEmpty($structure->id);
        $this->assertTrue($structure->pf_enabled);
        $this->assertTrue($structure->esi_enabled);

        // Calculate for monthly CTC ₹60,000
        $comp = $service->calculateCompensation(60000, $structure);

        // Basic = 50% of 60,000 = 30,000
        $this->assertEquals(30000, $comp['basic_amount']);
        // HRA = 50% of Basic = 15,000
        $this->assertEquals(15000, $comp['hra_amount']);
        // PF = 12% capped at 15,000 wage ceiling = 1,800
        $this->assertEquals(1800, $comp['pf_employee_amount']);
        // ESI = 0 because gross ₹60k > ₹21k threshold
        $this->assertEquals(0, $comp['esi_employee_amount']);
        // Professional tax = ₹200 for gross > ₹10,000
        $this->assertEquals(200, $comp['professional_tax_amount']);
        $this->assertGreaterThan(0, $comp['net_salary']);
    }

    public function test_payroll_batch_generation_with_loss_of_pay_and_statutory_deductions(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Suresh Menon',
            'email' => 'suresh.menon@tatatech.com',
            'salary' => 80000,
            'status' => 'active',
        ]);

        // Generate Payroll Batch for 2026-11
        $res = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/crm/payroll/batches/generate', [
            'year' => 2026,
            'month' => 11,
            'notes' => 'November 2026 Monthly Payroll Run',
        ]);

        $res->assertStatus(201);
        $batchId = $res->json('data.id');
        $this->assertEquals('2026-11', $res->json('data.payroll_period'));
        $this->assertGreaterThanOrEqual(1, $res->json('data.total_employees'));
        $this->assertGreaterThan(0, $res->json('data.net_amount'));

        // Check generated payslip
        $payslipRes = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/crm/payroll/payslips?payroll_batch_id=' . $batchId);
        $payslipRes->assertStatus(200);
        $this->assertNotEmpty($payslipRes->json('data'));
        $firstSlip = $payslipRes->json('data.0');
        $this->assertNotEmpty($firstSlip['payslip_number']);
        $this->assertGreaterThan(0, $firstSlip['net_pay']);

        // Approve batch
        $approveRes = $this->withHeaders($this->adminHeaders())->postJson("/api/v1/crm/payroll/batches/{$batchId}/approve");
        $approveRes->assertStatus(200);
        $this->assertEquals('approved', $approveRes->json('data.status'));

        // Disburse batch
        $disburseRes = $this->withHeaders($this->adminHeaders())->postJson("/api/v1/crm/payroll/batches/{$batchId}/disburse", [
            'payment_method' => 'neft',
            'payment_reference' => 'NEFT-BATCH-202611-9988',
        ]);
        $disburseRes->assertStatus(200);
        $this->assertEquals('disbursed', $disburseRes->json('data.status'));
    }

    public function test_full_and_final_settlement_calculation(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Priyanka Sen',
            'email' => 'priyanka.sen@tatatech.com',
            'date_of_joining' => '2020-01-01', // 6+ years service -> eligible for gratuity
            'salary' => 100000,
            'notice_period_days' => 60,
            'status' => 'active',
        ]);

        $res = $this->withHeaders($this->adminHeaders())->postJson("/api/v1/crm/payroll/full-and-final/{$employee->id}", [
            'relieving_date' => '2026-06-30',
            'unpaid_salary_days' => 20,
            'leave_encashment_days' => 15,
            'notice_served_days' => 60, // full notice served, no recovery
        ]);

        $res->assertStatus(200);
        $data = $res->json('data');
        $this->assertGreaterThan(0, $data['unpaid_salary_amount']);
        $this->assertGreaterThan(0, $data['leave_encashment_amount']);
        $this->assertGreaterThan(0, $data['gratuity_amount']); // Eligible due to >= 5 years
        $this->assertEquals(0, $data['notice_recovery_amount']);
        $this->assertGreaterThan(0, $data['net_settlement_payable']);
    }

    public function test_employee_document_registration_and_verification(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rohan Saxena',
            'email' => 'rohan@tatatech.com',
            'status' => 'active',
        ]);

        $docRes = $this->withHeaders($this->adminHeaders())->postJson("/api/v1/crm/employees/{$employee->id}/documents", [
            'document_type' => 'pan_card',
            'title' => 'Income Tax Permanent Account Number Card',
            'document_number' => 'ABCPS9876E',
            'file_name' => 'Rohan_PAN_Card.pdf',
            'expiry_date' => '2035-12-31',
        ]);

        $docRes->assertStatus(201);
        $docId = $docRes->json('data.id');
        $this->assertFalse($docRes->json('data.is_verified'));

        // Verify document
        $verifyRes = $this->withHeaders($this->adminHeaders())->postJson("/api/v1/crm/employees/{$employee->id}/documents/{$docId}/verify");
        $verifyRes->assertStatus(200);
        $this->assertTrue($verifyRes->json('data.is_verified'));
        $this->assertNotEmpty($verifyRes->json('data.verified_at'));
    }

    public function test_employee_productivity_metrics_and_performance_reviews(): void
    {
        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ananya Roy',
            'email' => 'ananya@tatatech.com',
            'status' => 'active',
        ]);

        // Submit Performance Review
        $revRes = $this->withHeaders($this->adminHeaders())->postJson("/api/v1/crm/employees/{$employee->id}/reviews", [
            'review_period' => '2026-Q4',
            'overall_rating' => 4.75,
            'strengths' => 'Exceptional technical execution and mentoring.',
            'improvements' => 'Expand direct customer advisory exposure.',
            'goals' => 'Achieve AWS Solutions Architect Professional certification.',
            'manager_feedback' => 'Recommended for technical leadership progression.',
        ]);

        $revRes->assertStatus(201);
        $this->assertEquals(4.75, $revRes->json('data.overall_rating'));

        // Get Holistic Productivity Dashboard
        $prodRes = $this->withHeaders($this->adminHeaders())->getJson("/api/v1/crm/employees/{$employee->id}/productivity");
        $prodRes->assertStatus(200);
        $this->assertGreaterThan(0, $prodRes->json('data.overall_productivity_score'));
        $this->assertArrayHasKey('score_breakdown', $prodRes->json('data'));
    }

    public function test_hr_reminders_and_milestones_tracking(): void
    {
        // Employee with probation ending in 10 days
        Employee::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Nikhil Gupta',
            'email' => 'nikhil@tatatech.com',
            'date_of_joining' => now()->subMonths(5)->toDateString(),
            'probation_end_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);

        $res = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/crm/hr/reminders');
        $res->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $res->json('data.summary.upcoming_probations'));
        $this->assertNotEmpty($res->json('data.reminders'));
    }
}
