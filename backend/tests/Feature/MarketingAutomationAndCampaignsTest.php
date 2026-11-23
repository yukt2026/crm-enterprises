<?php

namespace Tests\Feature;

use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\CampaignRecipient;
use App\Domains\Marketing\Models\DripEnrollment;
use App\Domains\Marketing\Models\DripSequence;
use App\Domains\Marketing\Models\DripStep;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingSegment;
use App\Domains\Marketing\Services\CampaignDispatchService;
use App\Domains\Marketing\Services\DripSequenceEngine;
use App\Domains\Marketing\Services\SegmentQueryService;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingAutomationAndCampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected string $tokenAdmin;
    protected SegmentQueryService $segmentQueryService;
    protected CampaignDispatchService $dispatchService;
    protected DripSequenceEngine $dripEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Growth Pulse Marketing',
            'slug' => 'growth-pulse',
            'subscription_tier' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Nova Horizon Tech',
            'slug' => 'nova-horizon',
            'subscription_tier' => 'professional',
        ]);

        $this->userAdmin = User::create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Rohan Varma',
            'email' => 'rohan@growthpulse.in',
            'password' => bcrypt('SecurePass2026!'),
            'role' => 'Business Admin',
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);
        $this->tokenAdmin = $this->userAdmin->createToken('test_token')->plainTextToken;

        $this->segmentQueryService = app(SegmentQueryService::class);
        $this->dispatchService = app(CampaignDispatchService::class);
        $this->dripEngine = app(DripSequenceEngine::class);
    }

    /** @test */
    public function it_creates_dynamic_segments_and_resolves_audience_members()
    {
        // Create sample leads
        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Anil Kapoor',
            'email' => 'anil@enterprise.com',
            'phone' => '+919876500001',
            'status' => 'qualified',
            'score' => 85,
            'city' => 'Bengaluru',
            'tags' => ['vip', 'cloud'],
        ]);

        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Kavita Roy',
            'email' => 'kavita@startup.io',
            'phone' => '+919876500002',
            'status' => 'new',
            'score' => 30,
            'city' => 'Delhi',
            'tags' => ['trial'],
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson('/api/v1/crm/segments', [
                'name' => 'High Value Bengaluru Qualified Leads',
                'description' => 'Targeted segment for enterprise cloud summit.',
                'type' => 'dynamic',
                'target_entity' => 'lead',
                'rules' => [
                    'logical_operator' => 'and',
                    'rules' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'qualified'],
                        ['field' => 'min_score', 'operator' => 'greater_than', 'value' => 80],
                    ],
                ],
            ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.name', 'High Value Bengaluru Qualified Leads');
        $res->assertJsonPath('data.cached_count', 1);

        $segmentId = $res->json('data.id');

        // Resolve segment members
        $membersRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->getJson("/api/v1/crm/segments/{$segmentId}/members");

        $membersRes->assertStatus(200);
        $membersRes->assertJsonPath('data.total_members', 1);
        $this->assertEquals('Anil Kapoor', $membersRes->json('data.sample_members.0.name'));
    }

    /** @test */
    public function it_enforces_manual_inclusion_and_exclusion_lists_on_segments()
    {
        $lead1 = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Lead One',
            'email' => 'one@test.com',
            'phone' => '+919999000001',
            'status' => 'new',
            'score' => 10,
        ]);

        $lead2 = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Lead Two',
            'email' => 'two@test.com',
            'phone' => '+919999000002',
            'status' => 'qualified',
            'score' => 90,
        ]);

        // Segment that qualifies lead2 by rule, but excludes lead2 manually and includes lead1 manually
        $segment = MarketingSegment::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Custom Inclusion Segment',
            'type' => 'dynamic',
            'target_entity' => 'lead',
            'rules' => [
                'rules' => [['field' => 'status', 'value' => 'qualified']],
            ],
            'inclusion_ids' => [$lead1->id],
            'exclusion_ids' => [$lead2->id],
        ]);

        $recipients = $this->segmentQueryService->resolveRecipients($segment);

        $this->assertCount(1, $recipients);
        $this->assertEquals($lead1->id, $recipients->first()['entity_id']);
    }

    /** @test */
    public function it_dispatches_broadcast_campaign_and_records_recipients()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Deepak Chopra',
            'email' => 'deepak@wellness.com',
            'phone' => '+919811122233',
            'company_name' => 'Wellness Global',
            'status' => 'qualified',
        ]);

        $segment = MarketingSegment::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Broadcast Audience',
            'type' => 'dynamic',
            'rules' => [
                'rules' => [['field' => 'status', 'value' => 'qualified']],
            ],
        ]);

        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Q4 Enterprise Product Launch',
            'channel' => 'whatsapp',
            'type' => 'broadcast',
            'status' => 'draft',
            'segment_id' => $segment->id,
            'content' => 'Hello {{first_name}}, exclusive offer for {{company}}!',
            'created_by_user_id' => $this->userAdmin->id,
        ]);

        // Dispatch broadcast
        $dispatchRes = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/crm/campaigns/{$campaign->id}/dispatch");

        $dispatchRes->assertStatus(200);
        $dispatchRes->assertJsonPath('data.dispatch_result.sent', 1);

        // Verify recipient log created
        $this->assertDatabaseHas('campaign_recipients', [
            'tenant_id' => $this->tenantA->id,
            'campaign_id' => $campaign->id,
            'entity_id' => $lead->id,
            'status' => 'sent',
        ]);

        $this->assertEquals('completed', $campaign->fresh()->status);
        $this->assertEquals(1, $campaign->fresh()->sent_count);
    }

    /** @test */
    public function it_strictly_enforces_communication_suppression_and_skips_opted_out_recipients()
    {
        $leadActive = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pooja Active',
            'email' => 'pooja@active.com',
            'phone' => '+919870001111',
            'status' => 'qualified',
        ]);

        $leadSuppressed = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Manish Suppressed',
            'email' => 'manish@optout.com',
            'phone' => '+919870002222',
            'status' => 'qualified',
        ]);

        // Add Manish to communication suppression list
        CommunicationSuppression::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'identifier' => '+919870002222',
            'reason' => 'unsubscribe',
            'suppressed_at' => now(),
        ]);

        $segment = MarketingSegment::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Consent Test Segment',
            'rules' => ['rules' => [['field' => 'status', 'value' => 'qualified']]],
        ]);

        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Suppression Check Campaign',
            'channel' => 'whatsapp',
            'status' => 'draft',
            'segment_id' => $segment->id,
            'content' => 'Promotional Alert',
        ]);

        $result = $this->dispatchService->dispatch($campaign, false);

        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(1, $result['skipped']);

        // Verify suppressed recipient is logged as skipped
        $this->assertDatabaseHas('campaign_recipients', [
            'tenant_id' => $this->tenantA->id,
            'campaign_id' => $campaign->id,
            'entity_id' => $leadSuppressed->id,
            'status' => 'skipped',
            'skip_reason' => 'suppressed_opt_out',
        ]);

        // Verify active recipient is logged as sent
        $this->assertDatabaseHas('campaign_recipients', [
            'tenant_id' => $this->tenantA->id,
            'campaign_id' => $campaign->id,
            'entity_id' => $leadActive->id,
            'status' => 'sent',
        ]);
    }

    /** @test */
    public function it_supports_personalized_test_send_without_polluting_campaign_metrics()
    {
        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Email Newsletter Preview',
            'channel' => 'email',
            'status' => 'draft',
            'subject' => 'Hi {{name}}, your enterprise briefing',
            'content' => 'Dear {{first_name}}, welcome to {{company}}.',
        ]);

        $res = $this->withHeader('Authorization', "Bearer {$this->tokenAdmin}")
            ->postJson("/api/v1/crm/campaigns/{$campaign->id}/test-send", [
                'recipient' => 'test.internal@growthpulse.in',
            ]);

        $res->assertStatus(200);
        $res->assertJsonPath('data.sent', 1);

        // Test send must NOT increment campaign live sent_count
        $this->assertEquals(0, $campaign->fresh()->sent_count);
        $this->assertEquals('draft', $campaign->fresh()->status);
    }

    /** @test */
    public function it_enrolls_leads_in_drip_sequences_and_advances_steps()
    {
        $sequence = DripSequence::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'New Lead Onboarding Journey',
            'status' => 'active',
            'trigger_event' => 'lead.created',
            'exit_on_conversion' => true,
            'respect_quiet_hours' => false,
        ]);

        $step1 = DripStep::create([
            'tenant_id' => $this->tenantA->id,
            'drip_sequence_id' => $sequence->id,
            'step_order' => 1,
            'channel' => 'whatsapp',
            'delay_interval' => 0,
            'delay_unit' => 'hours',
            'content' => 'Welcome message',
        ]);

        $step2 = DripStep::create([
            'tenant_id' => $this->tenantA->id,
            'drip_sequence_id' => $sequence->id,
            'step_order' => 2,
            'channel' => 'email',
            'delay_interval' => 24,
            'delay_unit' => 'hours',
            'content' => 'Follow up case study',
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sanjay Dutt',
            'email' => 'sanjay@films.com',
            'phone' => '+919820098200',
            'status' => 'new',
        ]);

        // 1. Enroll lead
        $enrollment = $this->dripEngine->enroll($sequence, 'lead', $lead->id);
        $this->assertNotNull($enrollment);
        $this->assertEquals('active', $enrollment->status);
        $this->assertEquals($step1->id, $enrollment->current_step_id);

        // 2. Process due enrollment (Step 1 has 0 delay)
        $processResult = $this->dripEngine->processDueEnrollments($this->tenantA->id);
        $this->assertEquals(1, $processResult['processed']);

        // Verify advanced to Step 2
        $enrollment->refresh();
        $this->assertEquals($step2->id, $enrollment->current_step_id);
        $this->assertEquals('active', $enrollment->status);
        $this->assertNotNull($enrollment->next_execution_at);
    }

    /** @test */
    public function it_exits_drip_sequence_on_conversion_or_opt_out()
    {
        $sequence = DripSequence::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Exit On Conversion Sequence',
            'status' => 'active',
            'exit_on_conversion' => true,
        ]);

        $step1 = DripStep::create([
            'tenant_id' => $this->tenantA->id,
            'drip_sequence_id' => $sequence->id,
            'step_order' => 1,
            'channel' => 'whatsapp',
            'delay_interval' => 0,
            'delay_unit' => 'hours',
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Vikram Converted',
            'email' => 'vikram@converted.com',
            'phone' => '+919988776655',
            'status' => 'qualified', // Already qualified/converted!
        ]);

        $enrollment = $this->dripEngine->enroll($sequence, 'lead', $lead->id);

        // When processed, it must exit immediately due to conversion
        $result = $this->dripEngine->processDueEnrollments($this->tenantA->id);

        $this->assertEquals(1, $result['exited']);
        $this->assertEquals('exited_conversion', $enrollment->fresh()->status);
    }

    /** @test */
    public function it_strictly_enforces_cross_tenant_isolation_on_campaigns_and_segments()
    {
        $campaignA = MarketingCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Secret Campaign',
            'channel' => 'email',
            'status' => 'draft',
        ]);

        $userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'name' => 'Nova User',
            'email' => 'user@nova.com',
            'password' => bcrypt('Pass123!'),
            'role' => 'Business Admin',
        ]);
        $tokenB = $userB->createToken('token_b')->plainTextToken;

        // User B cannot access Tenant A's campaign
        $res = $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->getJson("/api/v1/crm/campaigns/{$campaignA->id}");

        $this->assertContains($res->status(), [403, 404]);

        // User B cannot dispatch Tenant A's campaign
        $dispatchRes = $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->postJson("/api/v1/crm/campaigns/{$campaignA->id}/dispatch");

        $this->assertContains($dispatchRes->status(), [403, 404]);
    }
}
