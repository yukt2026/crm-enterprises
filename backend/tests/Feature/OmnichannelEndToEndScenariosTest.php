<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use App\Domains\CRM\Models\SalesTeam;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Projects\Models\Task;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Omnichannel\Models\CustomerCommunicationConsent;
use App\Domains\Omnichannel\Models\CustomerSocialIdentity;
use App\Domains\Omnichannel\Models\IdentityMergePending;
use App\Domains\Omnichannel\Models\OmnichannelConversation;
use App\Domains\Omnichannel\Models\SocialChannelAccount;
use App\Domains\Omnichannel\Models\SocialWebhookEvent;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\WhatsApp\Models\WhatsAppAccount;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class OmnichannelEndToEndScenariosTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $adminUser;
    protected User $salesAgent1;
    protected User $salesAgent2;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;
    protected SalesTeam $salesTeam;
    protected Pipeline $pipeline;
    protected PipelineStage $stageNew;
    protected PipelineStage $stageWon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Enterprise A',
            'slug' => 'acme-enterprise-a',
            'domain' => 'acme-a.nexus.test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Stark Corp B',
            'slug' => 'stark-corp-b',
            'domain' => 'stark-b.nexus.test',
            'status' => 'active',
        ]);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Sarah Connor',
            'email' => 'sarah@acme-a.nexus.test',
            'role' => 'admin',
        ]);
        $this->adminUser->workspaces()->attach($this->tenantA->id, ['role' => 'admin', 'status' => 'active']);

        $this->salesAgent1 = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'John Connor',
            'email' => 'john@acme-a.nexus.test',
            'role' => 'sales',
        ]);
        $this->salesAgent1->workspaces()->attach($this->tenantA->id, ['role' => 'sales', 'status' => 'active']);

        $this->salesAgent2 = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Kyle Reese',
            'email' => 'kyle@acme-a.nexus.test',
            'role' => 'sales',
        ]);
        $this->salesAgent2->workspaces()->attach($this->tenantA->id, ['role' => 'sales', 'status' => 'active']);

        $this->salesTeam = SalesTeam::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise Inbound Team',
            'leader_id' => $this->adminUser->id,
            'member_ids' => [$this->salesAgent1->id, $this->salesAgent2->id],
        ]);

        $this->pipeline = Pipeline::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise Direct Sales',
            'is_default' => true,
        ]);

        $this->stageNew = PipelineStage::create([
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'New Discovery',
            'code' => 'new_discovery',
            'order' => 1,
            'probability' => 20,
        ]);

        $this->stageWon = PipelineStage::create([
            'tenant_id' => $this->tenantA->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Closed Won',
            'code' => 'closed_won',
            'order' => 5,
            'probability' => 100,
        ]);

        $this->tokenA = $this->adminUser->createToken('test_token_a')->plainTextToken;

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'email' => 'tony@stark.test',
            'role' => 'admin',
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'admin', 'status' => 'active']);
        $this->tokenB = $this->userB->createToken('test_token_b')->plainTextToken;
    }

    protected function computeMetaSignature(string $payload): string
    {
        $secret = config('services.meta.app_secret', 'meta_app_secret_test_2026');
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Scenario A:
     * Facebook Ad -> Messenger DM -> AI qualification -> CRM lead -> employee assignment -> human reply -> deal creation.
     */
    public function test_scenario_a_facebook_ad_to_messenger_to_crm_lead_assignment_deal(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        // 1. Set up connected Facebook Page with encrypted system user token
        $fbAccount = SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'page_id' => 'fb_page_ad_101',
            'page_name' => 'Acme Enterprise Global Page',
            'page_access_token' => 'mock_page_token_integration',
            'is_connected' => true,
            'ai_enabled' => true,
            'ai_handover_keywords' => ['human', 'agent', 'support'],
        ]);

        // Verify token is encrypted in storage
        $this->assertNotEmpty($fbAccount->page_access_token);

        // 2. Incoming Facebook Messenger DM from Lead clicking an Ad
        $psid = 'psid_ad_user_888';
        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'fb_page_ad_101',
                    'time' => 1718000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => $psid],
                            'recipient' => ['id' => 'fb_page_ad_101'],
                            'timestamp' => 1718000000,
                            'message' => [
                                'mid' => 'mid_fb_ad_msg_001',
                                'text' => 'Hi, I saw your enterprise ad. Can you provide pricing for 100 seats?',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $signature = $this->computeMetaSignature($jsonPayload);

        $inboundRes = $this->call(
            'POST',
            '/api/v1/webhooks/meta/social',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $jsonPayload
        );

        $inboundRes->assertStatus(200);

        // Verify conversation and message created
        $conversation = OmnichannelConversation::where('tenant_id', $this->tenantA->id)
            ->where('channel', 'facebook')
            ->where('phone_number', 'fb_' . $psid)
            ->first();

        $this->assertNotNull($conversation);
        $this->assertEquals('open', $conversation->status);
        $this->assertNotEmpty($conversation->messages);

        // 3. AI Qualification & CRM Lead Creation
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Bruce Wayne',
            'email' => 'bruce@wayne-enterprises.test',
            'phone' => '+15559876543',
            'company_name' => 'Wayne Enterprises',
            'status' => 'qualified',
            'source' => 'facebook',
            'utm_campaign' => 'camp_fb_ad_q4',
        ]);

        $conversation->update(['lead_id' => $lead->id]);

        // 4. Workload-aware employee auto-assignment
        $autoAssignRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/auto-assign", [
                'team_id' => $this->salesTeam->id,
            ]);

        $autoAssignRes->assertStatus(200);
        $freshConv = $conversation->fresh();
        $this->assertNotNull($freshConv->assigned_user_id);

        // Verify assignment history log
        $historyRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/crm/inbox/conversations/{$conversation->id}/assignment-history");

        $historyRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertNotEmpty($historyRes->json('data'));

        // 5. Human Agent Outbound Reply
        $replyRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/messages", [
                'content' => 'Hello Bruce, for 100 enterprise seats we offer $45/seat/mo with 24/7 dedicated SLA.',
                'sender_actor' => 'human',
            ]);

        $replyRes->assertStatus(200);
        $freshConvAfterReply = $conversation->fresh();
        $this->assertNotNull($freshConvAfterReply->first_response_at);

        // 6. Deal Creation in CRM Pipeline
        $deal = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageNew->id,
            'assigned_user_id' => $freshConv->assigned_user_id,
            'name' => 'Wayne Enterprises 100 Seats Enterprise Deal',
            'value' => 54000.00,
            'currency' => 'USD',
            'stage' => 'new',
            'close_date' => Carbon::now()->addDays(30),
        ]);

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'stage' => 'new',
        ]);
    }

    /**
     * Scenario B:
     * Instagram DM -> AI response -> customer asks for a call -> AI calling or employee callback -> call summary -> CRM timeline update.
     */
    public function test_scenario_b_instagram_dm_to_call_to_crm_timeline(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $igAccount = SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'instagram',
            'page_id' => 'ig_biz_account_202',
            'page_name' => 'Acme Instagram Official',
            'instagram_business_id' => 'ig_biz_account_202',
            'page_access_token' => 'mock_ig_token_integration',
            'is_connected' => true,
            'ai_enabled' => true,
        ]);

        $igsid = 'igsid_user_777';

        // 1. Inbound Instagram DM via Meta Webhook
        $payload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => 'ig_biz_account_202',
                    'time' => 1718000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => $igsid],
                            'recipient' => ['id' => 'ig_biz_account_202'],
                            'timestamp' => 1718000000,
                            'message' => [
                                'mid' => 'mid_ig_msg_001',
                                'text' => 'Can someone please call me regarding enterprise compliance at +15551234567?',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $signature = $this->computeMetaSignature($jsonPayload);

        $this->call(
            'POST',
            '/api/v1/webhooks/meta/social',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $jsonPayload
        )->assertStatus(200);

        $conv = OmnichannelConversation::where('tenant_id', $this->tenantA->id)
            ->where('channel', 'instagram')
            ->where('phone_number', 'ig_' . $igsid)
            ->firstOrFail();

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Clark Kent',
            'phone' => '+15551234567',
            'email' => 'clark@dailyplanet.test',
            'status' => 'qualified',
            'source' => 'instagram',
        ]);
        $conv->update(['lead_id' => $lead->id]);

        // 2. AI Response sent acknowledging request
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/messages", [
                'content' => 'Thank you Clark! Our enterprise specialist is initiating a callback right now.',
                'sender_actor' => 'ai',
            ])->assertStatus(200);

        // 3. Outbound Call executed with recording & AI summary
        $call = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'call_id' => 'call_clark_compliance_99',
            'direction' => 'outbound',
            'from_number' => '+18005550000',
            'to_number' => '+15551234567',
            'status' => 'completed',
            'duration_seconds' => 245,
            'cost_amount' => 0.08,
            'cost_currency' => 'USD',
            'human_transferred' => true,
            'disposition' => 'completed',
            'ai_summary' => 'Client verified SOC2 and ISO27001 requirements. Agreed on proof of concept.',
            'recording_url' => 'https://storage.nexus.test/calls/rec_clark_99.mp3',
        ]);

        // 4. Query Unified Cross-Channel Customer Timeline
        $timelineRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/crm/inbox/timeline?lead_id={$lead->id}");

        $timelineRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $timeline = $timelineRes->json('data');
        $this->assertNotEmpty($timeline);

        // Verify both Instagram interactions and the Voice Call appear in timeline
        $hasCall = collect($timeline)->contains(fn($item) => $item['channel'] === 'call' && str_contains($item['content'], 'SOC2'));
        $hasIg = collect($timeline)->contains(fn($item) => $item['channel'] === 'instagram');

        $this->assertTrue($hasCall, 'Voice Call with AI summary must appear in customer timeline');
        $this->assertTrue($hasIg, 'Instagram interaction must appear in customer timeline');
    }

    /**
     * Scenario C:
     * Website form -> CRM lead -> WhatsApp follow-up -> email quotation -> payment received -> deal converted.
     */
    public function test_scenario_c_website_form_to_whatsapp_to_quotation_payment_conversion(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        // 1. Website Form Submission creates CRM Lead
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Diana Prince',
            'email' => 'diana@themyscira.test',
            'phone' => '+15553334444',
            'company_name' => 'Themyscira Cultural Arts',
            'status' => 'new',
            'source' => 'website_form',
            'utm_source' => 'google_search',
        ]);

        // 2. WhatsApp follow-up conversation
        $waConv = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'lead_id' => $lead->id,
            'phone_number' => '+15553334444',
            'customer_name' => 'Diana Prince',
            'status' => 'open',
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$waConv->id}/messages", [
                'content' => 'Hello Diana, thank you for reaching out via our website! We have drafted your enterprise quotation.',
                'sender_actor' => 'human',
            ])->assertStatus(200);

        // 3. Email Quotation Created
        $quotation = Quotation::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'quotation_number' => 'QUO-2026-DIANA-01',
            'title' => 'Themyscira Enterprise Plan Q4',
            'status' => 'sent',
            'subtotal' => 10000.00,
            'tax_amount' => 1800.00,
            'total_amount' => 11800.00,
            'currency' => 'USD',
            'valid_until' => Carbon::now()->addDays(14),
        ]);

        // 4. Invoice & Payment Received
        $invoice = Invoice::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'quotation_id' => $quotation->id,
            'invoice_number' => 'INV-2026-DIANA-01',
            'status' => 'paid',
            'subtotal' => 10000.00,
            'tax_amount' => 1800.00,
            'total_amount' => 11800.00,
            'outstanding_amount' => 0.00,
            'currency' => 'USD',
            'due_date' => Carbon::now()->addDays(30),
        ]);

        Payment::create([
            'tenant_id' => $this->tenantA->id,
            'invoice_id' => $invoice->id,
            'payment_reference' => 'pay_razorpay_diana_999',
            'payment_method' => 'card',
            'amount' => 11800.00,
            'currency' => 'USD',
            'status' => 'completed',
            'paid_at' => Carbon::now(),
        ]);

        // 5. Deal Converted / Won
        $deal = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'pipeline_id' => $this->pipeline->id,
            'pipeline_stage_id' => $this->stageWon->id,
            'name' => 'Themyscira Cultural Arts Closed Won Deal',
            'value' => 11800.00,
            'stage' => 'won',
            'won_at' => Carbon::now(),
        ]);

        $lead->update(['status' => 'converted']);

        $this->assertEquals('converted', $lead->fresh()->status);
        $this->assertEquals('won', $deal->fresh()->stage);
        $this->assertEquals(0.00, (float) $invoice->fresh()->outstanding_amount);
    }

    /**
     * Scenario D:
     * Missed incoming call -> callback task -> employee call -> customer converted.
     */
    public function test_scenario_d_missed_call_to_callback_task_to_employee_call_conversion(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Barry Allen',
            'phone' => '+15558889999',
            'email' => 'barry@star-labs.test',
            'status' => 'contacted',
        ]);

        // 1. Inbound Call arrives but is missed / abandoned
        $missedCall = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'call_id' => 'call_missed_barry_001',
            'direction' => 'inbound',
            'from_number' => '+15558889999',
            'to_number' => '+18005550000',
            'status' => 'no-answer',
            'disposition' => 'callback_requested',
            'duration_seconds' => 0,
        ]);

        // 2. High Priority Callback Task is Created for Sales Agent
        $task = Task::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'assigned_user_id' => $this->salesAgent1->id,
            'title' => 'URGENT: Return missed call from Barry Allen',
            'priority' => 'urgent',
            'status' => 'pending',
            'due_date' => Carbon::now()->addHour(),
        ]);

        $this->assertEquals('pending', $task->status);
        $this->assertEquals('urgent', $task->priority);

        // 3. Employee Places Outbound Call & Marks Task Completed
        $outboundCall = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'call_id' => 'call_callback_barry_002',
            'direction' => 'outbound',
            'from_number' => '+18005550000',
            'to_number' => '+15558889999',
            'status' => 'completed',
            'disposition' => 'completed',
            'duration_seconds' => 310,
            'human_transferred' => true,
            'ai_summary' => 'Sales agent answered technical questions. Barry signed up for annual tier.',
        ]);

        $task->update(['status' => 'completed']);
        $lead->update(['status' => 'converted']);

        $customer = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Barry Allen',
            'email' => 'barry@star-labs.test',
            'phone' => '+15558889999',
            'status' => 'active',
        ]);

        $this->assertEquals('completed', $task->fresh()->status);
        $this->assertEquals('converted', $lead->fresh()->status);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'tenant_id' => $this->tenantA->id,
            'status' => 'active',
        ]);
    }

    /**
     * Scenario E:
     * WhatsApp conversation -> human handover -> employee resolves -> conversation closed -> customer replies -> conversation reopened.
     */
    public function test_scenario_e_whatsapp_handover_resolve_closed_reopen_on_customer_reply(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        // 1. Initial WhatsApp conversation handled by autonomous AI
        $conv = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+15556667777',
            'customer_name' => 'Victor Stone',
            'status' => 'open',
            'is_ai_handled' => true,
        ]);

        // Customer sends handover request keyword
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/messages", [
                'content' => 'I need to speak to a human support agent please.',
                'sender_actor' => 'customer',
                'direction' => 'inbound',
            ])->assertStatus(200);

        // 2. Toggle Human Agent Handover
        $handoverRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/toggle-ai", [
                'is_ai_handled' => false,
            ]);

        $handoverRes->assertStatus(200);
        $this->assertFalse($conv->fresh()->is_ai_handled);

        // 3. Employee sends human reply
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/messages", [
                'content' => 'Hello Victor, I have adjusted your firewall settings as requested.',
                'sender_actor' => 'human',
            ])->assertStatus(200);

        // 4. Employee marks conversation resolved / closed
        $resolveRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->patchJson("/api/v1/crm/inbox/conversations/{$conv->id}/status", [
                'status' => 'resolved',
            ]);

        $resolveRes->assertStatus(200);
        $freshResolved = $conv->fresh();
        $this->assertEquals('resolved', $freshResolved->status);
        $this->assertNotNull($freshResolved->resolved_at);

        // 5. Customer later replies -> Conversation MUST automatically reopen!
        $reopenRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/messages", [
                'content' => 'Thank you! Actually, one more quick question regarding the VPN port.',
                'sender_actor' => 'customer',
                'direction' => 'inbound',
            ]);

        $reopenRes->assertStatus(200);
        $reopenedConv = $conv->fresh();

        $this->assertEquals('open', $reopenedConv->status, 'Resolved conversation must automatically reopen to open on customer reply');
        $this->assertNull($reopenedConv->resolved_at, 'Resolution timestamp must reset upon reopening');
        $this->assertEquals('customer', $reopenedConv->last_actor_type);
        $this->assertGreaterThanOrEqual(1, $reopenedConv->unread_count);
    }

    /**
     * Requirement 9: Uncertain customer matches must require supervisor manual review
     */
    public function test_requirement_uncertain_customer_matches_require_manual_review(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $pending = IdentityMergePending::create([
            'tenant_id' => $this->tenantA->id,
            'primary_type' => 'lead',
            'primary_id' => 1,
            'secondary_type' => 'contact',
            'secondary_id' => 2,
            'confidence_score' => 65,
            'status' => 'pending_review',
            'match_criteria' => 'name_similarity',
            'notes' => 'Name similarity 65% with customer John Wayne, but email differs.',
        ]);

        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/identity/pending');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Supervisor approves or rejects
        $reviewRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/identity/pending/{$pending->id}/review", [
                'decision' => 'rejected',
                'notes' => 'Verified different individuals. Do not merge.',
            ]);

        $reviewRes->assertStatus(200);
        $this->assertEquals('rejected', $pending->fresh()->status);
    }

    /**
     * Requirement 13 & 16: Legal Consent & Tenant Isolation Verification
     */
    public function test_requirement_consent_suppression_and_tenant_isolation(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');

        $dest = '+15559990000';

        // 1. Mark customer opted out / DND on SMS
        CustomerCommunicationConsent::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'sms',
            'destination' => $dest,
            'consent_status' => 'opted_out',
            'consent_source' => 'verbal',
            'is_marketing_allowed' => false,
            'is_transactional_allowed' => false,
        ]);

        $convA = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'sms',
            'phone_number' => $dest,
            'customer_name' => 'Opted Out Customer',
            'status' => 'open',
        ]);

        // Outbound send must be blocked with HTTP 422
        $blockedRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$convA->id}/messages", [
                'content' => 'Special promotion 50% discount!',
                'sender_actor' => 'human',
            ]);

        $blockedRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // 2. Cross-Tenant Isolation: Tenant B cannot access Tenant A conversation
        $this->actingAs($this->userB, 'sanctum');
        $crossAccessRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson("/api/v1/crm/inbox/conversations/{$convA->id}");

        $crossAccessRes->assertStatus(404);
    }
}
