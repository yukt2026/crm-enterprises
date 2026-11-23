<?php

namespace Tests\Feature;

use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Workflow\Models\Workflow;
use App\Domains\Workflow\Models\WorkflowRun;
use App\Domains\Workflow\Models\WorkflowVersion;
use App\Domains\Workflow\Services\WorkflowExecutionEngine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisualWorkflowAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected string $tokenAdmin;
    protected WorkflowExecutionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Automations Corp',
            'slug' => 'acme-auto',
            'subscription_tier' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Rival Systems',
            'slug' => 'rival-sys',
            'subscription_tier' => 'professional',
        ]);

        $this->userAdmin = User::create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Sarah Workflow Admin',
            'email' => 'sarah@acme.com',
            'password' => bcrypt('AdminPass2026!'),
            'role' => 'Business Admin',
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);
        $this->tokenAdmin = $this->userAdmin->createToken('test_token')->plainTextToken;

        $this->engine = app(WorkflowExecutionEngine::class);
    }

    /** @test */
    public function it_creates_versioned_workflow_definitions()
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson('/api/v1/crm/workflows', [
                'name' => 'Lead Qualification Sequence',
                'category' => 'crm',
                'trigger_type' => 'lead.created',
                'description' => 'Automated branching based on lead score',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Lead Qualification Sequence')
            ->assertJsonPath('data.current_version', 1)
            ->assertJsonPath('data.status', 'draft');

        $workflowId = $res->json('data.id');

        // Update definition -> produces version 2
        $resUpdate = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->putJson("/api/v1/crm/workflows/{$workflowId}", [
                'definition' => [
                    'nodes' => [
                        ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Lead Created']],
                        ['id' => 'n_act', 'type' => 'action', 'data' => ['label' => 'Add VIP Tag', 'action_type' => 'crm.add_tags', 'params' => ['tags' => ['vip']]]],
                    ],
                    'edges' => [
                        ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_act'],
                    ],
                ],
                'changelog' => 'Added VIP tagging action',
            ]);

        $resUpdate->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_version', 2);

        $this->assertDatabaseHas('workflow_versions', [
            'workflow_id' => $workflowId,
            'version' => 2,
            'changelog' => 'Added VIP tagging action',
        ]);
    }

    /** @test */
    public function it_evaluates_if_else_branching_and_executes_actions()
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'High Budget Routing',
            'category' => 'crm',
            'trigger_type' => 'lead.created',
            'status' => 'published',
            'is_active' => true,
            'current_version' => 1,
        ]);

        $definition = [
            'nodes' => [
                ['id' => 'n1_trig', 'type' => 'trigger', 'data' => ['label' => 'New Lead']],
                ['id' => 'n2_cond', 'type' => 'condition', 'data' => [
                    'label' => 'Estimated Value >= 50000?',
                    'conditions' => [
                        'combinator' => 'and',
                        'rules' => [
                            ['field' => 'lead.estimated_value', 'operator' => 'greater_or_equal', 'value' => 50000],
                        ],
                    ],
                ]],
                ['id' => 'n3_act_true', 'type' => 'action', 'data' => [
                    'label' => 'Upgrade to Qualified',
                    'action_type' => 'crm.update_lead_status',
                    'params' => ['status' => 'qualified'],
                ]],
                ['id' => 'n4_act_false', 'type' => 'action', 'data' => [
                    'label' => 'Set Cold Nurture',
                    'action_type' => 'crm.update_lead_status',
                    'params' => ['status' => 'cold_nurture'],
                ]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n1_trig', 'target' => 'n2_cond'],
                ['id' => 'e2', 'source' => 'n2_cond', 'target' => 'n3_act_true', 'sourceHandle' => 'true', 'label' => 'true'],
                ['id' => 'e3', 'source' => 'n2_cond', 'target' => 'n4_act_false', 'sourceHandle' => 'false', 'label' => 'false'],
            ],
        ];

        WorkflowVersion::create([
            'tenant_id' => $this->tenantA->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => true,
        ]);

        // Case 1: High budget lead -> takes True branch
        $highLead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Aditya Enterprise',
            'estimated_value' => 100000,
            'status' => 'new',
        ]);

        $resHigh = $this->engine->trigger($this->tenantA->id, 'lead.created', [
            'entity_type' => 'lead',
            'entity_id' => $highLead->id,
            'lead' => $highLead->toArray(),
        ]);

        $this->assertEquals('dispatched', $resHigh['status']);
        $this->assertEquals('qualified', $highLead->fresh()->status);

        // Case 2: Low budget lead -> takes False branch
        $lowLead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Small Starter',
            'estimated_value' => 10000,
            'status' => 'new',
        ]);

        $resLow = $this->engine->trigger($this->tenantA->id, 'lead.created', [
            'entity_type' => 'lead',
            'entity_id' => $lowLead->id,
            'lead' => $lowLead->toArray(),
        ]);

        $this->assertEquals('cold_nurture', $lowLead->fresh()->status);
    }

    /** @test */
    public function it_handles_delay_nodes_and_resumes_execution()
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Delayed Task Reminder',
            'category' => 'crm',
            'trigger_type' => 'lead.created',
            'status' => 'published',
            'is_active' => true,
            'current_version' => 1,
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pooja Delayed',
            'status' => 'new',
        ]);

        $definition = [
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Start']],
                ['id' => 'n_delay', 'type' => 'delay', 'data' => ['label' => 'Wait 2 Hours', 'duration' => 2, 'unit' => 'hours']],
                ['id' => 'n_task', 'type' => 'action', 'data' => [
                    'label' => 'Create Followup Task',
                    'action_type' => 'crm.create_task',
                    'params' => ['title' => 'Follow up on Pooja Delayed', 'lead_id' => $lead->id],
                ]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_delay'],
                ['id' => 'e2', 'source' => 'n_delay', 'target' => 'n_task'],
            ],
        ];

        WorkflowVersion::create([
            'tenant_id' => $this->tenantA->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => true,
        ]);

        $dispatch = $this->engine->trigger($this->tenantA->id, 'lead.created', [
            'lead' => $lead->toArray(),
        ]);

        $run = $dispatch['runs'][0];
        $this->assertEquals('waiting', $run->status);
        $this->assertNotNull($run->resume_at);

        // Resume suspended run
        $resumedRun = $this->engine->resume($run);
        $this->assertEquals('completed', $resumedRun->status);

        $this->assertDatabaseHas('tasks', [
            'tenant_id' => $this->tenantA->id,
            'title' => 'Follow up on Pooja Delayed',
        ]);
    }

    /** @test */
    public function it_enforces_idempotency_and_ignores_duplicate_events()
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Single Event Handler',
            'category' => 'crm',
            'trigger_type' => 'lead.created',
            'status' => 'published',
            'is_active' => true,
            'current_version' => 1,
        ]);

        WorkflowVersion::create([
            'tenant_id' => $this->tenantA->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => [
                'nodes' => [['id' => 'n1', 'type' => 'trigger', 'data' => ['label' => 'Trig']]],
                'edges' => [],
            ],
            'is_published' => true,
        ]);

        $idempotencyKey = 'webhook_event_unique_uuid_9999';

        // First trigger
        $first = $this->engine->trigger($this->tenantA->id, 'lead.created', ['test' => 1], $idempotencyKey);
        $this->assertEquals('dispatched', $first['status']);

        // Duplicate trigger with identical key
        $second = $this->engine->trigger($this->tenantA->id, 'lead.created', ['test' => 1], $idempotencyKey);
        $this->assertEquals('duplicate_ignored', $second['status']);

        // Only 1 run recorded
        $this->assertEquals(1, WorkflowRun::where('idempotency_key', $idempotencyKey)->count());
    }

    /** @test */
    public function it_rechecks_communication_consent_and_suppression_on_actions()
    {
        // Suppress email address
        CommunicationSuppression::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'email',
            'identifier' => 'suppressed@client.com',
            'reason' => 'unsubscribe',
            'suppressed_at' => now(),
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Opted Out Client',
            'email' => 'suppressed@client.com',
            'status' => 'new',
        ]);

        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Auto Emailer',
            'category' => 'marketing',
            'trigger_type' => 'lead.created',
            'status' => 'published',
            'is_active' => true,
            'current_version' => 1,
        ]);

        WorkflowVersion::create([
            'tenant_id' => $this->tenantA->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => [
                'nodes' => [
                    ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Start']],
                    ['id' => 'n_email', 'type' => 'action', 'data' => [
                        'label' => 'Send Promo Email',
                        'action_type' => 'communication.send_email',
                        'params' => ['to' => 'suppressed@client.com', 'subject' => 'Hello', 'content' => 'Offer details'],
                    ]],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_email'],
                ],
            ],
            'is_published' => true,
        ]);

        $dispatch = $this->engine->trigger($this->tenantA->id, 'lead.created', [
            'lead' => $lead->toArray(),
        ]);

        $stepLog = $dispatch['runs'][0]->stepLogs()->where('node_id', 'n_email')->first();
        $this->assertNotNull($stepLog);
        $this->assertEquals('suppressed', $stepLog->output_state['status']);
    }

    /** @test */
    public function it_supports_manual_retry_on_failed_workflow_runs()
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Failing Automation',
            'category' => 'crm',
            'trigger_type' => 'lead.created',
            'status' => 'published',
            'is_active' => true,
            'current_version' => 1,
        ]);

        $version = WorkflowVersion::create([
            'tenant_id' => $this->tenantA->id,
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => [
                'nodes' => [
                    ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Trig']],
                ],
                'edges' => [],
            ],
            'is_published' => true,
        ]);

        $run = WorkflowRun::create([
            'tenant_id' => $this->tenantA->id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'trigger_event' => 'lead.created',
            'status' => 'failed',
            'error_message' => 'Transient upstream network timeout',
            'retry_count' => 0,
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson("/api/v1/crm/workflow-runs/{$run->id}/retry");

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.retry_count', 1);
    }

    /** @test */
    public function it_supports_dry_run_test_simulation_without_modifying_database()
    {
        $workflow = Workflow::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Simulator Test',
            'category' => 'crm',
            'trigger_type' => 'lead.created',
            'status' => 'draft',
            'current_version' => 1,
        ]);

        $definition = [
            'nodes' => [
                ['id' => 'n_trig', 'type' => 'trigger', 'data' => ['label' => 'Trig']],
                ['id' => 'n_cond', 'type' => 'condition', 'data' => [
                    'label' => 'Score >= 80?',
                    'conditions' => [
                        'combinator' => 'and',
                        'rules' => [['field' => 'lead.score', 'operator' => 'greater_or_equal', 'value' => 80]],
                    ],
                ]],
                ['id' => 'n_act', 'type' => 'action', 'data' => [
                    'label' => 'Assign Lead',
                    'action_type' => 'crm.assign_lead',
                    'params' => ['assigned_user_id' => 999],
                ]],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'n_trig', 'target' => 'n_cond'],
                ['id' => 'e2', 'source' => 'n_cond', 'target' => 'n_act', 'sourceHandle' => 'true'],
            ],
        ];

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson("/api/v1/crm/workflows/{$workflow->id}/simulate", [
                'definition' => $definition,
                'sample_payload' => [
                    'lead' => ['name' => 'Sample Mock Lead', 'score' => 90],
                ],
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.steps_executed', 3)
            ->assertJsonPath('data.trace.1.branch_taken', 'true')
            ->assertJsonPath('data.trace.2.output.status', 'simulated');

        // Confirm no persistent runs or task records created during simulation
        $this->assertEquals(0, WorkflowRun::count());
    }

    /** @test */
    public function it_strictly_enforces_cross_tenant_isolation_on_workflows()
    {
        $wfB = Workflow::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Secret Tenant B Workflow',
            'category' => 'sales',
            'trigger_type' => 'deal.won',
            'status' => 'published',
            'current_version' => 1,
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->getJson("/api/v1/crm/workflows/{$wfB->id}");

        $res->assertNotFound();
    }
}
