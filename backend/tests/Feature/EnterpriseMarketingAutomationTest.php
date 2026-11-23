<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\CampaignApproval;
use App\Domains\Marketing\Models\CampaignRecipient;
use App\Domains\Marketing\Models\DripEnrollment;
use App\Domains\Marketing\Models\DripSequence;
use App\Domains\Marketing\Models\DripStep;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Marketing\Models\MarketingConsent;
use App\Domains\Marketing\Models\MarketingSegment;
use App\Domains\Marketing\Models\MarketingTemplate;
use App\Domains\Marketing\Services\CampaignApprovalService;
use App\Domains\Marketing\Services\CampaignDispatchService;
use App\Domains\Marketing\Services\DripSequenceEngine;
use App\Domains\Marketing\Services\MarketingAnalyticsService;
use App\Domains\Marketing\Services\MarketingConsentManager;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseMarketingAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $managerUser;
    protected User $executiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Apex Marketing Corp',
            'domain' => 'apex' . uniqid(),
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
            'role' => 'Marketing Manager',
        ]);
        $this->managerUser->workspaces()->attach($this->tenant->id, ['role' => 'Marketing Manager', 'status' => 'active']);

        $this->executiveUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Marketing Executive',
        ]);
        $this->executiveUser->workspaces()->attach($this->tenant->id, ['role' => 'Marketing Executive', 'status' => 'active']);
    }

    public function test_multi_tier_campaign_approval_workflow()
    {
        // 1. Executive creates a campaign draft
        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Diwali Festive Mega Launch',
            'channel' => 'whatsapp',
            'type' => 'broadcast',
            'objective' => 'lead_generation',
            'status' => 'draft',
            'approval_status' => 'not_submitted',
            'budget' => 25000.00,
            'created_by_user_id' => $this->executiveUser->id,
        ]);

        // 2. Submit for approval via API
        $response = $this->actingAs($this->executiveUser)
            ->postJson("/api/crm/campaigns/{$campaign->id}/submit-approval", [
                'comments' => 'Please review audience and templates for Diwali launch.',
            ]);

        $response->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('pending_approval', $campaign->approval_status);
        $this->assertEquals('in_review', $campaign->status);

        $this->assertDatabaseHas('campaign_approvals', [
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'action' => 'requested',
        ]);

        // 3. Dispatching while pending approval must be rejected by service
        $dispatchService = app(CampaignDispatchService::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pending manager/admin approval');
        $dispatchService->dispatch($campaign, false);
    }

    public function test_manager_approves_campaign_enabling_dispatch()
    {
        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Q4 Retention Wave',
            'channel' => 'email',
            'type' => 'broadcast',
            'objective' => 'retention',
            'status' => 'in_review',
            'approval_status' => 'pending_approval',
            'budget' => 15000.00,
            'created_by_user_id' => $this->executiveUser->id,
        ]);

        $response = $this->actingAs($this->managerUser)
            ->postJson("/api/crm/campaigns/{$campaign->id}/approve", [
                'comments' => 'Budget and audience verified. Approved.',
            ]);

        $response->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('approved', $campaign->approval_status);
        $this->assertEquals($this->managerUser->id, $campaign->approved_by_user_id);
        $this->assertNotNull($campaign->approved_at);

        $this->assertDatabaseHas('campaign_approvals', [
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'action' => 'approved',
        ]);
    }

    public function test_rejection_workflow_records_reason_and_audit_history()
    {
        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Unapproved Flash Sale',
            'channel' => 'sms',
            'type' => 'broadcast',
            'status' => 'in_review',
            'approval_status' => 'pending_approval',
            'budget' => 10000.00,
            'created_by_user_id' => $this->executiveUser->id,
        ]);

        $response = $this->actingAs($this->managerUser)
            ->postJson("/api/crm/campaigns/{$campaign->id}/reject", [
                'reason' => 'Content violates TRAI promotional SMS format; missing DLT template ID.',
            ]);

        $response->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('rejected', $campaign->approval_status);
        $this->assertEquals('draft', $campaign->status);
        $this->assertStringContainsString('missing DLT template ID', $campaign->rejection_reason);

        $this->assertDatabaseHas('campaign_approvals', [
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'action' => 'rejected',
        ]);
    }

    public function test_campaign_duplication_and_archival()
    {
        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Blueprint Campaign',
            'channel' => 'whatsapp',
            'type' => 'broadcast',
            'budget' => 12000.00,
            'sent_count' => 500,
            'status' => 'completed',
            'approval_status' => 'approved',
            'created_by_user_id' => $this->adminUser->id,
        ]);

        // Duplicate
        $dupResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/crm/campaigns/{$campaign->id}/duplicate");

        $dupResponse->assertStatus(201);
        $clonedId = $dupResponse->json('data.id');

        $clone = MarketingCampaign::find($clonedId);
        $this->assertEquals('Original Blueprint Campaign (Copy)', $clone->name);
        $this->assertEquals('draft', $clone->status);
        $this->assertEquals('not_submitted', $clone->approval_status);
        $this->assertEquals(0, $clone->sent_count);

        // Archive original
        $archResponse = $this->actingAs($this->adminUser)
            ->postJson("/api/crm/campaigns/{$campaign->id}/archive");

        $archResponse->assertStatus(200);
        $campaign->refresh();
        $this->assertEquals('archived', $campaign->status);
    }

    public function test_frequency_capping_and_duplicate_delivery_prevention()
    {
        $lead1 = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Amit Patel',
            'first_name' => 'Amit',
            'last_name' => 'Patel',
            'email' => 'amit.patel@test.com',
            'phone' => '+919876500001',
            'status' => 'new',
        ]);

        $lead2 = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Suresh Kumar',
            'first_name' => 'Suresh',
            'last_name' => 'Kumar',
            'email' => 'suresh.kumar@test.com',
            'phone' => '+919876500002',
            'status' => 'new',
        ]);

        $segment = MarketingSegment::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'All New Leads',
            'target_entity' => 'lead',
            'rules' => ['conditions' => [['field' => 'status', 'operator' => '=', 'value' => 'new']]],
            'inclusion_ids' => [$lead1->id, $lead2->id],
            'cached_count' => 2,
        ]);

        // Prior campaign for foreign key validity
        $priorCampaign = MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Prior Wave',
            'channel' => 'whatsapp',
            'status' => 'completed',
        ]);

        // Simulate lead1 recently messaged 2 days ago
        CampaignRecipient::create([
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $priorCampaign->id,
            'entity_type' => 'lead',
            'entity_id' => $lead1->id,
            'recipient_address' => '+919876500001',
            'status' => 'sent',
            'sent_at' => now()->subDays(2),
        ]);

        // Campaign with frequency cap of 7 days
        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Weekly Promotional Pulse',
            'channel' => 'whatsapp',
            'type' => 'broadcast',
            'segment_id' => $segment->id,
            'status' => 'ready',
            'approval_status' => 'approved',
            'business_hours_only' => false,
            'frequency_cap_days' => 7,
            'content' => 'Hello {{first_name}}, check our latest updates!',
        ]);

        $dispatchService = app(CampaignDispatchService::class);
        $result = $dispatchService->dispatch($campaign, false);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['sent']); // lead2 sent
        $this->assertEquals(1, $result['skipped']); // lead1 skipped due to frequency cap

        $this->assertDatabaseHas('campaign_recipients', [
            'tenant_id' => $this->tenant->id,
            'campaign_id' => $campaign->id,
            'entity_id' => $lead1->id,
            'status' => 'skipped',
            'skip_reason' => 'frequency_capped',
        ]);
    }

    public function test_marketing_consent_registry_and_real_time_unsubscribe()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Priya Sharma',
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'email' => 'priya.sharma@example.in',
            'phone' => '+919876512345',
            'status' => 'new',
        ]);

        // Record opt-in
        $consentManager = app(MarketingConsentManager::class);
        $consent = $consentManager->recordConsent(
            $this->tenant->id,
            'lead',
            $lead->id,
            'whatsapp',
            'opted_in',
            'website_form'
        );
        $this->assertTrue($consentManager->hasConsent($this->tenant->id, 'lead', $lead->id, 'whatsapp'));

        // Handle customer replying "STOP"
        $consentManager->handleUnsubscribeKeyword(
            $this->tenant->id,
            'whatsapp',
            '+919876512345',
            $lead->id,
            'lead'
        );

        $this->assertFalse($consentManager->hasConsent($this->tenant->id, 'lead', $lead->id, 'whatsapp'));
        $this->assertDatabaseHas('communication_suppressions', [
            'tenant_id' => $this->tenant->id,
            'channel' => 'whatsapp',
            'identifier' => '919876512345',
        ]);
    }

    public function test_multi_channel_template_studio_and_ai_generation()
    {
        // 1. Create a template with DLT Template ID
        $templateResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/crm/marketing/templates', [
                'name' => 'Payment Reminder SMS Notice',
                'channel' => 'sms',
                'category' => 'transactional',
                'content' => 'Dear {{first_name}}, invoice for {{company}} is due. Pay online at https://crm.enterprises .',
                'dlt_template_id' => '1407161234567890123',
                'variables' => ['first_name', 'company'],
            ]);

        $templateResponse->assertStatus(201);
        $templateId = $templateResponse->json('data.id');

        // 2. Preview template
        $previewResponse = $this->actingAs($this->adminUser)
            ->getJson("/api/crm/marketing/templates/{$templateId}/preview");

        $previewResponse->assertStatus(200)
            ->assertJsonPath('data.dlt_template_id', '1407161234567890123');

        $this->assertStringContainsString('Rahul', $previewResponse->json('data.rendered_content'));

        // 3. AI Drafting
        $aiResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/crm/marketing/templates/ai-generate', [
                'channel' => 'whatsapp',
                'objective' => 'Demonstration invite for enterprise software',
                'product_name' => 'Apex Cloud CRM',
            ]);

        $aiResponse->assertStatus(200)
            ->assertJsonPath('data.ai_generated', true);
        $this->assertStringContainsString('Apex Cloud CRM', $aiResponse->json('data.content'));
    }

    public function test_customer_journey_conditional_branching_and_rep_assignment()
    {
        $salesRep = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Officer Vikram',
            'role' => 'Sales Representative',
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deepak Verma',
            'first_name' => 'Deepak',
            'last_name' => 'Verma',
            'email' => 'deepak.verma@testcorp.com',
            'phone' => '+919988776655',
            'status' => 'new',
            'assigned_user_id' => null,
        ]);

        $sequence = DripSequence::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Inbound Lead Nurturing',
            'journey_type' => 'lead_nurturing',
            'channel' => 'whatsapp',
            'status' => 'active',
            'trigger_event' => 'lead_created',
            'exit_on_reply' => false,
        ]);

        // Step 1: Send initial WhatsApp welcome
        $step1 = DripStep::create([
            'tenant_id' => $this->tenant->id,
            'drip_sequence_id' => $sequence->id,
            'step_order' => 1,
            'step_type' => 'send_message',
            'channel' => 'whatsapp',
            'content' => 'Hi {{first_name}}, welcome to our platform!',
            'delay_interval' => 0,
            'delay_unit' => 'minutes',
        ]);

        // Step 2: Conditional check (Did customer reply?)
        $step2 = DripStep::create([
            'tenant_id' => $this->tenant->id,
            'drip_sequence_id' => $sequence->id,
            'step_order' => 2,
            'step_type' => 'condition_check',
            'condition_rules' => [
                'check' => 'replied',
                'yes_step_order' => 3, // Step 3: Assign rep
                'no_step_order' => 4,  // Step 4: Email follow up
            ],
            'delay_interval' => 0,
            'delay_unit' => 'minutes',
        ]);

        // Step 3 (YES branch): Assign sales rep
        $step3 = DripStep::create([
            'tenant_id' => $this->tenant->id,
            'drip_sequence_id' => $sequence->id,
            'step_order' => 3,
            'step_type' => 'assign_rep',
            'delay_interval' => 0,
            'delay_unit' => 'minutes',
        ]);

        $engine = app(DripSequenceEngine::class);

        // Enroll with context simulated as having replied
        $enrollment = $engine->enroll($sequence, 'lead', $lead->id, [
            'customer_replied' => true,
        ]);

        $this->assertNotNull($enrollment);
        $this->assertEquals('active', $enrollment->status);

        // Fast forward execution time and process step 1
        $enrollment->update(['next_execution_at' => now()->subMinute()]);
        $engine->processDueEnrollments($this->tenant->id);

        $enrollment->refresh();
        $this->assertEquals($step2->id, $enrollment->current_step_id);

        // Process step 2 (condition check) -> should branch to step 3 (assign_rep)
        $enrollment->update(['next_execution_at' => now()->subMinute()]);
        $engine->processDueEnrollments($this->tenant->id);

        $enrollment->refresh();
        $this->assertEquals($step3->id, $enrollment->current_step_id);

        // Process step 3 (assign rep)
        $enrollment->update(['next_execution_at' => now()->subMinute()]);
        $engine->processDueEnrollments($this->tenant->id);

        $lead->refresh();
        $this->assertNotNull($lead->assigned_user_id);
    }

    public function test_marketing_analytics_roi_and_cpl_cpa_attribution()
    {
        $campaign = MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High-Conversion Summer Promotion',
            'channel' => 'whatsapp',
            'type' => 'broadcast',
            'objective' => 'lead_generation',
            'budget' => 20000.00,
            'spend_amount' => 5000.00,
            'total_recipients_count' => 1000,
            'sent_count' => 1000,
            'delivered_count' => 950,
            'read_count' => 800,
            'opens_count' => 800,
            'clicks_count' => 200,
            'replied_count' => 50,
            'conversions_count' => 10,
            'attributed_revenue' => 50000.00,
            'status' => 'completed',
            'approval_status' => 'approved',
        ]);

        // Create attributed deals
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deal from Summer Promo',
            'value' => 50000.00,
            'stage' => 'won',
            'utm_campaign' => $campaign->name,
            'created_by_user_id' => $this->adminUser->id,
        ]);

        $analyticsService = app(MarketingAnalyticsService::class);
        $analytics = $analyticsService->getCampaignAnalytics($campaign);

        $this->assertEquals(950, $analytics['provider_metrics']['delivered']);
        $this->assertEquals(95.0, $analytics['provider_metrics']['delivery_rate_percent']);
        $this->assertEquals(50000.0, $analytics['crm_attribution']['attributed_revenue']);
        $this->assertEquals(900.0, $analytics['crm_attribution']['roi_percent']); // ((50000 - 5000) / 5000) * 100 = 900%
        $this->assertEquals(1000.0, $analytics['crm_attribution']['roas_percent']); // (50000 / 5000) * 100 = 1000%
    }
}
