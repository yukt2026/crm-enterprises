<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\BranchSalesTarget;
use App\Domains\CRM\Models\BranchTransfer;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Employee;
use App\Domains\Identity\Models\CrmRole;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiBranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $branchManagerUser;
    protected Branch $headquarters;
    protected Branch $branchMumbai;
    protected Department $salesDept;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Enterprise Multi-Branch Corp',
            'domain' => 'multibranch-' . uniqid() . '.test',
            'subscription_plan' => 'enterprise',
        ]);

        $this->headquarters = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Delhi National HQ',
            'code' => 'BR-DEL-01',
            'city' => 'New Delhi',
            'state' => 'Delhi',
            'country' => 'IN',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->branchMumbai = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mumbai Western Hub',
            'code' => 'BR-BOM-01',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'IN',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->salesDept = Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regional Enterprise Sales',
            'code' => 'RES',
        ]);

        $this->adminUser = User::create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'branch_id' => $this->headquarters->id,
            'name' => 'National Director',
            'email' => 'director.' . uniqid() . '@multibranch.test',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
        ]);
        $this->adminUser->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
            'status' => 'active',
        ]);

        $this->branchManagerUser = User::create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'name' => 'Mumbai Regional Head',
            'email' => 'mumbai.head.' . uniqid() . '@multibranch.test',
            'password' => bcrypt('password123'),
            'role' => 'branch_manager',
        ]);
        $this->branchManagerUser->workspaces()->attach($this->tenant->id, [
            'role' => 'branch_manager',
            'status' => 'active',
        ]);

        $this->branchMumbai->update(['manager_user_id' => $this->branchManagerUser->id]);

        // Seed RBAC presets
        app(RbacService::class)->seedSystemRolePresets();
    }

    /** @test */
    public function it_creates_a_new_branch_with_address_business_hours_and_departments(): void
    {
        Sanctum::actingAs($this->adminUser);

        $payload = [
            'name' => 'Bengaluru Tech Branch',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'country' => 'IN',
            'timezone' => 'Asia/Kolkata',
            'email' => 'bangalore@multibranch.test',
            'phone' => '+91 80 1234 5678',
            'address_line1' => 'Tower A, Manyata Tech Park',
            'pincode' => '560045',
            'latitude' => 13.0475,
            'longitude' => 77.6197,
            'department_ids' => [$this->salesDept->id],
        ];

        $response = $this->postJson('/api/v1/crm/branches', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Bengaluru Tech Branch')
            ->assertJsonPath('data.city', 'Bengaluru');

        $this->assertDatabaseHas('branches', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Bengaluru Tech Branch',
            'city' => 'Bengaluru',
            'pincode' => '560045',
        ]);

        $branchId = $response->json('data.id');
        $this->assertDatabaseHas('branch_departments', [
            'branch_id' => $branchId,
            'department_id' => $this->salesDept->id,
        ]);
    }

    /** @test */
    public function it_updates_branch_details_and_toggles_active_status(): void
    {
        Sanctum::actingAs($this->adminUser);

        $updatePayload = [
            'name' => 'Mumbai Western Flagship Hub',
            'phone' => '+91 22 9999 8888',
        ];

        $updateRes = $this->putJson("/api/v1/crm/branches/{$this->branchMumbai->id}", $updatePayload);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Mumbai Western Flagship Hub')
            ->assertJsonPath('data.phone', '+91 22 9999 8888');

        // Toggle status
        $statusRes = $this->patchJson("/api/v1/crm/branches/{$this->branchMumbai->id}/status", [
            'is_active' => false,
        ]);
        $statusRes->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('branches', [
            'id' => $this->branchMumbai->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function it_assigns_employees_and_users_to_branch(): void
    {
        Sanctum::actingAs($this->adminUser);

        $staffUser = User::create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Rahul Sharma',
            'email' => 'rahul.' . uniqid() . '@multibranch.test',
            'password' => bcrypt('password123'),
            'role' => 'employee',
        ]);
        $staffUser->workspaces()->attach($this->tenant->id, [
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $staffUser->id,
            'name' => 'Rahul Sharma',
            'email' => $staffUser->email,
            'employee_id' => 'EMP-001',
            'designation' => 'Sales Executive',
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/crm/branches/{$this->branchMumbai->id}/employees", [
            'user_ids' => [$staffUser->id],
            'department_id' => $this->salesDept->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_count', 1);

        $this->assertDatabaseHas('users', [
            'id' => $staffUser->id,
            'branch_id' => $this->branchMumbai->id,
            'department_id' => $this->salesDept->id,
        ]);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'branch_id' => $this->branchMumbai->id,
            'department_id' => $this->salesDept->id,
        ]);
    }

    /** @test */
    public function it_supports_shared_customers_with_branch_specific_interactions(): void
    {
        Sanctum::actingAs($this->adminUser);

        // One shared corporate customer across branches
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->headquarters->id, // primary HQ account
            'name' => 'Tata Consultancy Engagements',
            'email' => 'procurement@tata.example',
            'status' => 'active',
        ]);

        // Branch 1 interaction (Delhi deal)
        $delhiDeal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'branch_id' => $this->headquarters->id,
            'name' => 'North India Cloud Migration',
            'value' => 1500000,
            'stage' => 'won',
        ]);

        // Branch 2 interaction (Mumbai deal)
        $mumbaiDeal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'branch_id' => $this->branchMumbai->id,
            'name' => 'West India Security Audit',
            'value' => 800000,
            'stage' => 'negotiation',
        ]);

        $this->assertEquals($customer->id, $delhiDeal->customer_id);
        $this->assertEquals($customer->id, $mumbaiDeal->customer_id);
        $this->assertEquals($this->headquarters->id, $delhiDeal->branch_id);
        $this->assertEquals($this->branchMumbai->id, $mumbaiDeal->branch_id);

        // No duplicate customer records created
        $this->assertEquals(1, Customer::where('name', 'Tata Consultancy Engagements')->count());
    }

    /** @test */
    public function it_executes_branch_record_transfers_with_audit_trail(): void
    {
        Sanctum::actingAs($this->adminUser);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->headquarters->id,
            'name' => 'Sunil Joshi',
            'email' => 'sunil@example.com',
            'phone' => '+919876543210',
            'status' => 'open',
        ]);

        $transferPayload = [
            'transferable_type' => 'lead',
            'transferable_id' => $lead->id,
            'to_branch_id' => $this->branchMumbai->id,
            'reason' => 'Customer shifted headquarters to Mumbai BKC branch.',
            'metadata' => [
                'reassign_user_id' => $this->branchManagerUser->id,
            ],
        ];

        $response = $this->postJson('/api/v1/crm/branches/transfers', $transferPayload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.transferable_type', 'lead')
            ->assertJsonPath('data.from_branch_id', $this->headquarters->id)
            ->assertJsonPath('data.to_branch_id', $this->branchMumbai->id);

        // Verify lead branch was updated
        $lead->refresh();
        $this->assertEquals($this->branchMumbai->id, $lead->branch_id);
        $this->assertEquals($this->branchManagerUser->id, $lead->assigned_user_id);

        // Verify audit history endpoint
        $historyRes = $this->getJson('/api/v1/crm/branches/transfers/history?transferable_type=lead');
        $historyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_calculates_branch_manager_dashboard_metrics(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Seed Mumbai leads, deals, invoices, and expenses
        Lead::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'name' => 'Lead One',
            'converted_at' => Carbon::now(),
        ]);

        Deal::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'name' => 'Mumbai Enterprise Deal',
            'value' => 500000,
            'stage' => 'won',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'invoice_number' => 'INV-MUM-001',
            'total_amount' => 500000,
            'paid_amount' => 300000,
            'outstanding_amount' => 200000,
            'status' => 'partially_paid',
        ]);

        Payment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'invoice_id' => $invoice->id,
            'amount' => 300000,
            'status' => 'success',
        ]);

        Expense::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'expense_number' => 'EXP-MUM-001',
            'expense_date' => Carbon::today(),
            'title' => 'Mumbai Office Lease',
            'category' => 'Rent',
            'amount' => 80000,
            'status' => 'approved',
        ]);

        SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'ticket_number' => 'TCK-MUM-01',
            'subject' => 'Payment Gateway Timeout',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->getJson("/api/v1/crm/branches/{$this->branchMumbai->id}/dashboard");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branch.id', $this->branchMumbai->id)
            ->assertJsonPath('data.leads.total', 1)
            ->assertJsonPath('data.deals.won_count', 1)
            ->assertJsonPath('data.sales.invoiced_revenue', 500000)
            ->assertJsonPath('data.sales.collected_revenue', 300000)
            ->assertJsonPath('data.sales.pending_receivables', 200000)
            ->assertJsonPath('data.sales.expenses', 80000)
            ->assertJsonPath('data.sales.net_profit', 420000)
            ->assertJsonPath('data.support.open_tickets', 1);
    }

    /** @test */
    public function it_calculates_central_owner_consolidated_analytics(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create invoices for both branches
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->headquarters->id,
            'invoice_number' => 'INV-DEL-101',
            'total_amount' => 1000000,
            'paid_amount' => 1000000,
            'outstanding_amount' => 0,
            'status' => 'paid',
        ]);

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchMumbai->id,
            'invoice_number' => 'INV-MUM-102',
            'total_amount' => 400000,
            'paid_amount' => 200000,
            'outstanding_amount' => 200000,
            'status' => 'partially_paid',
        ]);

        $response = $this->getJson('/api/v1/crm/branches/analytics/consolidated');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_branches', 2)
            ->assertJsonPath('data.summary.total_sales', 1400000)
            ->assertJsonPath('data.summary.total_outstanding', 200000);

        $this->assertCount(2, $response->json('data.branch_comparisons'));
    }

    /** @test */
    public function it_manages_branch_operational_settings_and_sales_targets(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Update settings
        $settingsPayload = [
            'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'settings' => [
                'lead_routing' => ['strategy' => 'round_robin', 'auto_assign' => true],
                'holidays' => ['Ganesh Chaturthi', 'Maharashtra Day'],
            ],
        ];

        $settingsRes = $this->putJson("/api/v1/crm/branches/{$this->branchMumbai->id}/settings", $settingsPayload);
        $settingsRes->assertStatus(200)
            ->assertJsonPath('data.working_days', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
            ->assertJsonPath('data.settings.holidays', ['Ganesh Chaturthi', 'Maharashtra Day']);

        // Save Sales Target
        $targetPayload = [
            'period_type' => 'monthly',
            'period_year' => 2026,
            'period_number' => 11,
            'target_amount' => 2500000,
            'target_deals' => 10,
        ];

        $targetRes = $this->postJson("/api/v1/crm/branches/{$this->branchMumbai->id}/sales-targets", $targetPayload);
        $targetRes->assertStatus(201)
            ->assertJsonPath('data.target_amount', 2500000)
            ->assertJsonPath('data.period_number', 11);
    }

    /** @test */
    public function it_strictly_enforces_branch_data_isolation_and_blocks_cross_branch_tampering(): void
    {
        // Branch Manager belongs to Mumbai branch with branch-scoped role
        $branchRole = CrmRole::where('slug', 'branch_manager')->first();
        if ($branchRole) {
            $branchRole->users()->syncWithoutDetaching([$this->branchManagerUser->id => ['tenant_id' => $this->tenant->id]]);
        }

        Sanctum::actingAs($this->branchManagerUser);

        // Mumbai manager accessing Mumbai branch -> allowed
        $mumbaiRes = $this->getJson("/api/v1/crm/branches/{$this->branchMumbai->id}");
        $mumbaiRes->assertStatus(200);

        // Mumbai manager tampering ID to access Delhi HQ -> strictly 403 Forbidden
        $delhiRes = $this->getJson("/api/v1/crm/branches/{$this->headquarters->id}");
        $delhiRes->assertStatus(403);
    }

    /** @test */
    public function it_strictly_enforces_tenant_isolation_on_branches(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Foreign Competitor Corp',
            'domain' => 'competitor-' . uniqid() . '.test',
            'subscription_plan' => 'starter',
        ]);

        $otherBranch = Branch::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Chennai Branch',
            'code' => 'BR-MAA-01',
            'city' => 'Chennai',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->adminUser);

        // Admin of Tenant A accessing Tenant B's branch -> 404 Not Found
        $response = $this->getJson("/api/v1/crm/branches/{$otherBranch->id}");
        $response->assertStatus(404);
    }
}
