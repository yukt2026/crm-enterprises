<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

// Models
use App\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Sales\Models\Product;
use App\Domains\Sales\Models\Service;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\Meeting;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\Leave;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\AuditSecurity\Models\ActivityLog;
use App\Domains\Identity\Models\Notification;

class JsonDataMigrationCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $jsonPath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $candidates = [
            base_path('../frontend/crm_db.json'),
            base_path('frontend/crm_db.json'),
            dirname(base_path()) . '/frontend/crm_db.json',
        ];

        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                $this->jsonPath = $cand;
                break;
            }
        }
    }

    /**
     * Test JSON source file and safety backup exist
     */
    public function test_json_database_source_and_backup_exist(): void
    {
        $this->assertNotNull($this->jsonPath, 'crm_db.json must exist');
        $this->assertFileExists($this->jsonPath);

        $json = json_decode(File::get($this->jsonPath), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('users', $json);
        $this->assertArrayHasKey('leads', $json);
        $this->assertArrayHasKey('companies', $json);
        $this->assertArrayHasKey('deals', $json);
        $this->assertArrayHasKey('invoices', $json);
        $this->assertArrayHasKey('employees', $json);

        // Verify backup copy exists
        $backupPath = dirname($this->jsonPath) . '/crm_db.backup.json';
        $this->assertFileExists($backupPath);
    }

    /**
     * Test dry run command simulates validation without mutating DB
     */
    public function test_migration_dry_run_does_not_mutate_database(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['id' => 1],
            ['name' => 'Acme Enterprise Inc.', 'slug' => 'acme-enterprise-inc', 'uuid' => (string)\Illuminate\Support\Str::uuid()]
        );
        TenantContext::setTenantId($tenant->id);

        $initialCompanies = Company::count();
        $initialDeals = Deal::count();

        $exitCode = Artisan::call('crm:migrate-json', [
            '--file' => $this->jsonPath,
            '--tenant-id' => $tenant->id,
            '--dry-run' => true,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals($initialCompanies, Company::count());
        $this->assertEquals($initialDeals, Deal::count());
    }

    /**
     * Test live cutover migration populates all collections with relational integrity
     */
    public function test_live_migration_populates_all_collections_and_relations(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['id' => 1],
            ['name' => 'Acme Enterprise Inc.', 'slug' => 'acme-enterprise-inc', 'uuid' => (string)\Illuminate\Support\Str::uuid()]
        );
        TenantContext::setTenantId($tenant->id);

        $exitCode = Artisan::call('crm:migrate-json', [
            '--file' => $this->jsonPath,
            '--tenant-id' => $tenant->id,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // 1. Verify Users & RBAC
        $this->assertGreaterThanOrEqual(9, User::count());
        $john = User::where('email', 'john@nexus.com')->first();
        $this->assertNotNull($john);
        $this->assertEquals('Super Admin', $john->role);
        $this->assertTrue($john->two_factor_enabled);

        // 2. Verify Companies & Revenue Parsing
        $starkComp = Company::where('name', 'Stark Industries')->first();
        $this->assertNotNull($starkComp);
        $this->assertEquals(500000000.00, (float)$starkComp->annual_revenue);

        $umbrellaComp = Company::where('name', 'Umbrella Corporation')->first();
        $this->assertNotNull($umbrellaComp);
        $this->assertEquals(300000000.00, (float)$umbrellaComp->annual_revenue);

        // 3. Verify Customers and Company FK
        $tonyCust = Customer::where('name', 'Tony Stark')->first();
        $this->assertNotNull($tonyCust);
        $this->assertEquals($starkComp->id, $tonyCust->company_id);

        // 4. Verify Contacts and Company FK
        $pepperContact = Contact::where('name', 'Pepper Potts')->first();
        $this->assertNotNull($pepperContact);
        $this->assertEquals($starkComp->id, $pepperContact->company_id);
        $this->assertTrue($pepperContact->is_primary);

        // 5. Verify Leads
        $this->assertGreaterThanOrEqual(5, Lead::count());
        $peterLead = Lead::where('email', 'peter.parker@dailybugle.com')->first();
        $this->assertNotNull($peterLead);
        $this->assertEquals(5000.00, (float)$peterLead->estimated_value);
        $this->assertEquals('new', $peterLead->status);

        // 6. Verify Products & Services
        $secProduct = Product::where('sku', 'SEC-ENT-01')->first();
        $this->assertNotNull($secProduct);
        $this->assertEquals(25000.00, (float)$secProduct->price);

        $cloudService = Service::where('name', 'SaaS Cloud Hosting & Support')->first();
        $this->assertNotNull($cloudService);
        $this->assertEquals(1500.00, (float)$cloudService->price);
        $this->assertEquals('monthly', $cloudService->billing_cycle);

        // 7. Verify Deals
        $droneDeal = Deal::where('name', 'Stark Drone AI Upgrade')->first();
        $this->assertNotNull($droneDeal);
        $this->assertEquals(350000.00, (float)$droneDeal->value);
        $this->assertEquals('qualification', $droneDeal->stage);

        // 8. Verify Quotations & Child Line Items
        $quote1 = Quotation::where('quote_number', 'QT-2026-001')->first();
        $this->assertNotNull($quote1);
        $this->assertEquals(135700.00, (float)$quote1->total_amount);
        $this->assertGreaterThanOrEqual(1, $quote1->items()->count());

        // 9. Verify Invoices & Child Line Items
        $inv1 = Invoice::where('invoice_number', 'INV-2026-001')->first();
        $this->assertNotNull($inv1);
        $this->assertEquals('paid', $inv1->status);
        $this->assertEquals(135700.00, (float)$inv1->total_amount);
        $this->assertEquals(135700.00, (float)$inv1->paid_amount);
        $this->assertEquals(0.00, (float)$inv1->outstanding_amount);
        $this->assertGreaterThanOrEqual(1, $inv1->items()->count());

        // 10. Verify Payments
        $payment = Payment::where('amount', 135700)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($inv1->id, $payment->invoice_id);
        $this->assertEquals('Bank Transfer', $payment->payment_method);
        $this->assertEquals('success', $payment->status);

        // 11. Verify Projects & Team Members Pivot
        $socProject = Project::where('name', 'AI Operations Command Board')->first();
        $this->assertNotNull($socProject);
        $this->assertEquals(45, $socProject->progress);
        $this->assertGreaterThanOrEqual(1, $socProject->teamMembers()->count());

        // 12. Verify Tasks
        $task = Task::where('title', 'Setup Kubernetes Cluster Ingress Nodes')->first();
        $this->assertNotNull($task);
        $this->assertEquals('medium', $task->priority);
        $this->assertEquals('in_progress', $task->status);
        $this->assertEquals($socProject->id, $task->project_id);

        // 13. Verify Meetings & Attendees
        $meeting = Meeting::where('title', 'Tony Stark Security Review')->first();
        $this->assertNotNull($meeting);
        $this->assertGreaterThanOrEqual(1, $meeting->attendees()->count());

        // 14. Verify HR (Employees, Departments, Attendance, Leaves)
        $employee = Employee::where('email', 'ranjeetmaurya2033@gmail.com')->first();
        $this->assertNotNull($employee);
        $this->assertEquals('Engineering', $employee->department);
        $this->assertNotNull($employee->department_id);

        $attendance = Attendance::where('employee_id', $employee->id)->first();
        $this->assertNotNull($attendance);
        $this->assertEquals('08:55', substr($attendance->check_in, 0, 5));

        $leave = Leave::where('type', 'Casual')->first();
        $this->assertNotNull($leave);
        $this->assertEquals('pending', $leave->status);

        // 15. Verify Support Tickets
        $ticket = SupportTicket::where('subject', 'Container Threat Analyzer Connection Timeout')->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('high', $ticket->priority);
        $this->assertEquals('in_progress', $ticket->status);

        // 16. Verify Activity Logs & Notifications
        $activity = ActivityLog::where('action', 'INVOICE_PAID')->first();
        $this->assertNotNull($activity);
        $this->assertEquals('Finance', $activity->module);

        $notif = Notification::where('title', 'New Lead Assigned')->first();
        $this->assertNotNull($notif);
        $this->assertFalse((bool)$notif->is_read);
    }

    /**
     * Test idempotency: subsequent migration executions do not create duplicate records
     */
    public function test_migration_is_strictly_idempotent_on_reruns(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['id' => 1],
            ['name' => 'Acme Enterprise Inc.', 'slug' => 'acme-enterprise-inc', 'uuid' => (string)\Illuminate\Support\Str::uuid()]
        );
        TenantContext::setTenantId($tenant->id);

        // First run
        Artisan::call('crm:migrate-json', [
            '--file' => $this->jsonPath,
            '--tenant-id' => $tenant->id,
            '--force' => true,
        ]);

        $usersCount1 = User::count();
        $companiesCount1 = Company::count();
        $leadsCount1 = Lead::count();
        $dealsCount1 = Deal::count();
        $invoicesCount1 = Invoice::count();
        $employeesCount1 = Employee::count();
        $projectsCount1 = Project::count();

        // Second run
        $exitCode = Artisan::call('crm:migrate-json', [
            '--file' => $this->jsonPath,
            '--tenant-id' => $tenant->id,
            '--force' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Record counts must match exactly (no duplicate records)
        $this->assertEquals($usersCount1, User::count(), 'User count must remain identical');
        $this->assertEquals($companiesCount1, Company::count(), 'Company count must remain identical');
        $this->assertEquals($leadsCount1, Lead::count(), 'Lead count must remain identical');
        $this->assertEquals($dealsCount1, Deal::count(), 'Deal count must remain identical');
        $this->assertEquals($invoicesCount1, Invoice::count(), 'Invoice count must remain identical');
        $this->assertEquals($employeesCount1, Employee::count(), 'Employee count must remain identical');
        $this->assertEquals($projectsCount1, Project::count(), 'Project count must remain identical');
    }
}
