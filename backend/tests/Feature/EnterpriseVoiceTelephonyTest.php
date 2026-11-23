<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\TenantContext;
use App\Domains\Voice\Models\CallIvrFlow;
use App\Domains\Voice\Models\CallQueue;
use App\Domains\Voice\Models\CallRecordingAccessLog;
use App\Domains\Voice\Models\TelephonyPhoneNumber;
use App\Domains\Voice\Models\TelephonyProvider;
use App\Domains\Voice\Models\TelephonyProviderLog;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnterpriseVoiceTelephonyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $repUser;
    protected TelephonyProvider $provider;
    protected TelephonyPhoneNumber $phoneNumber;
    protected Lead $lead;
    protected Contact $contact;
    protected Deal $deal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tata Tele Enterprises',
            'slug' => 'tata-tele',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Rajesh Sharma',
            'email' => 'rajesh.sharma@tatatele.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->adminUser->workspaces()->attach($this->tenant->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->repUser = User::create([
            'name' => 'Amit Kumar',
            'email' => 'amit.rep@tatatele.com',
            'password' => bcrypt('password123'),
            'role' => 'Sales Representative',
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->repUser->workspaces()->attach($this->tenant->id, ['role' => 'Sales Representative', 'status' => 'active']);

        TenantContext::setTenant($this->tenant);

        // Exotel Provider
        $this->provider = TelephonyProvider::create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'exotel',
            'name' => 'Exotel Virtual PBX',
            'account_sid' => 'exotel_acc_9988',
            'auth_token' => 'exotel_sec_token_7766',
            'caller_id' => '+918047192000',
            'is_active' => true,
            'supports_recordings' => true,
        ]);

        // Virtual Phone Number
        $this->phoneNumber = TelephonyPhoneNumber::create([
            'tenant_id' => $this->tenant->id,
            'telephony_provider_id' => $this->provider->id,
            'phone_number' => '+918047192000',
            'display_name' => 'Primary Sales Helpline',
            'is_default' => true,
            'routing_type' => 'ivr',
            'is_active' => true,
        ]);

        // Lead & Contact for caller matching
        $this->lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vikas Malhotra',
            'phone' => '+919876543210',
            'email' => 'vikas@malhotracorp.in',
            'company_name' => 'Malhotra Corp',
            'status' => 'new',
            'score' => 50,
            'assigned_user_id' => $this->repUser->id,
        ]);

        $this->contact = Contact::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Priya Nair',
            'phone' => '+919988776655',
            'email' => 'priya@nairlogistics.com',
        ]);

        $this->deal = Deal::create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'name' => 'Nair Logistics ERP Suite',
            'value' => 750000.00,
            'currency' => 'INR',
            'stage' => 'negotiation',
            'assigned_user_id' => $this->repUser->id,
        ]);
    }

    /**
     * 1. Test Provider Connection Test & Diagnostics.
     */
    public function test_telephony_provider_connection_test(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson("/api/v1/crm/voice/providers/{$this->provider->id}/test");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'status' => 'connected',
                'provider' => 'exotel',
            ]);

        $this->assertDatabaseHas('telephony_provider_logs', [
            'tenant_id' => $this->tenant->id,
            'provider' => 'exotel',
            'event_type' => 'connection_test',
            'status' => 'success',
        ]);
    }

    /**
     * 2. Test Outbound Click-to-Call from CRM records with CrmActivity generation.
     */
    public function test_click_to_call_generates_activity_and_call_record(): void
    {
        Sanctum::actingAs($this->repUser);

        $response = $this->postJson('/api/v1/crm/voice/calls/click-to-call', [
            'to_number' => '+919988776655',
            'from_number' => '+918047192000',
            'contact_id' => $this->contact->id,
            'deal_id' => $this->deal->id,
            'provider' => 'exotel',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'call_type' => 'human',
                    'to_number' => '+919988776655',
                    'contact_id' => $this->contact->id,
                    'deal_id' => $this->deal->id,
                ],
            ]);

        $callId = $response->json('data.id');

        $this->assertDatabaseHas('voice_calls', [
            'id' => $callId,
            'tenant_id' => $this->tenant->id,
            'call_type' => 'human',
            'contact_id' => $this->contact->id,
            'deal_id' => $this->deal->id,
            'assigned_user_id' => $this->repUser->id,
        ]);
    }

    /**
     * 3. Test Caller Lookup for known contact with open deals & previous history.
     */
    public function test_caller_lookup_identifies_known_contact_and_deals(): void
    {
        Sanctum::actingAs($this->repUser);

        $response = $this->postJson('/api/v1/crm/voice/calls/lookup', [
            'phone_number' => '+919988776655',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_known_customer' => true,
                    'customer_name' => 'Priya Nair',
                    'customer_email' => 'priya@nairlogistics.com',
                ],
            ]);

        $deals = $response->json('data.open_deals');
        $this->assertCount(1, $deals);
        $this->assertEquals('Nair Logistics ERP Suite', $deals[0]['name']);
    }

    /**
     * 4. Test Caller Lookup for unknown number gives quick-create suggestion.
     */
    public function test_caller_lookup_for_unknown_number(): void
    {
        Sanctum::actingAs($this->repUser);

        $response = $this->postJson('/api/v1/crm/voice/calls/lookup', [
            'phone_number' => '+919123456789',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_known_customer' => false,
                    'quick_create_suggestion' => [
                        'phone' => '+919123456789',
                        'source' => 'phone_call',
                    ],
                ],
            ]);
    }

    /**
     * 5. Test Inbound Call handling with Webhook Deduplication (Idempotency).
     */
    public function test_inbound_call_with_idempotency_deduplication(): void
    {
        Sanctum::actingAs($this->adminUser);

        $idempotencyKey = 'call_webhook_unique_9988';

        // First event
        $res1 = $this->postJson('/api/v1/crm/voice/calls/inbound', [
            'from_number' => '+919876543210',
            'to_number' => '+918047192000',
            'provider' => 'exotel',
            'provider_call_id' => 'sid_exo_112233',
            'idempotency_key' => $idempotencyKey,
        ]);

        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_duplicate' => false,
            ]);

        $firstCallId = $res1->json('data.id');

        // Repeated webhook event with same idempotency key
        $res2 = $this->postJson('/api/v1/crm/voice/calls/inbound', [
            'from_number' => '+919876543210',
            'to_number' => '+918047192000',
            'provider' => 'exotel',
            'provider_call_id' => 'sid_exo_112233',
            'idempotency_key' => $idempotencyKey,
        ]);

        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_duplicate' => true,
            ]);

        $this->assertEquals($firstCallId, $res2->json('data.id'));
    }

    /**
     * 6. Test Call Disposition update triggers CRM sync and follow-up Task creation.
     */
    public function test_call_disposition_triggers_crm_sync_and_task(): void
    {
        Sanctum::actingAs($this->repUser);

        $call = VoiceCall::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->lead->id,
            'assigned_user_id' => $this->repUser->id,
            'from_number' => '+918047192000',
            'to_number' => '+919876543210',
            'direction' => 'outbound',
            'status' => 'in-progress',
        ]);

        $response = $this->postJson("/api/v1/crm/voice/calls/{$call->id}/disposition", [
            'status' => 'completed',
            'disposition' => 'interested',
            'qualification_result' => 'qualified',
            'duration_seconds' => 180,
            'follow_up_required' => true,
            'ai_summary' => 'Client expressed genuine interest in Enterprise CRM 100-user license.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'disposition' => 'interested',
                    'qualification_result' => 'qualified',
                ],
            ]);

        // Verify CrmActivity is logged
        $this->assertDatabaseHas('crm_activities', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->lead->id,
            'activity_type' => 'call',
        ]);

        // Verify Task is scheduled
        $this->assertDatabaseHas('tasks', [
            'tenant_id' => $this->tenant->id,
            'lead_id' => $this->lead->id,
            'priority' => 'high',
        ]);
    }

    /**
     * 7. Test IVR Flows & Call Queues management.
     */
    public function test_ivr_flows_and_call_queues_crud(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create Queue
        $queueRes = $this->postJson('/api/v1/crm/voice/queues', [
            'name' => 'Sales Inbound Tier 1',
            'department' => 'Sales',
            'strategy' => 'round_robin',
            'max_wait_time_seconds' => 60,
            'assigned_agent_ids' => [$this->repUser->id],
            'is_active' => true,
        ]);

        $queueRes->assertStatus(201);
        $queueId = $queueRes->json('data.id');

        // Create IVR Flow
        $ivrRes = $this->postJson('/api/v1/crm/voice/ivr-flows', [
            'name' => 'Main Enterprise Greeting Flow',
            'welcome_audio_text' => 'Namaste! Welcome to Tata Tele. Press 1 for Sales, Press 2 for Support.',
            'language' => 'hi',
            'phone_number_id' => $this->phoneNumber->id,
            'business_hours' => [
                'open' => '09:00',
                'close' => '18:00',
                'days' => [1, 2, 3, 4, 5, 6],
                'timezone' => 'Asia/Kolkata',
            ],
            'menu_options' => [
                ['key' => '1', 'action' => 'route_queue', 'target_queue_id' => $queueId, 'label' => 'Sales'],
                ['key' => '2', 'action' => 'route_queue', 'label' => 'Support'],
            ],
            'unanswered_action' => 'voicemail',
            'is_active' => true,
        ]);

        $ivrRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'language' => 'hi',
                    'name' => 'Main Enterprise Greeting Flow',
                ],
            ]);
    }

    /**
     * 8. Test AI Voice Calling Agent Configuration with Business Purpose & Regional Dialects.
     */
    public function test_ai_agent_creation_with_business_purpose_and_dialects(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/crm/voice/agents', [
            'name' => 'Aarav Indian AI Sales Executive',
            'description' => 'Outbound Lead Qualification Agent in Hindi & Hinglish',
            'business_purpose' => 'lead_qualification',
            'status' => 'active',
            'language' => 'hinglish',
            'regional_dialects' => ['hi', 'hinglish', 'en', 'mr', 'gu'],
            'voice_engine' => 'elevenlabs',
            'voice_id' => 'aarav_indic_v2',
            'tone' => 'consultative',
            'system_instructions' => 'Qualify B2B CRM prospects with respectful Indian greeting and budget verification.',
            'call_opening' => 'Namaste! Main Acme solutions se Aarav bol raha hoon. Kya aapse 2 minute baat ho sakti hai?',
            'confidence_threshold' => 0.70,
            'max_call_duration_seconds' => 300,
            'human_transfer_number' => '+919876500000',
            'monthly_call_minutes_budget' => 2000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'business_purpose' => 'lead_qualification',
                    'language' => 'hinglish',
                    'confidence_threshold' => 0.70,
                ],
            ]);
    }

    /**
     * 9. Test AI Conversation Turn Simulation with Hindi & Budget Entity Extraction.
     */
    public function test_ai_turn_simulation_with_hindi_and_budget_extraction(): void
    {
        Sanctum::actingAs($this->repUser);

        $agent = VoiceAiAgent::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hindi Sales AI',
            'language' => 'hinglish',
            'business_purpose' => 'lead_qualification',
            'status' => 'active',
            'voice_engine' => 'elevenlabs',
            'voice_id' => 'alloy',
            'tone' => 'consultative',
            'system_instructions' => 'Help customers.',
            'call_opening' => 'Namaste!',
        ]);

        $call = VoiceCall::create([
            'tenant_id' => $this->tenant->id,
            'voice_agent_id' => $agent->id,
            'lead_id' => $this->lead->id,
            'from_number' => '+918047192000',
            'to_number' => '+919876543210',
            'direction' => 'outbound',
            'status' => 'in-progress',
        ]);

        // Customer mentions 5 Lakh budget in Hinglish
        $response = $this->postJson("/api/v1/crm/voice/calls/{$call->id}/turn", [
            'user_speech_text' => 'Haanji namaste, mujhe CRM software chahiye, hamara budget 5 lakh rupees hai.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'detected_intent' => 'lead_qualification_budget_stated',
                    'extracted_entities' => [
                        'budget' => 500000,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('voice_calls', [
            'id' => $call->id,
            'qualification_result' => 'qualified',
            'disposition' => 'interested',
        ]);
    }

    /**
     * 10. Test AI Human Handoff Trigger when Customer Requests Specialist or Manager.
     */
    public function test_ai_human_handoff_trigger(): void
    {
        Sanctum::actingAs($this->repUser);

        $agent = VoiceAiAgent::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Demo Agent',
            'language' => 'hinglish',
            'business_purpose' => 'lead_qualification',
            'status' => 'active',
            'voice_engine' => 'elevenlabs',
            'voice_id' => 'alloy',
            'tone' => 'consultative',
            'system_instructions' => 'Help.',
            'call_opening' => 'Namaste!',
            'human_transfer_number' => '+919876511111',
        ]);

        $call = VoiceCall::create([
            'tenant_id' => $this->tenant->id,
            'voice_agent_id' => $agent->id,
            'from_number' => '+918047192000',
            'to_number' => '+919876543210',
            'direction' => 'outbound',
            'status' => 'in-progress',
        ]);

        $response = $this->postJson("/api/v1/crm/voice/calls/{$call->id}/turn", [
            'user_speech_text' => 'Mujhe aapke senior manager se baat karni hai, transfer kijiye.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'should_transfer' => true,
                    'detected_intent' => 'human_transfer_requested',
                    'transfer_target' => '+919876511111',
                ],
            ]);

        $this->assertDatabaseHas('voice_calls', [
            'id' => $call->id,
            'human_transferred' => true,
            'hangup_cause' => 'TRANSFERRED_TO_HUMAN',
        ]);
    }

    /**
     * 11. Test Recording Playback & Download with RBAC Audit Logging.
     */
    public function test_recording_access_and_download_logging(): void
    {
        Sanctum::actingAs($this->adminUser);

        $call = VoiceCall::create([
            'tenant_id' => $this->tenant->id,
            'assigned_user_id' => $this->repUser->id,
            'from_number' => '+918047192000',
            'to_number' => '+919876543210',
            'direction' => 'outbound',
            'status' => 'completed',
            'duration_seconds' => 120,
            'recording_url' => 'https://api.exotel.com/recordings/call_demo_123.mp3',
        ]);

        // Playback
        $playbackRes = $this->getJson("/api/v1/crm/voice/calls/{$call->id}/recording/playback");
        $playbackRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'recording_url' => 'https://api.exotel.com/recordings/call_demo_123.mp3',
                ],
            ]);

        // Download
        $downloadRes = $this->getJson("/api/v1/crm/voice/calls/{$call->id}/recording/download");
        $downloadRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('call_recording_access_logs', [
            'tenant_id' => $this->tenant->id,
            'voice_call_id' => $call->id,
            'user_id' => $this->adminUser->id,
            'action' => 'listen',
        ]);

        $this->assertDatabaseHas('call_recording_access_logs', [
            'tenant_id' => $this->tenant->id,
            'voice_call_id' => $call->id,
            'user_id' => $this->adminUser->id,
            'action' => 'download',
        ]);
    }

    /**
     * 12. Test Manager Call Quality Review & Analytics Dashboard.
     */
    public function test_quality_review_and_analytics_dashboard(): void
    {
        Sanctum::actingAs($this->adminUser);

        $call = VoiceCall::create([
            'tenant_id' => $this->tenant->id,
            'assigned_user_id' => $this->repUser->id,
            'from_number' => '+918047192000',
            'to_number' => '+919876543210',
            'direction' => 'outbound',
            'status' => 'completed',
            'duration_seconds' => 240,
        ]);

        // Submit Quality Review
        $reviewRes = $this->postJson("/api/v1/crm/voice/calls/{$call->id}/reviews", [
            'score' => 5,
            'criteria_scores' => [
                'greeting' => 5,
                'politeness' => 5,
                'resolution' => 5,
                'compliance' => 5,
            ],
            'feedback' => 'Flawless communication, polite tone, clear enterprise explanation.',
            'tags' => ['excellent', 'demo_closed'],
        ]);

        $reviewRes->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'score' => 5,
                ],
            ]);

        $this->assertDatabaseHas('call_quality_reviews', [
            'tenant_id' => $this->tenant->id,
            'voice_call_id' => $call->id,
            'score' => 5,
        ]);

        // Call Reports
        $reportRes = $this->getJson('/api/v1/crm/voice/calls/reports');
        $reportRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_calls' => 1,
                        'answered_calls' => 1,
                    ],
                ],
            ]);
    }
}
