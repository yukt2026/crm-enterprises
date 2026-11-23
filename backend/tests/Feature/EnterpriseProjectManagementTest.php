<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskTimeLog;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $managerUser;
    protected User $staffUser;
    protected Customer $customer;
    protected string $adminToken;
    protected string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Wipro Digital Enterprise',
            'slug' => 'wipro-digital',
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

        $this->managerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Project Manager',
        ]);
        $this->managerUser->workspaces()->attach($this->tenant->id, [
            'role' => 'Project Manager',
            'status' => 'active',
        ]);

        $this->staffUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Software Engineer',
        ]);
        $this->staffUser->workspaces()->attach($this->tenant->id, [
            'role' => 'Software Engineer',
            'status' => 'active',
        ]);
        $this->staffToken = $this->staffUser->createToken('staff-token')->plainTextToken;

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Infosys Technologies Ltd',
            'email' => 'client@infosys.com',
            'phone' => '+91 9876543210',
            'status' => 'active',
        ]);
    }

    public function test_can_create_project_with_enterprise_code_and_milestones(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/crm/projects', [
                'name' => 'Enterprise Cloud Migration 2027',
                'customer_id' => $this->customer->id,
                'budget' => 750000.00,
                'labor_cost_rate' => 600.00,
                'billing_rate' => 1500.00,
                'status' => 'planned',
                'priority' => 'high',
                'milestones' => [
                    [
                        'title' => 'Architecture & Discovery',
                        'amount' => 200000.00,
                        'order_index' => 1,
                    ],
                    [
                        'title' => 'Migration Execution',
                        'amount' => 400000.00,
                        'order_index' => 2,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $data = $response->json('data');
        $this->assertNotEmpty($data['code']);
        $this->assertStringStartsWith('PRJ-', $data['code']);
        $this->assertEquals('Enterprise Cloud Migration 2027', $data['name']);
        $this->assertCount(2, $data['milestones']);
    }

    public function test_can_create_project_from_won_deal(): void
    {
        $deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Global ERP Rollout',
            'customer_id' => $this->customer->id,
            'value' => 1250000.00,
            'stage' => 'won',
            'assigned_user_id' => $this->managerUser->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/crm/projects/create-from-deal', [
                'deal_id' => $deal->id,
                'labor_cost_rate' => 800.00,
            ]);

        $response->assertStatus(201);
        $project = $response->json('data');
        $this->assertEquals($deal->id, $project['deal_id']);
        $this->assertEquals(1250000.00, (float) $project['budget']);
        $this->assertEquals($this->managerUser->id, $project['manager_user_id']);
    }

    public function test_project_lifecycle_transitions_with_audit_history(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mobile App Re-architecture',
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'budget' => 500000,
        ]);

        // Transition: draft -> active
        $res1 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/projects/{$project->id}/transition-status", [
                'status' => 'active',
                'reason' => 'Client approved SOW and kickoff scheduled',
            ]);

        $res1->assertStatus(200);
        $this->assertEquals('active', $res1->json('data.status'));

        // Transition: active -> completed
        $res2 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/projects/{$project->id}/transition-status", [
                'status' => 'completed',
                'reason' => 'All milestones delivered and signed off',
            ]);

        $res2->assertStatus(200);
        $this->assertEquals('completed', $res2->json('data.status'));
        $this->assertEquals(100, $res2->json('data.progress'));

        // Verify history records
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'from_status' => 'draft',
            'to_status' => 'active',
        ]);
        $this->assertDatabaseHas('project_status_histories', [
            'project_id' => $project->id,
            'from_status' => 'active',
            'to_status' => 'completed',
        ]);
    }

    public function test_can_create_task_with_subtasks_labels_and_multiple_assignees(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'FinTech Integration',
            'budget' => 300000,
        ]);

        // Create Parent Task
        $parentRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/crm/tasks', [
                'project_id' => $project->id,
                'title' => 'Design Payment Gateway Adapter',
                'priority' => 'urgent',
                'labels' => ['backend', 'payments', 'v2'],
                'assignee_ids' => [$this->managerUser->id, $this->staffUser->id],
            ]);

        $parentRes->assertStatus(201);
        $parentId = $parentRes->json('data.id');
        $this->assertCount(2, $parentRes->json('data.assignees'));

        // Create Subtask
        $subRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/crm/tasks', [
                'project_id' => $project->id,
                'parent_task_id' => $parentId,
                'title' => 'Implement Razorpay Webhook Handler',
                'priority' => 'high',
                'assigned_user_id' => $this->staffUser->id,
            ]);

        $subRes->assertStatus(201);
        $this->assertEquals($parentId, $subRes->json('data.parent_task_id'));
    }

    public function test_task_dependency_and_circular_dependency_prevention(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Database Sharding',
            'budget' => 200000,
        ]);

        $taskA = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'title' => 'Task A: DB Schema Design',
            'status' => 'in_progress',
        ]);

        $taskB = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'title' => 'Task B: Migration Script',
            'status' => 'todo',
        ]);

        $taskC = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'title' => 'Task C: Production Deployment',
            'status' => 'todo',
        ]);

        // Task B depends on Task A
        $dep1 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/tasks/{$taskB->id}/dependencies", [
                'depends_on_task_id' => $taskA->id,
                'lag_days' => 1,
            ]);
        $dep1->assertStatus(201);

        // Task C depends on Task B
        $dep2 = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/tasks/{$taskC->id}/dependencies", [
                'depends_on_task_id' => $taskB->id,
            ]);
        $dep2->assertStatus(201);

        // Circular attempt: Task A depends on Task C (A -> C -> B -> A => cycle!)
        $cycleRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/tasks/{$taskA->id}/dependencies", [
                'depends_on_task_id' => $taskC->id,
            ]);
        $cycleRes->assertStatus(422);

        // Verify task B cannot be completed while prerequisite task A is not done
        $completeRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/tasks/{$taskB->id}/complete");
        $completeRes->assertStatus(422);
    }

    public function test_task_live_timer_start_and_stop(): void
    {
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Write Unit Tests for Project Profitability',
            'status' => 'in_progress',
        ]);

        // Start live timer
        $startRes = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
            ->postJson("/api/v1/crm/tasks/{$task->id}/start-timer");

        $startRes->assertStatus(201);
        $logId = $startRes->json('data.id');
        $this->assertTrue($startRes->json('data.is_running'));

        // Stop live timer
        $stopRes = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
            ->postJson("/api/v1/crm/tasks/stop-timer/{$logId}", [
                'description' => 'Completed initial unit tests for profit calculation',
            ]);

        $stopRes->assertStatus(200);
        $this->assertFalse($stopRes->json('data.is_running'));
        $this->assertGreaterThanOrEqual(1, $stopRes->json('data.duration_minutes'));
    }

    public function test_manual_time_logging_with_overlap_prevention(): void
    {
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Bug Triage and Refactor',
            'status' => 'in_progress',
        ]);

        $today = Carbon::today()->toDateString();

        // 1st time log: 10:00 to 12:00
        $res1 = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
            ->postJson("/api/v1/crm/tasks/{$task->id}/log-time", [
                'start_time' => "{$today} 10:00:00",
                'end_time' => "{$today} 12:00:00",
                'description' => 'Morning debugging session',
                'is_billable' => true,
            ]);
        $res1->assertStatus(201);

        // 2nd time log overlapping: 11:30 to 13:00 -> should be rejected
        $overlapRes = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
            ->postJson("/api/v1/crm/tasks/{$task->id}/log-time", [
                'start_time' => "{$today} 11:30:00",
                'end_time' => "{$today} 13:00:00",
                'description' => 'Overlapping work session',
            ]);
        $overlapRes->assertStatus(422);

        // 3rd time log non-overlapping: 14:00 to 16:00 -> should succeed
        $res3 = $this->withHeader('Authorization', "Bearer {$this->staffToken}")
            ->postJson("/api/v1/crm/tasks/{$task->id}/log-time", [
                'start_time' => "{$today} 14:00:00",
                'end_time' => "{$today} 16:00:00",
                'description' => 'Afternoon code review',
            ]);
        $res3->assertStatus(201);
    }

    public function test_manager_timesheet_approval_and_rejection(): void
    {
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Security Audit Remediation',
        ]);

        $log = TaskTimeLog::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $task->id,
            'user_id' => $this->staffUser->id,
            'duration_minutes' => 120,
            'is_billable' => true,
            'status' => 'pending',
        ]);

        // Manager approves
        $approveRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/tasks/timesheets/{$log->id}/approve");

        $approveRes->assertStatus(200);
        $this->assertEquals('approved', $approveRes->json('data.status'));

        // Manager rejects with reason
        $rejectRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/tasks/timesheets/{$log->id}/reject", [
                'reason' => 'Log duration exceeds agreed timebox, please revise',
            ]);

        $rejectRes->assertStatus(200);
        $this->assertEquals('rejected', $rejectRes->json('data.status'));
        $this->assertEquals('Log duration exceeds agreed timebox, please revise', $rejectRes->json('data.rejection_reason'));
    }

    public function test_milestone_deliverables_review_and_client_portal_approval(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'E-Commerce Platform',
            'status' => 'active',
            'budget' => 600000,
        ]);

        $milestone = ProjectMilestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'title' => 'Milestone 1: UI Mockups & Prototype',
            'amount' => 150000.00,
            'status' => 'in_progress',
            'approval_status' => 'pending_review',
        ]);

        // 1. Internal QA Review Submission
        $subRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/milestones/{$milestone->id}/submit-review", [
                'deliverables_summary' => 'Figma link and clickable mobile prototype complete',
            ]);
        $subRes->assertStatus(200);

        // 2. Manager Internal Approval
        $appRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/milestones/{$milestone->id}/approve-internal", [
                'notes' => 'Internal QA passed all checklist criteria',
            ]);
        $appRes->assertStatus(200);
        $this->assertEquals('internal_approved', $appRes->json('data.approval_status'));

        // 3. Dispatch to Client Portal
        $sendRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/milestones/{$milestone->id}/send-to-client");
        $sendRes->assertStatus(200);
        $token = $sendRes->json('data.client_review_token');
        $this->assertNotEmpty($token);

        // 4. Client Portal Approval
        $clientRes = $this->postJson("/api/v1/portal/milestones/{$token}/approve", [
            'client_name' => 'Mr. Nandan Nilekani',
            'feedback' => 'Designs look spectacular. Approved for sprint 2.',
        ]);
        $clientRes->assertStatus(200);
        $this->assertEquals('client_approved', $clientRes->json('data.approval_status'));
        $this->assertEquals('completed', $clientRes->json('data.status'));
        $this->assertEquals(100, $clientRes->json('data.progress'));

        // Audit log exists
        $this->assertDatabaseHas('milestone_approval_logs', [
            'milestone_id' => $milestone->id,
            'actor_type' => 'client',
            'action' => 'client_approved',
            'actor_name' => 'Mr. Nandan Nilekani',
        ]);
    }

    public function test_project_documents_client_visibility_isolation(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Secure Banking Portal',
            'budget' => 800000,
        ]);

        // Upload Internal-only document
        $internalDoc = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/projects/{$project->id}/documents", [
                'name' => 'Internal_Architecture_Notes_Confidential.pdf',
                'category' => 'spec',
                'is_client_visible' => false,
            ]);
        $internalDoc->assertStatus(201);

        // Upload Client-visible deliverable
        $clientDoc = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/crm/projects/{$project->id}/documents", [
                'name' => 'Sprint_1_Deliverables_Report.pdf',
                'category' => 'deliverable',
                'is_client_visible' => true,
            ]);
        $clientDoc->assertStatus(201);

        // Fetch all documents (internal view) -> 2 items
        $allRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/crm/projects/{$project->id}/documents");
        $allRes->assertStatus(200);
        $this->assertCount(2, $allRes->json('data'));

        // Fetch client-only view -> 1 item
        $clientRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/crm/projects/{$project->id}/documents?client_only=true");
        $clientRes->assertStatus(200);
        $this->assertCount(1, $clientRes->json('data'));
        $this->assertEquals('Sprint_1_Deliverables_Report.pdf', $clientRes->json('data.0.name'));
    }

    public function test_real_time_project_financials_and_profitability_calculation(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Custom SaaS Implementation',
            'budget' => 500000.00,
            'labor_cost_rate' => 500.00,
            'billing_rate' => 1200.00,
            'status' => 'active',
        ]);

        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'title' => 'Core Architecture',
        ]);

        // 10 hours of approved labor: 10 hrs * ₹500/hr = ₹5,000 labor cost
        TaskTimeLog::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_id' => $task->id,
            'user_id' => $this->staffUser->id,
            'duration_minutes' => 600, // 10 hours
            'hourly_rate' => 1200.00,
            'is_billable' => true,
            'status' => 'approved',
        ]);

        // Project Expense: ₹15,000 cloud infrastructure
        Expense::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'expense_number' => 'EXP-2027-PRJ-001',
            'category' => 'Infrastructure',
            'title' => 'AWS Cloud Servers - Development Tier',
            'amount' => 15000.00,
            'status' => 'approved',
            'expense_date' => Carbon::today(),
        ]);

        // Invoiced Revenue: ₹200,000
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-2027-PRJ-001',
            'total_amount' => 200000.00,
            'status' => 'sent',
            'issue_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(15),
        ]);

        $finRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/crm/projects/{$project->id}/financials");

        $finRes->assertStatus(200);
        $fin = $finRes->json('data');

        $this->assertEquals(500000.00, (float) $fin['budget']);
        $this->assertEquals(200000.00, (float) $fin['invoiced_revenue']);
        $this->assertEquals(15000.00, (float) $fin['actual_expenses']);
        $this->assertEquals(5000.00, (float) $fin['labor_costs']);
        $this->assertEquals(20000.00, (float) $fin['total_costs']); // 15000 + 5000
        $this->assertEquals(180000.00, (float) $fin['gross_profit']); // 200000 - 20000
        $this->assertEquals(90.0, (float) $fin['profit_margin_percentage']);
    }

    public function test_gantt_chart_and_team_workload_metrics(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Data Lake Construction',
            'status' => 'active',
            'budget' => 450000,
        ]);

        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'title' => 'Kafka Cluster Setup',
            'start_date' => Carbon::today(),
            'due_date' => Carbon::today()->addDays(5),
            'estimated_hours' => 25,
            'assigned_user_id' => $this->staffUser->id,
        ]);

        // Gantt Data
        $ganttRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/crm/projects/{$project->id}/gantt");
        $ganttRes->assertStatus(200);
        $this->assertNotEmpty($ganttRes->json('data.tasks'));
        $this->assertEquals('Kafka Cluster Setup', $ganttRes->json('data.tasks.0.title'));

        // Workload Report
        $workloadRes = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/crm/projects/workload');
        $workloadRes->assertStatus(200);
        $this->assertNotEmpty($workloadRes->json('data'));
    }
}
