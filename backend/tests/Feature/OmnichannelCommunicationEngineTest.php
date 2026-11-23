<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\SalesTeam;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Models\CustomerCommunicationConsent;
use App\Domains\Omnichannel\Models\OmnichannelConversation;
use App\Domains\Omnichannel\Models\OmnichannelQuickReply;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmnichannelCommunicationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $agent1;
    protected User $agent2;
    protected User $userB;
    protected SalesTeam $supportTeam;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Nexus Omnichannel Enterprise',
            'slug' => 'nexus-omnichannel',
            'subscription_tier' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Apex Competitor',
            'slug' => 'apex-competitor',
            'subscription_tier' => 'professional',
        ]);

        $this->agent1 = User::create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Agent Clark',
            'email' => 'clark@nexus.com',
            'password' => bcrypt('Password123!'),
            'role' => 'agent',
        ]);
        $this->agent1->workspaces()->attach($this->tenantA->id, ['role' => 'agent', 'status' => 'active']);

        $this->agent2 = User::create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Agent Diana',
            'email' => 'diana@nexus.com',
            'password' => bcrypt('Password123!'),
            'role' => 'agent',
        ]);
        $this->agent2->workspaces()->attach($this->tenantA->id, ['role' => 'agent', 'status' => 'active']);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'name' => 'Bob Rival',
            'email' => 'bob@apex.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'admin', 'status' => 'active']);

        $this->supportTeam = SalesTeam::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tier 1 Global Support',
        ]);

        $this->tokenA = $this->agent1->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    public function test_it_unifies_all_channels_in_single_inbox_with_filtering(): void
    {
        $this->actingAs($this->agent1, 'sanctum');

        $channels = ['whatsapp', 'facebook', 'instagram', 'email', 'sms', 'webchat', 'call'];

        foreach ($channels as $idx => $ch) {
            OmnichannelConversation::create([
                'tenant_id' => $this->tenantA->id,
                'channel' => $ch,
                'phone_number' => '+1555100' . $idx,
                'customer_email' => "user{$idx}@example.com",
                'customer_name' => "Customer {$ch}",
                'status' => 'open',
                'priority' => 'medium',
                'last_message_at' => Carbon::now()->subMinutes($idx * 5),
            ]);
        }

        // Fetch all conversations
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/conversations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.all', 7)
            ->assertJsonPath('stats.whatsapp', 1)
            ->assertJsonPath('stats.facebook', 1)
            ->assertJsonPath('stats.instagram', 1)
            ->assertJsonPath('stats.email', 1)
            ->assertJsonPath('stats.sms', 1)
            ->assertJsonPath('stats.webchat', 1)
            ->assertJsonPath('stats.call', 1);

        // Filter specifically by Facebook Messenger
        $fbResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/conversations?channel=facebook');

        $fbResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel', 'facebook');

        // Filter specifically by Instagram DM
        $igResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/conversations?channel=instagram');

        $igResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel', 'instagram');
    }

    public function test_it_handles_manual_and_workload_aware_round_robin_assignment(): void
    {
        $this->actingAs($this->agent1, 'sanctum');

        $conversation = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+15559876543',
            'customer_name' => 'Bruce Wayne',
            'status' => 'open',
        ]);

        // 1. Manual Assignment with Reason
        $assignResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/assign", [
                'assigned_user_id' => $this->agent1->id,
                'assigned_team_id' => $this->supportTeam->id,
                'routing_method' => 'manual',
                'reason' => 'VIP Account Direct Routing',
            ]);

        $assignResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_user_id', $this->agent1->id)
            ->assertJsonPath('data.assigned_team_id', $this->supportTeam->id);

        $this->assertDatabaseHas('conversation_assignment_logs', [
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $conversation->id,
            'assigned_to_user_id' => $this->agent1->id,
            'routing_method' => 'manual',
        ]);

        // 2. Create another conversation and test Auto-Assign Round Robin
        // Agent 1 currently has 1 open conversation, Agent 2 has 0 -> Round Robin should pick Agent 2
        $conv2 = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'webchat',
            'phone_number' => 'web_session_999',
            'customer_name' => 'Barry Allen',
            'status' => 'open',
        ]);

        $autoAssignRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conv2->id}/auto-assign");

        $autoAssignRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_user_id', $this->agent2->id);

        // Verify assignment history endpoint
        $historyRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/crm/inbox/conversations/{$conv2->id}/assignment-history");

        $historyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.routing_method', 'round_robin')
            ->assertJsonPath('data.0.assigned_to_user_id', $this->agent2->id);
    }

    public function test_it_tracks_first_response_time_and_resolution_sla(): void
    {
        $this->actingAs($this->agent1, 'sanctum');

        $conversation = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+15550192834',
            'customer_name' => 'Arthur Curry',
            'status' => 'open',
        ]);
        $conversation->timestamps = false;
        $conversation->created_at = Carbon::now()->subMinutes(15);
        $conversation->save();

        $this->assertNull($conversation->first_response_at);

        // Agent replies
        $replyResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/messages", [
                'content' => 'Hello Arthur, thanks for waiting. How can we help?',
                'sender_actor' => 'human',
            ]);

        $replyResponse->assertStatus(200);

        $fresh = $conversation->fresh();
        $this->assertNotNull($fresh->first_response_at);
        $this->assertGreaterThanOrEqual(900, $fresh->first_response_time_seconds); // >= 15 minutes (900s)

        // Resolve conversation
        $resolveResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->patchJson("/api/v1/crm/inbox/conversations/{$conversation->id}/status", [
                'status' => 'resolved',
            ]);

        $resolveResponse->assertStatus(200);
        $freshResolved = $conversation->fresh();
        $this->assertEquals('resolved', $freshResolved->status);
        $this->assertNotNull($freshResolved->resolved_at);
        $this->assertGreaterThan(0, $freshResolved->resolution_time_seconds);
    }

    public function test_it_enforces_channel_communication_consent_and_suppression(): void
    {
        $this->actingAs($this->agent1, 'sanctum');

        $conversation = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'sms',
            'phone_number' => '+15554443322',
            'customer_name' => 'Victor Stone',
            'status' => 'open',
        ]);

        // Record customer opt-out on SMS
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/crm/inbox/consents', [
                'destination' => '+15554443322',
                'channel' => 'sms',
                'consent_status' => 'opted_out',
                'consent_source' => 'verbal',
            ])->assertStatus(200);

        // Outbound send to opted-out destination fails with 422
        $blockedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/messages", [
                'content' => 'Promotional SMS discount code',
                'message_classification' => 'marketing',
            ]);

        $blockedResponse->assertStatus(422)
            ->assertJsonPath('success', false);

        // Opt back in and re-verify send capability
        $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/crm/inbox/consents', [
                'destination' => '+15554443322',
                'channel' => 'sms',
                'consent_status' => 'opted_in',
                'consent_source' => 'form',
            ])->assertStatus(200);

        // Outbound send now succeeds
        $allowedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/messages", [
                'content' => 'Welcome back! Here is your account update.',
            ]);

        $allowedResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_it_aggregates_cross_channel_customer_timeline(): void
    {
        $this->actingAs($this->agent1, 'sanctum');

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Oliver Queen',
            'email' => 'oliver@queen.io',
            'phone' => '+15557778899',
            'status' => 'qualified',
            'source' => 'instagram',
        ]);

        // 1. Instagram message
        $igConv = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'instagram',
            'lead_id' => $lead->id,
            'phone_number' => 'ig_oliver_queen',
            'customer_name' => 'Oliver Queen',
            'status' => 'open',
        ]);
        $igMsg = WhatsAppMessage::create([
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $igConv->id,
            'channel' => 'instagram',
            'direction' => 'inbound',
            'content' => 'Inquiry from Instagram',
            'sender_actor' => 'customer',
            'delivery_status' => 'delivered',
        ]);
        $igMsg->timestamps = false;
        $igMsg->created_at = Carbon::now()->subHours(4);
        $igMsg->save();

        // 2. Outgoing Voice Call with recording and AI summary
        $call = VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'call_id' => 'call_oliver_100',
            'direction' => 'outbound',
            'from_number' => '+18005550000',
            'to_number' => '+15557778899',
            'status' => 'completed',
            'duration_seconds' => 185,
            'cost_amount' => 0.06,
            'cost_currency' => 'USD',
            'human_transferred' => false,
            'ai_summary' => 'Customer confirmed requirements for 50 enterprise seats.',
            'recording_url' => 'https://storage.nexus.test/calls/rec_100.mp3',
        ]);
        $call->timestamps = false;
        $call->created_at = Carbon::now()->subHours(2);
        $call->save();

        // 3. Email message
        $emailConv = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'email',
            'lead_id' => $lead->id,
            'customer_email' => 'oliver@queen.io',
            'customer_name' => 'Oliver Queen',
            'status' => 'open',
        ]);
        $emailMsg = WhatsAppMessage::create([
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $emailConv->id,
            'channel' => 'email',
            'direction' => 'outbound',
            'subject' => 'Enterprise Proposal Q4',
            'content' => 'Attached is your enterprise proposal.',
            'sender_actor' => 'human',
            'delivery_status' => 'sent',
        ]);
        $emailMsg->timestamps = false;
        $emailMsg->created_at = Carbon::now()->subHour();
        $emailMsg->save();

        // Query unified timeline
        $timelineRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/crm/inbox/timeline?lead_id={$lead->id}");

        $timelineRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $timelineRes->json('data');
        $this->assertCount(3, $items);

        // Verify chronological order (most recent first)
        $this->assertEquals('email', $items[0]['channel']);
        $this->assertEquals('call', $items[1]['channel']);
        $this->assertEquals('instagram', $items[2]['channel']);
        $this->assertStringContainsString('50 enterprise seats', $items[1]['content']);
    }

    public function test_it_manages_quick_replies_canned_responses(): void
    {
        $this->actingAs($this->agent1, 'sanctum');

        // Create Quick Reply
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/crm/inbox/quick-replies', [
                'shortcut' => '/pricing',
                'title' => 'Standard Enterprise Pricing',
                'channel' => 'all',
                'content' => 'Our enterprise subscription starts at $99/seat/mo with unlimited WhatsApp automation.',
                'tags' => ['pricing', 'sales'],
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.shortcut', '/pricing');

        $this->assertDatabaseHas('omnichannel_quick_replies', [
            'tenant_id' => $this->tenantA->id,
            'shortcut' => '/pricing',
        ]);

        // List Quick Replies
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/quick-replies');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_it_calculates_omnichannel_analytics_from_real_data(): void
    {
        $this->actingAs($this->agent1, 'sanctum');

        // Seed sample conversations on various channels
        OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+1555111',
            'status' => 'open',
            'first_response_time_seconds' => 120, // 2 mins
        ]);

        OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'instagram',
            'phone_number' => 'ig_user_1',
            'status' => 'resolved',
            'first_response_time_seconds' => 240, // 4 mins
            'resolution_time_seconds' => 600, // 10 mins
        ]);

        // Seed voice call
        VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'call_id' => 'analytics_call_1',
            'direction' => 'inbound',
            'from_number' => '+1555222',
            'to_number' => '+1800555',
            'status' => 'completed',
            'duration_seconds' => 120,
            'cost_amount' => 0.04,
            'cost_currency' => 'USD',
            'human_transferred' => false,
        ]);

        $analyticsRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/analytics');

        $analyticsRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_conversations', 2)
            ->assertJsonPath('data.conversations_by_channel.whatsapp', 1)
            ->assertJsonPath('data.conversations_by_channel.instagram', 1)
            ->assertJsonPath('data.first_response_time.average_seconds', 180)
            ->assertJsonPath('data.voice_calls_overview.total_calls', 1)
            ->assertJsonPath('data.voice_calls_overview.completed', 1);
    }

    public function test_it_strictly_enforces_cross_tenant_isolation_on_conversations(): void
    {
        // Tenant A creates conversation
        $convA = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+15559990001',
            'customer_name' => 'Acme Secret Client',
            'status' => 'open',
        ]);

        // User B from Tenant B cannot view Tenant A conversation
        $this->actingAs($this->userB, 'sanctum');

        $viewRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson("/api/v1/crm/inbox/conversations/{$convA->id}");
        $viewRes->assertStatus(404);

        // User B cannot send message to Tenant A conversation
        $sendRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->postJson("/api/v1/crm/inbox/conversations/{$convA->id}/messages", [
                'content' => 'Malicious cross tenant reply',
            ]);
        $sendRes->assertStatus(404);

        // User B listing does not leak Tenant A conversation
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson('/api/v1/crm/inbox/conversations');
        $listRes->assertStatus(200)
            ->assertJsonMissing(['phone_number' => '+15559990001']);
    }
}
