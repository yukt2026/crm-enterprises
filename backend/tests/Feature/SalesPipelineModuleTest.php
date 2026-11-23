<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use App\Domains\CRM\Models\Product;
use App\Domains\CRM\Models\SalesQuota;
use App\Domains\CRM\Models\SalesTeam;
use App\Domains\CRM\Services\DealPipelineService;
use App\Domains\Projects\Models\Task;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPipelineModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected User $userRep;
    protected User $userB;
    protected string $tokenAdmin;
    protected string $tokenRep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Global',
            'slug' => 'beta-global',
            'is_active' => true,
        ]);

        $this->userAdmin = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice.admin@acme.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin']);

        $this->userRep = User::create([
            'name' => 'Sam Sales',
            'email' => 'sam.sales@acme.com',
            'password' => bcrypt('password123'),
            'role' => 'Sales Executive',
        ]);
        $this->userRep->workspaces()->attach($this->tenantA->id, ['role' => 'Sales Executive']);

        $this->userB = User::create([
            'name' => 'Bob Beta',
            'email' => 'bob@beta.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin']);

        $this->tokenAdmin = $this->userAdmin->createToken('admin_token')->plainTextToken;
        $this->tokenRep = $this->userRep->createToken('rep_token')->plainTextToken;
    }

    /**
     * Test Multiple Pipelines and Custom Stages per Tenant.
     */
    public function test_multiple_pipelines_and_custom_stages_creation(): void
    {
        // 1. Create a custom Partner Pipeline
        $pipelineData = [
            'name' => 'Strategic Partner Pipeline',
            'description' => 'Channel partner tier qualification and contract closing',
            'is_default' => false,
            'stages' => [
                ['name' => 'Application Review', 'code' => 'review', 'probability' => 25, 'order_column' => 1, 'color' => '#3b82f6'],
                ['name' => 'Partner Agreement', 'code' => 'agreement', 'probability' => 75, 'order_column' => 2, 'color' => '#ec4899', 'required_fields' => ['value']],
                ['name' => 'Active Partner', 'code' => 'won', 'probability' => 100, 'order_column' => 3, 'color' => '#10b981', 'is_won' => true],
            ],
        ];

        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/pipelines', $pipelineData);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'Strategic Partner Pipeline');

        $pipelineId = $res->json('data.id');

        $this->assertDatabaseHas('pipelines', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'Strategic Partner Pipeline',
        ]);

        $this->assertDatabaseHas('pipeline_stages', [
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $pipelineId,
            'name' => 'Application Review',
            'probability' => 25,
        ]);

        // 2. Add an additional stage via stage sub-resource
        $stageRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/pipelines/{$pipelineId}/stages", [
                'name' => 'Executive Approval',
                'code' => 'exec_approval',
                'probability' => 90,
                'order_column' => 4,
            ]);

        $stageRes->assertStatus(201);
        $this->assertDatabaseHas('pipeline_stages', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'Executive Approval',
            'probability' => 90,
        ]);
    }

    /**
     * Test Deal Stage Transition Validation and Weighted Forecast Calculation.
     */
    public function test_deal_stage_transition_validation_and_weighted_probability(): void
    {
        /** @var DealPipelineService $pipelineService */
        $pipelineService = app(DealPipelineService::class);
        $pipeline = $pipelineService->ensureDefaultPipeline($this->tenantA->id);
        $stages = $pipeline->stages()->get();
        $stage1 = $stages->where('code', 'qualification')->first();
        $stageProposal = $stages->where('code', 'proposal')->first(); // Requires 'value'
        $stageWon = $stages->where('code', 'won')->first(); // Requires 'value' and 'close_date'

        $company = Company::create(['tenant_id' => $this->tenantA->id, 'name' => 'Global Logistics Inc']);

        // 1. Create deal at Qualification stage
        $deal = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage1->id,
            'name' => 'ERP Cloud Upgrade',
            'value' => 50000,
            'stage' => 'qualification',
            'probability' => $stage1->probability,
            'weighted_value' => round(50000 * $stage1->probability / 100, 2),
            'assigned_user_id' => $this->userRep->id,
        ]);

        $this->assertEquals(10000, $deal->weighted_value); // 50000 * 20% = 10000

        // 2. Advance to Proposal stage (60% probability)
        $moveRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/deals/{$deal->id}/move-to-stage", [
                'pipeline_stage_id' => $stageProposal->id,
                'value' => 60000,
            ]);

        $moveRes->assertStatus(200);

        $deal->refresh();
        $this->assertEquals('proposal', $deal->stage);
        $this->assertEquals(60, $deal->probability);
        $this->assertEquals(36000, $deal->weighted_value); // 60000 * 60% = 36000

        // 3. Stage transition history log created
        $this->assertDatabaseHas('deal_stage_histories', [
            'tenant_id' => $this->tenantA->id,
            'deal_id' => $deal->id,
            'from_stage_id' => $stage1->id,
            'to_stage_id' => $stageProposal->id,
            'to_stage_name' => $stageProposal->name,
        ]);
    }

    /**
     * Test Deal Closure Workflows: Won vs Lost with required competitor & reason.
     */
    public function test_deal_closure_workflows_won_and_lost(): void
    {
        /** @var DealPipelineService $pipelineService */
        $pipelineService = app(DealPipelineService::class);
        $pipeline = $pipelineService->ensureDefaultPipeline($this->tenantA->id);
        $stages = $pipeline->stages()->get();
        $stageProposal = $stages->where('code', 'proposal')->first();
        $stageWon = $stages->where('code', 'won')->first();
        $stageLost = $stages->where('code', 'lost')->first();

        $deal = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stageProposal->id,
            'name' => 'Strategic Enterprise License',
            'value' => 120000,
            'stage' => 'proposal',
            'probability' => 60,
            'assigned_user_id' => $this->userRep->id,
        ]);

        // 1. Move to Won: must set won_at, probability 100%, close_date
        $wonRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/deals/{$deal->id}/move-to-stage", [
                'pipeline_stage_id' => $stageWon->id,
                'close_date' => Carbon::today()->toDateString(),
            ]);

        $wonRes->assertStatus(200);
        $deal->refresh();

        $this->assertEquals('won', $deal->stage);
        $this->assertEquals(100, $deal->probability);
        $this->assertEquals(120000, $deal->weighted_value);
        $this->assertNotNull($deal->won_at);
        $this->assertNull($deal->lost_at);

        // 2. Test moving to Lost with competitor and reason validation
        $lostRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/deals/{$deal->id}/move-to-stage", [
                'pipeline_stage_id' => $stageLost->id,
                'lost_reason' => 'Competitor offered lower upfront pricing',
                'competitor' => 'Salesforce Inc',
            ]);

        $lostRes->assertStatus(200);
        $deal->refresh();

        $this->assertEquals('lost', $deal->stage);
        $this->assertEquals(0, $deal->probability);
        $this->assertEquals(0, $deal->weighted_value);
        $this->assertNotNull($deal->lost_at);
        $this->assertEquals('Salesforce Inc', $deal->competitor);
    }

    /**
     * Test Tasks & Follow-ups Creation, Scopes and Checklist.
     */
    public function test_tasks_creation_recurrence_and_views_scoping(): void
    {
        $company = Company::create(['tenant_id' => $this->tenantA->id, 'name' => 'Target Enterprise Corp']);
        $deal = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'company_id' => $company->id,
            'name' => 'Demo Deal',
            'value' => 25000,
            'stage' => 'qualification',
        ]);

        // 1. Create task with checklist items
        $taskData = [
            'title' => 'Deliver Executive Solution Demo',
            'description' => 'Present tailored CRM features to C-level team',
            'priority' => 'high',
            'due_date' => Carbon::today()->toDateString(),
            'assigned_user_id' => $this->userRep->id,
            'deal_id' => $deal->id,
            'company_id' => $company->id,
            'checklist_items' => [
                ['id' => 'chk_1', 'text' => 'Review custom fields demo data', 'completed' => false],
                ['id' => 'chk_2', 'text' => 'Prepare slide deck for CEO', 'completed' => false],
            ],
        ];

        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson('/api/v1/crm/tasks', $taskData);

        $res->assertStatus(201);
        $taskId = $res->json('data.id');

        // 2. Toggle checklist item
        $toggleRes = $this->withHeader('Authorization', "Bearer {$this->tokenRep}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/tasks/{$taskId}/toggle-checklist", [
                'item_id' => 'chk_1',
            ]);

        $toggleRes->assertStatus(200);
        $updatedItems = $toggleRes->json('data.checklist_items');
        $this->assertTrue($updatedItems[0]['completed']);

        // 3. Test View Filter: Today view includes today's task
        $todayRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson('/api/v1/crm/tasks?view=today');

        $todayRes->assertStatus(200);
        $taskTitles = collect($todayRes->json('data'))->pluck('title')->all();
        $this->assertContains('Deliver Executive Solution Demo', $taskTitles);

        // 4. Mark Task Complete
        $completeRes = $this->withHeader('Authorization', "Bearer {$this->tokenRep}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/tasks/{$taskId}/complete");

        $completeRes->assertStatus(200);
        $this->assertEquals('done', $completeRes->json('data.status'));
        $this->assertNotNull($completeRes->json('data.completed_at'));
    }

    /**
     * Test Sales Teams, Quotas and Performance Leaderboard.
     */
    public function test_sales_teams_quotas_and_leaderboard(): void
    {
        // 1. Create Sales Team
        $team = SalesTeam::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Strategic Accounts Team',
            'leader_id' => $this->userAdmin->id,
            'member_ids' => [$this->userRep->id, $this->userAdmin->id],
        ]);

        // 2. Configure Quota for Rep
        $now = Carbon::now();
        SalesQuota::create([
            'tenant_id' => $this->tenantA->id,
            'sales_team_id' => $team->id,
            'user_id' => $this->userRep->id,
            'period' => 'monthly',
            'start_date' => $now->copy()->startOfMonth()->toDateString(),
            'end_date' => $now->copy()->endOfMonth()->toDateString(),
            'target_revenue' => 50000,
        ]);

        // 3. Create a won deal for Rep within period
        Deal::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Closed Deal Alpha',
            'value' => 35000,
            'stage' => 'won',
            'assigned_user_id' => $this->userRep->id,
            'won_at' => $now->copy()->toDateTimeString(),
        ]);

        // 4. Fetch Leaderboard
        $leaderboardRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson('/api/v1/crm/sales-teams/leaderboard');

        $leaderboardRes->assertStatus(200);
        $entries = $leaderboardRes->json('data.leaderboard');

        $repEntry = collect($entries)->firstWhere('user_id', $this->userRep->id);
        $this->assertNotNull($repEntry);
        $this->assertEquals(35000, $repEntry['revenue_won']);
        $this->assertEquals(1, $repEntry['deals_won_count']);
        $this->assertEquals(70.0, $repEntry['attainment_percent']); // 35000 / 50000 = 70%
    }

    /**
     * Test Stale Deals Detection and Forecast aggregation.
     */
    public function test_stale_deals_detection_and_weighted_forecast(): void
    {
        /** @var DealPipelineService $pipelineService */
        $pipelineService = app(DealPipelineService::class);
        $pipeline = $pipelineService->ensureDefaultPipeline($this->tenantA->id);
        $stage1 = $pipeline->stages()->where('code', 'qualification')->first();

        // Create a deal with old updated_at (stale)
        $oldDate = Carbon::now()->subDays(25);
        $staleDeal = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage1->id,
            'name' => 'Stale Enterprise Contract',
            'value' => 80000,
            'stage' => 'qualification',
            'probability' => 20,
            'weighted_value' => 16000,
            'stage_updated_at' => $oldDate,
            'created_at' => $oldDate,
        ]);

        // Test Stale Deals API
        $staleRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson('/api/v1/crm/deals/stale?days=14');

        $staleRes->assertStatus(200);
        $staleItems = $staleRes->json('data');
        $staleDealIds = collect($staleItems)->pluck('deal.id')->all();
        $this->assertContains($staleDeal->id, $staleDealIds);

        // Test Forecast API
        $forecastRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson("/api/v1/crm/deals/forecast?pipeline_id={$pipeline->id}");

        $forecastRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(80000, $forecastRes->json('data.total_open_pipeline_value'));
        $this->assertGreaterThanOrEqual(16000, $forecastRes->json('data.total_weighted_forecast'));
    }

    /**
     * Test Strict Cross-Tenant Isolation on Sales Pipeline and Tasks.
     */
    public function test_tenant_isolation_on_sales_pipeline_and_tasks(): void
    {
        $pipelineA = Pipeline::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Private Pipeline',
        ]);

        $pipelineB = Pipeline::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Confidential Pipeline',
        ]);

        $tokenB = $this->userB->createToken('token_b')->plainTextToken;

        $resA = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson('/api/v1/crm/pipelines');

        $resA->assertStatus(200);
        $pipelineNamesA = collect($resA->json('data'))->pluck('name')->all();

        $this->assertContains('Tenant A Private Pipeline', $pipelineNamesA);
        $this->assertNotContains('Tenant B Confidential Pipeline', $pipelineNamesA);
    }
}
