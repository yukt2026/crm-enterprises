<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\Voice\Models\TelephonyOptOut;
use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\TelephonyProvider;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\Voice\Models\VoiceCallingCampaign;
use App\Domains\Voice\Services\VoiceAiPipelineService;
use App\Domains\Voice\Services\VoiceCrmAutomationService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VoiceTelephonyModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected User $userB;
    protected string $tokenAdmin;
    protected string $tokenB;
    protected TelephonyProvider $exotelProvider;
    protected TelephonyProvider $twilioProvider;
    protected TelephonyPhoneNumber $numberA;
    protected VoiceAiAgent $voiceAgentA;
    protected Lead $leadA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Telephony India',
            'slug' => 'acme-telephony',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Global Voice',
            'slug' => 'beta-voice',
            'is_active' => true,
        ]);

        $this->userAdmin = User::create([
            'name' => 'Kunal Sharma',
            'email' => 'kunal.voice@acme.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);
        $this->tokenAdmin = $this->userAdmin->createToken('admin_token')->plainTextToken;

        $this->userB = User::create([
            'name' => 'David Beta',
            'email' => 'david@beta.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
            'current_tenant_id' => $this->tenantB->id,
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin', 'status' => 'active']);
        $this->tokenB = $this->userB->createToken('beta_token')->plainTextToken;

        Sanctum::actingAs($this->userAdmin);
        TenantContext::setTenant($this->tenantA);

        // Exotel Provider Setup (India Focus)
        $this->exotelProvider = TelephonyProvider::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'exotel',
            'name' => 'Exotel India Core',
            'account_sid' => 'exotel_sid_123',
            'auth_token' => 'exotel_token_secret',
            'api_key' => 'exotel_api_key_456',
            'subdomain' => 'api.exotel.com',
            'caller_id' => '+918047192000',
            'dlt_entity_id' => 'DLT_IND_11002233',
            'is_active' => true,
            'supports_recordings' => true,
        ]);

        // Twilio Provider Setup (Global)
        $this->twilioProvider = TelephonyProvider::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'twilio',
            'name' => 'Twilio Voice',
            'account_sid' => 'AC_twilio_sid_123',
            'auth_token' => 'twilio_auth_secret',
            'caller_id' => '+14155552671',
            'is_active' => true,
            'supports_recordings' => true,
        ]);

        // Virtual DID Phone Number
        $this->numberA = TelephonyPhoneNumber::create([
            'tenant_id' => $this->tenantA->id,
            'telephony_provider_id' => $this->exotelProvider->id,
            'phone_number' => '+918047192000',
            'display_name' => 'Acme Sales Inbound Line',
            'country_code' => 'IN',
            'is_default' => true,
            'routing_type' => 'ai_agent',
            'is_active' => true,
        ]);

        // Voice AI Agent
        $this->voiceAgentA = VoiceAiAgent::create([
            'tenant_id' => $this->tenantA->id,
            'telephony_phone_number_id' => $this->numberA->id,
            'name' => 'Priya - Enterprise Sales Agent',
            'description' => 'Autonomous voice agent for outbound lead qualification and inbound inquiries.',
            'status' => 'active',
            'language' => 'hinglish',
            'voice_engine' => 'elevenlabs',
            'voice_id' => 'hi-IN-Priya',
            'tone' => 'consultative',
            'system_instructions' => 'You are Priya from Acme Technologies. Qualify leads by asking for team size and budget.',
            'call_opening' => 'Namaste! Main Acme Technologies se Priya bol rahi hoon. Yeh call quality aur training ke liye record ki ja sakti hai.',
            'max_call_duration_seconds' => 600,
            'human_transfer_number' => '+919876500000',
            'allowed_crm_actions' => ['update_lead', 'schedule_callback', 'create_task', 'escalate_to_human'],
            'monthly_call_minutes_budget' => 1000,
            'used_call_minutes_this_month' => 50,
            'version' => 1,
        ]);

        // Target CRM Lead
        $this->leadA = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Rohan Mehta',
            'phone' => '+919822334455',
            'status' => 'new',
            'source' => 'google-ads',
            'requirement' => 'CRM Cloud Deployment',
            'score' => 40,
        ]);
    }

    public function test_voice_ai_agent_creation_and_configuration(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson('/api/v1/crm/voice/agents', [
                'name' => 'Aarav - Inbound Support Agent',
                'description' => 'Handles after-hours support triage',
                'telephony_phone_number_id' => $this->numberA->id,
                'status' => 'active',
                'language' => 'hi',
                'voice_engine' => 'openai',
                'voice_id' => 'alloy',
                'tone' => 'friendly',
                'system_instructions' => 'Aap Acme Support sahayak hain. Customer query note karein.',
                'call_opening' => 'Namaste! Acme Support mein aapka swagat hai. Yeh call record ki ja rahi hai.',
                'max_call_duration_seconds' => 300,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Aarav - Inbound Support Agent')
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('voice_ai_agents', [
            'name' => 'Aarav - Inbound Support Agent',
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    public function test_inbound_call_exoml_generation_and_disclosure(): void
    {
        $response = $this->post('/api/v1/telephony/webhooks/exotel/connect', [
            'From' => '+919822334455',
            'To' => '+918047192000',
            'CallSid' => 'exo_inbound_test_123',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type'));
        $content = $response->getContent();

        // Must contain ExoML Response tag and Say opening disclosure
        $this->assertStringContainsString('<Response>', $content);
        $this->assertStringContainsString('<Say>', $content);
        $this->assertStringContainsString('Acme Technologies', $content);
        $this->assertStringContainsString('<Record', $content);
    }

    public function test_inbound_call_twiml_generation(): void
    {
        $response = $this->post('/api/v1/telephony/webhooks/twilio/voice', [
            'From' => '+14155550100',
            'To' => '+14155552671',
            'CallSid' => 'CA_twilio_inbound_test',
        ]);

        $response->assertStatus(200);
        $content = $response->getContent();

        // Must contain TwiML Response and Say tag
        $this->assertStringContainsString('<Response>', $content);
        $this->assertStringContainsString('<Say', $content);
        $this->assertStringContainsString('<Record', $content);
    }

    public function test_outbound_call_dispatch(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson('/api/v1/crm/voice/calls/dial', [
                'to_number' => '+919822334455',
                'voice_agent_id' => $this->voiceAgentA->id,
                'lead_id' => $this->leadA->id,
                'provider' => 'exotel',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'in-progress');

        $callId = $response->json('data.id');
        $this->assertNotNull($callId);

        $call = VoiceCall::find($callId);
        $this->assertNotNull($call);
        $this->assertNotNull($call->provider_call_id);
        $this->assertEquals('+919822334455', $call->to_number);
    }

    public function test_dnd_opt_out_blocks_outbound_calling(): void
    {
        // Add number to DND suppression list
        TelephonyOptOut::create([
            'tenant_id' => $this->tenantA->id,
            'phone_number' => '+919999999999',
            'opt_out_type' => 'trai_dnd',
            'reason' => 'Registered on National Customer Preference Register (NCPR)',
            'opted_out_at' => Carbon::now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson('/api/v1/crm/voice/calls/dial', [
                'to_number' => '+919999999999',
                'voice_agent_id' => $this->voiceAgentA->id,
            ]);

        // Must fail with 422 Unprocessable Entity and DND rejection message
        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('DND / Opt-Out suppression', $response->json('message'));
    }

    public function test_speech_transcript_and_barge_in_handling(): void
    {
        $call = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'voice_agent_id' => $this->voiceAgentA->id,
            'lead_id' => $this->leadA->id,
            'direction' => 'outbound',
            'from_number' => '+918047192000',
            'to_number' => '+919822334455',
            'status' => 'in-progress',
        ]);

        $pipeline = app(VoiceAiPipelineService::class);

        // Turn 1 with customer barge-in
        $turn1 = $pipeline->processTurn(
            $call,
            $this->voiceAgentA,
            'Wait, let me speak! I am interested in CRM quotation for 2 lakh budget.',
            true // Barge-in detected
        );

        $this->assertTrue($turn1['barge_in_handled']);
        $this->assertEquals('lead_qualification', $turn1['detected_intent']);
        $this->assertEquals(200000, $turn1['extracted_entities']['budget']);
        $this->assertNotEmpty($turn1['agent_reply_text']);

        // Check transcript stored in call
        $call->refresh();
        $this->assertStringContainsString('Wait, let me speak!', $call->transcript);
        $this->assertStringContainsString('[AI Agent]:', $call->transcript);
    }

    public function test_post_call_crm_automation_and_lead_qualification(): void
    {
        $call = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'voice_agent_id' => $this->voiceAgentA->id,
            'lead_id' => $this->leadA->id,
            'direction' => 'outbound',
            'from_number' => '+918047192000',
            'to_number' => '+919822334455',
            'status' => 'in-progress',
            'duration_seconds' => 125,
            'transcript' => "[User]: Yes, we need enterprise CRM and have 3 lakh budget. Please schedule demo.\n[AI Agent]: Great, I will arrange a follow-up demo tomorrow.",
            'extracted_data' => ['budget' => 300000],
        ]);

        $automation = app(VoiceCrmAutomationService::class);
        $automation->processPostCallAutomation($call);

        $call->refresh();
        $this->assertEquals('interested', $call->disposition);
        $this->assertEquals('qualified', $call->qualification_result);
        $this->assertNotNull($call->ai_summary);
        $this->assertNotNull($call->next_follow_up_at);

        // Verify Lead updated
        $this->leadA->refresh();
        $this->assertEquals('qualified', $this->leadA->qualification_status);
        $this->assertEquals(300000, $this->leadA->estimated_value);
        $this->assertGreaterThan(50, $this->leadA->score);

        // Verify CrmActivity created
        $this->assertDatabaseHas('crm_activities', [
            'lead_id' => $this->leadA->id,
            'activity_type' => 'call',
        ]);

        // Verify Follow-up Task created
        $this->assertDatabaseHas('tasks', [
            'lead_id' => $this->leadA->id,
            'priority' => 'high',
        ]);
    }

    public function test_customer_dnd_verbal_request_auto_suppresses_number(): void
    {
        $call = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'voice_agent_id' => $this->voiceAgentA->id,
            'lead_id' => $this->leadA->id,
            'direction' => 'outbound',
            'from_number' => '+918047192000',
            'to_number' => '+919822334455',
            'status' => 'in-progress',
            'duration_seconds' => 45,
            'transcript' => "[User]: Please put me on DND. Stop calling this number.",
        ]);

        $automation = app(VoiceCrmAutomationService::class);
        $automation->processPostCallAutomation($call);

        $call->refresh();
        $this->assertEquals('dnd_requested', $call->disposition);
        $this->assertEquals('unqualified', $call->qualification_result);

        // Verify telephone number is now registered in DND table
        $this->assertDatabaseHas('telephony_opt_outs', [
            'tenant_id' => $this->tenantA->id,
            'phone_number' => '+919822334455',
            'opt_out_type' => 'customer_verbal',
        ]);
    }

    public function test_calling_campaign_execution_batch(): void
    {
        $campaign = VoiceCallingCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'voice_agent_id' => $this->voiceAgentA->id,
            'name' => 'Q4 High-Intent Web Leads Campaign',
            'status' => 'draft',
            'target_filters' => ['status' => 'new'],
            'calls_per_minute_limit' => 10,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson("/api/v1/crm/voice/campaigns/{$campaign->id}/launch", [
                'batch_limit' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $campaign->refresh();
        $this->assertEquals('running', $campaign->status);
        $this->assertGreaterThanOrEqual(1, $campaign->total_contacts_called);
    }

    public function test_call_duration_reconciliation_and_usage_metering(): void
    {
        $initialUsedMinutes = $this->voiceAgentA->used_call_minutes_this_month;

        $call = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'voice_agent_id' => $this->voiceAgentA->id,
            'provider' => 'exotel',
            'provider_call_id' => 'exo_metering_test_123',
            'direction' => 'outbound',
            'from_number' => '+918047192000',
            'to_number' => '+919822334455',
            'status' => 'ringing',
        ]);

        // Send Provider Status Webhook callback (125 seconds = 3 billing minutes rounded up)
        $response = $this->post('/api/v1/telephony/webhooks/exotel/status', [
            'CallSid' => 'exo_metering_test_123',
            'Status' => 'completed',
            'RecordingUrl' => 'https://s3.ap-south-1.amazonaws.com/exotel-recordings/test.mp3',
            'Duration' => 125,
        ]);

        $response->assertStatus(200);

        $call->refresh();
        $this->assertEquals('completed', $call->status);
        $this->assertEquals(125, $call->duration_seconds);
        $this->assertNotNull($call->recording_url);
        $this->assertGreaterThan(0.0, $call->cost_amount);

        // Verify minutes deducted from agent budget
        $this->voiceAgentA->refresh();
        $this->assertEquals($initialUsedMinutes + 3, $this->voiceAgentA->used_call_minutes_this_month);
    }

    public function test_cross_tenant_call_isolation(): void
    {
        $callA = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'voice_agent_id' => $this->voiceAgentA->id,
            'from_number' => '+918047192000',
            'to_number' => '+919822334455',
            'status' => 'completed',
            'duration_seconds' => 90,
        ]);

        // Tenant B requests call details
        Sanctum::actingAs($this->userB);
        TenantContext::setTenant($this->tenantB);

        $responseB = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson("/api/v1/crm/voice/calls/{$callA->id}");

        $responseB->assertStatus(404);

        // Tenant B calls list must not include Tenant A calls
        $listB = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson('/api/v1/crm/voice/calls');

        $listB->assertStatus(200);
        $this->assertEmpty($listB->json('data'));

        // Restore Tenant A
        Sanctum::actingAs($this->userAdmin);
        TenantContext::setTenant($this->tenantA);
    }
}
