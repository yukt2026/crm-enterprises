<?php

namespace Tests\Feature;

use App\Domains\Identity\Services\RbacService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Workflow\Models\Workflow;
use App\Domains\Workflow\Models\WorkflowApproval;
use App\Domains\Workflow\Models\WorkflowAutomationLimit;
use App\Domains\Workflow\Models\WorkflowRun;
use App\Domains\Workflow\Models\WorkflowVersion;
use App\Domains\Workflow\Services\ConditionEvaluator;
use App\Domains\Workflow\Services\TriggerRegistry;
use App\Domains\Workflow\Services\WorkflowExecutionEngine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedWorkflowAutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected WorkflowExecutionEngine $engine;
    protected ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Automations Inc',
            'slug' => 'automations-inc',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin@automations.com',
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->user->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
            'status' => 'active',
        ]);

        $this->engine = app(WorkflowExecutionEngine::class);
        $this->evaluator = app(ConditionEvaluator::class);
    }

    public function test_trigger_catalog_contains_all_nine_modules(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/v1/crm/workflows/triggers/catalog');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertArrayHasKey('leads', $data);
        $this->assertArrayHasKey('customers', $data);
        $this->assertArrayHasKey('sales', $data);
        $this->assertArrayHasKey('finance', $data);
        $this->assertArrayHasKey('communication', $data);
        $this->assertArrayHasKey('support', $data);
        $this->assertArrayHasKey('projects', $data);
        $this->assertArrayHasKey('hr', $data);
        $this->assertArrayHasKey('appointments', $data);

        // Verify specific triggers exist
        $this->assertTrue(TriggerRegistry::exists('lead.created'));
        $this->assertTrue(TriggerRegistry::exists('deal.won'));
        $this->assertTrue(TriggerRegistry::exists('payment.received'));
        $this->assertTrue(TriggerRegistry::exists('ticket.created'));
        $this->assertTrue(TriggerRegistry::exists('appointment.booked'));
    }

    public function test_condition_evaluator_handles_complex_rules_and_nesting(): void
    {
        $context = [
            'lead' => [
                'score' => 85,
                'status' => 'qualified',
                'tags' => ['high_priority', 'enterprise'],
                'created_at' => now()->subDays(2)->toIso8601String(),
            ],
            'deal' => [
                'value' => 250000,
            ],
        ];

        // 1. Numeric and equality AND rule
        $group1 = [
            'combinator' => 'and',
            'rules' => [
                ['field' => 'lead.score', 'operator' => 'greater_or_equal', 'value' => 80],
                ['field' => 'lead.status', 'operator' => 'equals', 'value' => 'qualified'],
                ['field' => 'deal.value', 'operator' => 'greater_than', 'value' => 100000],
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($group1, $context, $this->tenant->id));

        // 2. Tag presence
        $group2 = [
            'combinator' => 'and',
            'rules' => [
                ['field' => 'lead.tags', 'operator' => 'contains', 'value' => 'enterprise'],
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($group2, $context, $this->tenant->id));

        // 3. Date comparison
        $group3 = [
            'combinator' => 'and',
            'rules' => [
                ['field' => 'lead.created_at', 'operator' => 'within_days', 'value' => 5],
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($group3, $context, $this->tenant->id));

        // 4. Nested OR inside AND
        $group4 = [
            'combinator' => 'and',
            'rules' => [
                ['field' => 'lead.score', 'operator' => 'greater_than', 'value' => 50],
                [
                    'combinator' => 'or',
                    'rules' => [
                        ['field' => 'lead.status', 'operator' => 'equals', 'value' => 'lost'],
                        ['field' => 'deal.value', 'operator' => 'greater_than', 'value' => 200000],
                    ],
                ],
            ],
        ];
        $this->assertTrue($this->evaluator->evaluate($group4, $context, $this->tenant->id));
    }

    public function test_workflow_execution_creates_task_and_updates_lead(): void
    {
        $this->actingAs($this->user);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vikas Mehta',
            'phone' => '+919876543210',
            'email' => 'vikas@enterprise.com',
            'status' => 'new',
            'score' => 90,
        ]);

        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fast-Track Lead Qualification',
            'category' => 'crm',
            'status' => 'published',
            'trigger_type' => 'lead.created',
            'current_version' => 1,
            'is_active' => true,
        ]);

        $definition = [
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'New Lead']],
                ['id' => 'n_cond', 'type' => 'condition', 'data' => [
                    'label' => 'Score >= 80',
                    'conditions' => [
                        'combinator' => 'and',
                        'rules' => [
                            ['field' => 'lead.score', 'operator' => 'greater_or_equal', 'value' => 80],
                        ],
                    ],
                ]],
                ['id' => 'n_act1', 'type' => 'action', 'data' => [
                    'label' => 'Update Status to Qualified',
                    'action_type' => 'crm.update_lead_status',
                    'params' => ['status' => 'qualified'],
                ]],
                ['id' => 'n_act2', 'type' => 'action', 'data' => [
                    'label' => 'Create Follow-up Task',
                    'action_type' => 'crm.create_task',
                    'params' => ['title' => 'VIP Followup within 2 hours', 'priority' => 'high'],
                ]],
            ],
            'edges' => [
                ['source' => 'n_trig', 'target' => 'n_cond'],
                ['source' => 'n_cond', 'target' => 'n_act1', 'sourceHandle' => 'true'],
                ['source' => 'n_act1', 'target' => 'n_act2'],
            ],
        ];

        WorkflowVersion::create([
            'tenant_id' => $this->tenant->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => true,
        ]);

        $dispatchResult = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'lead.created',
            payload: [
                'entity_id' => $lead->id,
                'entity_type' => 'lead',
                'lead' => $lead->toArray(),
            ]
        );

        $this->assertEquals('dispatched', $dispatchResult['status']);
        $this->assertEquals(1, $dispatchResult['workflows_matched']);

        // Verify lead was updated
        $lead->refresh();
        $this->assertEquals('qualified', $lead->status);

        // Verify task was created
        $run = $dispatchResult['runs'][0];
        $this->assertEquals('completed', $run->status);
        $this->assertDatabaseHas('tasks', [
            'tenant_id' => $this->tenant->id,
            'title' => 'VIP Followup within 2 hours',
            'lead_id' => $lead->id,
        ]);
    }

    public function test_workflow_approval_node_pauses_and_resumes_on_decision(): void
    {
        $this->actingAs($this->user);

        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High-Value Discount Approval',
            'category' => 'sales',
            'status' => 'published',
            'trigger_type' => 'deal.won',
            'current_version' => 1,
            'is_active' => true,
        ]);

        $definition = [
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Deal Won']],
                ['id' => 'n_appr', 'type' => 'approval', 'data' => [
                    'label' => 'Manager Discount Signoff',
                    'title' => 'Sign off 25% Enterprise Discount',
                    'category' => 'discount',
                    'approver_role' => 'sales_manager',
                ]],
                ['id' => 'n_act_app', 'type' => 'action', 'data' => [
                    'label' => 'Create Delivery Project',
                    'action_type' => 'projects.create_project_draft',
                    'params' => ['name' => 'Enterprise Onboarding'],
                ]],
            ],
            'edges' => [
                ['source' => 'n_trig', 'target' => 'n_appr'],
                ['source' => 'n_appr', 'target' => 'n_act_app', 'sourceHandle' => 'approved'],
            ],
        ];

        WorkflowVersion::create([
            'tenant_id' => $this->tenant->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => true,
        ]);

        $deal = \App\Domains\CRM\Models\Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Big Enterprise Deal',
            'value' => 500000,
            'stage' => 'won',
            'pipeline_id' => 1,
        ]);

        $dispatch = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'deal.won',
            payload: [
                'entity_id' => $deal->id,
                'entity_type' => 'deal',
                'deal' => $deal->toArray(),
            ]
        );

        $run = $dispatch['runs'][0];
        $this->assertEquals('waiting_approval', $run->status);

        // Verify approval record was created
        $approval = WorkflowApproval::where('workflow_run_id', $run->id)->first();
        $this->assertNotNull($approval);
        $this->assertEquals('pending', $approval->status);
        $this->assertEquals('sales_manager', $approval->approver_role);

        // Call the approval decision endpoint
        $decisionRes = $this->postJson("/api/v1/crm/workflows/approvals/{$approval->id}/decide", [
            'decision' => 'approve',
            'comments' => 'Discount within authorized Q4 margin policy.',
        ]);

        $decisionRes->assertStatus(200);
        $approval->refresh();
        $this->assertEquals('approved', $approval->status);

        // Verify workflow resumed and finished project creation
        $run->refresh();
        $this->assertEquals('completed', $run->status);
        $this->assertDatabaseHas('projects', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Onboarding',
        ]);
    }

    public function test_loop_protection_halts_on_recursion_depth_or_entity_flooding(): void
    {
        // 1. Depth loop protection
        $resDepth = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'lead.updated',
            payload: ['entity_type' => 'lead', 'entity_id' => 44],
            idempotencyKey: null,
            parentRunId: 1,
            depth: 11
        );

        $this->assertEquals('loop_detected', $resDepth['status']);
        $this->assertStringContainsString('maximum recursion depth', $resDepth['message']);

        // 2. Entity flooding rate-limit protection: simulate 10 recent runs
        $dummyWf = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Flood Test',
            'category' => 'crm',
            'trigger_type' => 'lead.updated',
        ]);
        $dummyVer = WorkflowVersion::create([
            'tenant_id' => $this->tenant->id,
            'workflow_id' => $dummyWf->id,
            'version' => 1,
            'definition' => ['nodes' => [], 'edges' => []],
        ]);

        for ($i = 0; $i < 10; $i++) {
            WorkflowRun::create([
                'tenant_id' => $this->tenant->id,
                'workflow_id' => $dummyWf->id,
                'workflow_version_id' => $dummyVer->id,
                'trigger_event' => 'lead.updated',
                'entity_type' => 'lead',
                'entity_id' => 999,
                'status' => 'completed',
            ]);
        }

        $resFlood = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'lead.updated',
            payload: ['entity_type' => 'lead', 'entity_id' => 999]
        );

        $this->assertEquals('loop_detected', $resFlood['status']);
        $this->assertStringContainsString('triggered 10 runs in 15 minutes', $resFlood['message']);
    }

    public function test_idempotency_prevents_duplicate_runs(): void
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Payment Confirmation Sequence',
            'category' => 'finance',
            'status' => 'published',
            'trigger_type' => 'payment.received',
            'current_version' => 1,
            'is_active' => true,
        ]);

        WorkflowVersion::create([
            'tenant_id' => $this->tenant->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => [
                'nodes' => [['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Payment']]],
                'edges' => [],
            ],
            'is_published' => true,
        ]);

        $key = 'razorpay_event_pay_1234567890';

        $run1 = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'payment.received',
            payload: ['amount' => 50000],
            idempotencyKey: $key
        );
        $this->assertEquals('dispatched', $run1['status']);

        // Second dispatch with same idempotency key
        $run2 = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'payment.received',
            payload: ['amount' => 50000],
            idempotencyKey: $key
        );
        $this->assertEquals('duplicate_ignored', $run2['status']);
    }

    public function test_dry_run_simulation_mode_never_causes_side_effects(): void
    {
        $this->actingAs($this->user);

        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Simulated Automation',
            'category' => 'crm',
            'status' => 'draft',
            'trigger_type' => 'lead.created',
            'current_version' => 1,
            'is_active' => false,
        ]);

        $definition = [
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'New Lead']],
                ['id' => 'n_act', 'type' => 'action', 'data' => [
                    'label' => 'Send WhatsApp Broadcast',
                    'action_type' => 'communication.send_whatsapp_template',
                    'params' => ['to' => '+919999999999', 'template_name' => 'sales_offer'],
                ]],
            ],
            'edges' => [
                ['source' => 'n_trig', 'target' => 'n_act'],
            ],
        ];

        WorkflowVersion::create([
            'tenant_id' => $this->tenant->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => false,
        ]);

        $response = $this->postJson("/api/v1/crm/workflows/{$workflow->id}/simulate", [
            'sample_payload' => ['lead' => ['name' => 'Test Lead', 'phone' => '+919999999999']],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $trace = $response->json('data.trace');
        $this->assertEquals('simulated', $trace[1]['output']['status']);

        // Verify zero persistent WorkflowRun or logs created in test simulation
        $this->assertEquals(0, WorkflowRun::count());
    }

    public function test_tenant_limits_and_metrics_endpoints(): void
    {
        $this->actingAs($this->user);

        // 1. Get Limits
        $limitsRes = $this->getJson('/api/v1/crm/workflows/limits');
        $limitsRes->assertStatus(200);
        $limitsRes->assertJsonPath('data.limits.max_monthly_executions', 50000);

        // 2. Update Limits
        $updateRes = $this->putJson('/api/v1/crm/workflows/limits', [
            'max_monthly_executions' => 75000,
            'max_active_workflows' => 100,
        ]);
        $updateRes->assertStatus(200);
        $this->assertEquals(75000, WorkflowAutomationLimit::getForTenant($this->tenant->id)->max_monthly_executions);

        // 3. Get Metrics
        $metricsRes = $this->getJson('/api/v1/crm/workflows/dashboard/metrics');
        $metricsRes->assertStatus(200);
        $metricsRes->assertJsonStructure([
            'success',
            'data' => [
                'total_workflows',
                'active_workflows',
                'paused_workflows',
                'total_runs',
                'completed_runs',
                'failed_runs',
                'waiting_runs',
                'pending_approvals',
                'success_rate',
                'monthly_executions',
                'monthly_limit',
            ],
        ]);
    }

    public function test_delay_node_schedules_resume_and_command_resumes_it(): void
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Delayed Notification',
            'category' => 'crm',
            'status' => 'published',
            'trigger_type' => 'lead.created',
            'current_version' => 1,
            'is_active' => true,
        ]);

        $definition = [
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Lead Created']],
                ['id' => 'n_delay', 'type' => 'delay', 'data' => [
                    'label' => 'Wait 2 Hours',
                    'duration' => 2,
                    'unit' => 'hours',
                    'working_hours_only' => true,
                ]],
                ['id' => 'n_act', 'type' => 'action', 'data' => [
                    'label' => 'Create Follow-up Note',
                    'action_type' => 'crm.add_note',
                    'params' => ['note' => '2-hour delayed followup note'],
                ]],
            ],
            'edges' => [
                ['source' => 'n_trig', 'target' => 'n_delay'],
                ['source' => 'n_delay', 'target' => 'n_act'],
            ],
        ];

        WorkflowVersion::create([
            'tenant_id' => $this->tenant->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => true,
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Delay Test User',
            'phone' => '+919988776655',
        ]);

        $dispatch = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'lead.created',
            payload: ['entity_id' => $lead->id, 'entity_type' => 'lead', 'lead' => $lead->toArray()]
        );

        $run = $dispatch['runs'][0];
        $this->assertEquals('waiting', $run->status);
        $this->assertNotNull($run->resume_at);

        // Manually age the resume_at to 1 minute ago to simulate elapsed delay
        $run->update(['resume_at' => now()->subMinute()]);

        // Run the console command
        $this->artisan('workflow:process-delays')->assertSuccessful();

        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }

    public function test_monthly_usage_limits_blocks_execution_when_exhausted(): void
    {
        $limits = WorkflowAutomationLimit::getForTenant($this->tenant->id);
        $limits->update([
            'max_monthly_executions' => 10,
            'current_month_executions' => 10,
        ]);

        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Limit Enforced Workflow',
            'category' => 'crm',
            'status' => 'published',
            'trigger_type' => 'lead.created',
            'current_version' => 1,
            'is_active' => true,
        ]);

        $res = $this->engine->trigger(
            tenantId: $this->tenant->id,
            triggerType: 'lead.created',
            payload: ['lead' => ['name' => 'Blocked Lead']]
        );

        $this->assertEquals('limit_exceeded', $res['status']);
        $this->assertStringContainsString('limit of 10 executions reached', $res['message']);
    }
}
