<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use App\Domains\CRM\Services\DealPipelineService;
use App\Domains\CRM\Services\SalesForecastingService;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Finance\Services\QuotationService;
use App\Domains\Projects\Models\Project;
use App\Domains\Sales\Models\CommissionPlan;
use App\Domains\Sales\Models\Contract;
use App\Domains\Sales\Models\SalesCommission;
use App\Domains\Sales\Services\ContractService;
use App\Domains\Sales\Services\SalesCommissionService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnterpriseSalesAndCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $managerUser;
    protected User $salesUser1;
    protected User $salesUser2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Apex Enterprise Systems',
            'domain' => 'apexsales' . uniqid(),
            'status' => 'active',
        ]);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Business Admin',
        ]);
        $this->adminUser->workspaces()->attach($this->tenant->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->managerUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Sales Manager',
        ]);
        $this->managerUser->workspaces()->attach($this->tenant->id, ['role' => 'Sales Manager', 'status' => 'active']);

        $this->salesUser1 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Vikram Seth',
            'role' => 'Sales Executive',
        ]);
        $this->salesUser1->workspaces()->attach($this->tenant->id, ['role' => 'Sales Executive', 'status' => 'active']);

        $this->salesUser2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Pooja Iyer',
            'role' => 'Sales Executive',
        ]);
        $this->salesUser2->workspaces()->attach($this->tenant->id, ['role' => 'Sales Executive', 'status' => 'active']);
    }

    public function test_multiple_pipelines_and_allowed_transition_enforcement()
    {
        // 1. Create a custom enterprise pipeline
        $pipeline = Pipeline::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Cloud Solutions Pipeline',
            'business_model' => 'b2b_enterprise',
            'is_default' => false,
            'is_active' => true,
        ]);

        $stage1 = PipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Initial Discovery',
            'code' => 'discovery',
            'probability' => 20,
            'order_column' => 1,
            'allowed_transitions' => ['demo', 'lost'], // Only demo or lost allowed from discovery!
        ]);

        $stage2 = PipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Technical Demo',
            'code' => 'demo',
            'probability' => 50,
            'order_column' => 2,
            'allowed_transitions' => ['negotiation', 'lost'],
        ]);

        $stage3 = PipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Commercial Negotiation',
            'code' => 'negotiation',
            'probability' => 80,
            'order_column' => 3,
            'required_fields' => ['value', 'close_date'],
        ]);

        $deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage1->id,
            'name' => 'Mega ERP License Deal',
            'value' => 500000.00,
            'stage' => 'discovery',
            'probability' => 20,
            'assigned_user_id' => $this->salesUser1->id,
        ]);

        $pipelineService = app(DealPipelineService::class);

        // 2. Attempt invalid transition skipping directly to negotiation
        try {
            $pipelineService->moveDealToStage($deal, $stage3->id, [], $this->salesUser1->id);
            $this->fail('Expected ValidationException for disallowed stage transition.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stage', $e->errors());
            $this->assertStringContainsString('not permitted', $e->errors()['stage'][0]);
        }

        // 3. Valid transition to Demo
        $updatedDeal = $pipelineService->moveDealToStage($deal, $stage2->id, [], $this->salesUser1->id);
        $this->assertEquals($stage2->id, $updatedDeal->pipeline_stage_id);
        $this->assertEquals(50, $updatedDeal->probability);
        $this->assertEquals(250000.00, (float) $updatedDeal->weighted_value);
    }

    public function test_deal_ownership_transfer_and_reopen_workflow()
    {
        $pipeline = app(DealPipelineService::class)->ensureDefaultPipeline($this->tenant->id);
        $openStage = $pipeline->stages()->where('is_won', false)->where('is_lost', false)->first();
        $lostStage = $pipeline->stages()->where('is_lost', true)->first();

        $deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $openStage->id,
            'name' => 'Fintech SaaS Agreement',
            'value' => 120000.00,
            'stage' => $openStage->code,
            'assigned_user_id' => $this->salesUser1->id,
        ]);

        $pipelineService = app(DealPipelineService::class);

        // Transfer ownership
        $transferred = $pipelineService->transferOwnership(
            $deal,
            $this->salesUser2->id,
            $this->managerUser->id,
            'Territory realignment'
        );
        $this->assertEquals($this->salesUser2->id, $transferred->assigned_user_id);

        // Move to Closed Lost
        $pipelineService->moveDealToStage($deal, $lostStage->id, [
            'lost_reason' => 'Competitor offered heavy discount',
        ], $this->salesUser2->id);

        $deal->refresh();
        $this->assertEquals('lost', $deal->stage);
        $this->assertNotNull($deal->lost_at);

        // Reopen Deal
        $reopened = $pipelineService->reopenDeal(
            $deal,
            $openStage->id,
            $this->managerUser->id,
            'Customer revisited decision after competitor downtime'
        );

        $this->assertNull($reopened->lost_at);
        $this->assertNotNull($reopened->reopened_at);
        $this->assertEquals($this->managerUser->id, $reopened->reopened_by_user_id);
        $this->assertEquals($openStage->id, $reopened->pipeline_stage_id);
    }

    public function test_deal_won_conversion_into_onboarding_project()
    {
        $pipeline = app(DealPipelineService::class)->ensureDefaultPipeline($this->tenant->id);
        $wonStage = $pipeline->stages()->where('is_won', true)->first();

        $deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $wonStage->id,
            'name' => 'Hospital Network CRM Rollout',
            'value' => 850000.00,
            'stage' => 'won',
            'assigned_user_id' => $this->salesUser1->id,
        ]);

        $pipelineService = app(DealPipelineService::class);
        $project = $pipelineService->convertToProject($deal, $this->managerUser->id, [
            'name' => 'Hospital Network Implementation Milestone 1',
        ]);

        $this->assertNotNull($project);
        $this->assertEquals('Hospital Network Implementation Milestone 1', $project->name);
        $this->assertEquals(850000.00, (float) $project->budget);

        $deal->refresh();
        $this->assertEquals($project->id, $deal->project_id);
    }

    public function test_quotation_approval_discount_restriction_and_digital_acceptance()
    {
        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'quote_number' => 'QUO-E2E-' . uniqid(),
            'subtotal' => 100000.00,
            'discount_rate' => 25.00, // 25% discount exceeds standard 10% threshold
            'discount_amount' => 25000.00,
            'tax_rate' => 18.00,
            'tax_amount' => 13500.00,
            'total_amount' => 88500.00,
            'status' => 'draft',
            'approval_status' => 'not_submitted',
        ]);

        $quotationService = app(QuotationService::class);

        // 1. Check discount approval policy
        $check = $quotationService->checkDiscountApproval($quotation, 25.00, $this->salesUser1);
        $this->assertTrue($check['requires_approval']);
        $this->assertNotNull($check['approval']);

        $quotation->refresh();
        $this->assertEquals('pending_approval', $quotation->approval_status);

        // 2. Manager approves quotation
        $quotationService->approve($quotation, $this->managerUser, 'Approved 25% enterprise strategic concession.');
        $quotation->refresh();
        $this->assertEquals('approved', $quotation->approval_status);
        $this->assertEquals($this->managerUser->id, $quotation->approved_by_user_id);

        // 3. Customer digital acceptance
        $quotationService->recordCustomerAction($quotation, 'accept', '203.0.113.195', 'Agreed to commercial terms.');
        $quotation->refresh();
        $this->assertEquals('accepted', $quotation->status);
        $this->assertEquals('203.0.113.195', $quotation->customer_action_ip);
    }

    public function test_quotation_to_invoice_conversion_with_duplicate_guard()
    {
        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'quote_number' => 'QUO-INV-' . uniqid(),
            'subtotal' => 50000.00,
            'tax_rate' => 18.00,
            'tax_amount' => 9000.00,
            'total_amount' => 59000.00,
            'status' => 'accepted',
            'approval_status' => 'approved',
        ]);

        $quotation->items()->create([
            'item_name' => 'Enterprise License & Implementation',
            'quantity' => 1,
            'unit_price' => 50000.00,
            'tax_rate' => 18.00,
            'tax_amount' => 9000.00,
            'total_price' => 59000.00,
        ]);

        $quotationService = app(QuotationService::class);

        // First conversion
        $invoice1 = $quotationService->convertToInvoice($this->tenant->id, $quotation->id, $this->adminUser);
        $this->assertNotNull($invoice1);
        $quotation->refresh();
        $this->assertEquals($invoice1->id, $quotation->converted_to_invoice_id);

        // Attempt second conversion -> returns same invoice, prevents duplicate!
        $invoice2 = $quotationService->convertToInvoice($this->tenant->id, $quotation->id, $this->adminUser);
        $this->assertEquals($invoice1->id, $invoice2->id);

        $this->assertEquals(1, Invoice::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_contract_proposal_and_digital_signature_audit_hash()
    {
        $contractService = app(ContractService::class);

        $contract = $contractService->createContract($this->tenant->id, [
            'title' => 'Master Services Enterprise Agreement',
            'contract_type' => 'contract',
            'contract_value' => 300000.00,
            'e_signature_provider' => 'aadhaar_esign',
        ], $this->managerUser);

        $this->assertEquals('unsigned', $contract->signature_status);

        // Send for signature
        $contractService->sendForSignature($contract, 'Sunil Mittal', 'sunil.mittal@clientcorp.in', 'aadhaar_esign');
        $contract->refresh();
        $this->assertEquals('sent_for_signature', $contract->signature_status);

        // Record customer signature
        $signed = $contractService->recordSignature(
            $contract,
            'Sunil Mittal',
            'sunil.mittal@clientcorp.in',
            '49.206.12.88'
        );

        $this->assertEquals('signed', $signed->signature_status);
        $this->assertNotNull($signed->signed_at);
        $this->assertNotEmpty($signed->audit_trail_hash);
        $this->assertEquals(64, strlen($signed->audit_trail_hash)); // SHA-256 hash length
    }

    public function test_sales_commission_calculation_and_clawback_reversal()
    {
        // 1. Create Tiered Commission Plan
        $plan = CommissionPlan::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Accelerated Tier Plan',
            'plan_type' => 'tiered',
            'calculation_basis' => 'deal_won',
            'rate_percentage' => 5.00, // base 5%
            'tier_rules' => [
                ['min' => 0, 'max' => 100000, 'rate' => 5.00],
                ['min' => 100000, 'max' => 500000, 'rate' => 8.00], // 8% for 100k-500k
                ['min' => 500000, 'rate' => 10.00],
            ],
            'is_active' => true,
        ]);

        $deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gov Tender Cloud Contract',
            'value' => 200000.00, // falls into 8% bracket -> $16,000 commission
            'stage' => 'won',
            'assigned_user_id' => $this->salesUser1->id,
        ]);

        $commissionService = app(SalesCommissionService::class);

        // 2. Calculate Deal Commission
        $commission = $commissionService->calculateDealWonCommission($deal);
        $this->assertNotNull($commission);
        $this->assertEquals(200000.00, (float) $commission->base_amount);
        $this->assertEquals(8.00, (float) $commission->commission_rate);
        $this->assertEquals(16000.00, (float) $commission->commission_amount);
        $this->assertEquals('pending_approval', $commission->status);

        // 3. Idempotency test: Re-calculating must NOT create duplicate
        $dup = $commissionService->calculateDealWonCommission($deal);
        $this->assertEquals($commission->id, $dup->id);
        $this->assertEquals(1, SalesCommission::where('tenant_id', $this->tenant->id)->count());

        // 4. Manager approves commission
        $commissionService->approveCommission($commission, $this->managerUser);
        $commission->refresh();
        $this->assertEquals('approved', $commission->status);

        // 5. Clawback / Reversal on deal cancellation
        $commissionService->reverseCommission($commission, 'Deal cancelled post-audit due to client RFP withdrawal.');
        $commission->refresh();
        $this->assertEquals('reversed', $commission->status);
        $this->assertNotNull($commission->reversed_at);
        $this->assertStringContainsString('cancelled', $commission->reversal_reason);
    }

    public function test_sales_forecasting_weighted_pipeline_analytics()
    {
        $pipeline = app(DealPipelineService::class)->ensureDefaultPipeline($this->tenant->id);
        $stages = $pipeline->stages;
        $stage20 = $stages->where('probability', 20)->first();
        $stage80 = $stages->where('probability', 80)->first();
        $wonStage = $stages->where('is_won', true)->first();

        // Deal 1: $100,000 @ 20% -> Weighted: $20,000
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage20->id,
            'name' => 'Opportunity Alpha',
            'value' => 100000.00,
            'stage' => $stage20->code,
            'probability' => 20,
            'weighted_value' => 20000.00,
            'assigned_user_id' => $this->salesUser1->id,
            'close_date' => now()->addDays(15)->toDateString(),
        ]);

        // Deal 2: $200,000 @ 80% -> Weighted: $160,000 (Committed)
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage80->id,
            'name' => 'Opportunity Beta',
            'value' => 200000.00,
            'stage' => $stage80->code,
            'probability' => 80,
            'weighted_value' => 160000.00,
            'assigned_user_id' => $this->salesUser2->id,
            'close_date' => now()->addDays(30)->toDateString(),
        ]);

        // Deal 3: $50,000 Won
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $wonStage->id,
            'name' => 'Opportunity Gamma',
            'value' => 50000.00,
            'stage' => 'won',
            'probability' => 100,
            'weighted_value' => 50000.00,
            'assigned_user_id' => $this->salesUser1->id,
            'won_at' => now(),
        ]);

        $forecastingService = app(SalesForecastingService::class);
        $forecast = $forecastingService->getForecastReport($this->tenant->id, $pipeline->id);

        $summary = $forecast['summary'];
        $this->assertEquals(2, $summary['open_deals_count']);
        $this->assertEquals(300000.00, (float) $summary['unweighted_pipeline_value']); // 100k + 200k
        $this->assertEquals(180000.00, (float) $summary['weighted_forecast_value']);   // 20k + 160k
        $this->assertEquals(200000.00, (float) $summary['committed_value']);           // Deal 2 (prob >= 80%)
        $this->assertEquals(50000.00, (float) $summary['closed_won_value']);

        // Check assumptions notice
        $this->assertStringContainsString('statistical projections', $forecast['assumptions']['notice']);
    }
}
