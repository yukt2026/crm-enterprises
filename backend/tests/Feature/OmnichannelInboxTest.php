<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\SalesTeam;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Models\CommunicationSuppression;
use App\Domains\Omnichannel\Models\EmailAccount;
use App\Domains\Omnichannel\Models\IdentityMergePending;
use App\Domains\Omnichannel\Models\OmnichannelConversation;
use App\Domains\Omnichannel\Models\SmsConfiguration;
use App\Domains\Omnichannel\Models\WebsiteChatWidget;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmnichannelInboxTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Omnichannel Corp',
            'slug' => 'acme-omnichannel',
            'subscription_tier' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Rival Logistics',
            'slug' => 'rival-logistics',
            'subscription_tier' => 'professional',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Alice Support',
            'email' => 'alice@acme.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'Manager',
        ]);
        $this->userA->workspaces()->attach($this->tenantA->id, ['role' => 'Manager', 'status' => 'active']);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'name' => 'Bob Rival',
            'email' => 'bob@rival.com',
            'password' => bcrypt('Secret123!'),
            'role' => 'Manager',
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'Manager', 'status' => 'active']);

        $this->tokenA = $this->userA->createToken('test_token')->plainTextToken;
    }

    /** @test */
    public function it_supports_channel_filtering_and_counts()
    {
        // Create conversations on different channels for Tenant A
        OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919876543210',
            'customer_name' => 'Rohan Sharma',
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => now(),
        ]);

        OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'email',
            'customer_email' => 'contact@enterprises.org',
            'customer_name' => 'Corporate Client',
            'phone_number' => 'N/A',
            'subject' => 'Enterprise SLA Inquiry',
            'status' => 'open',
            'unread_count' => 2,
            'last_message_at' => now(),
        ]);

        OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'webchat',
            'channel_account_id' => 'vis_999888',
            'customer_name' => 'Website Guest',
            'phone_number' => 'N/A',
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => now(),
        ]);

        // Filter by email
        $resEmail = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/conversations?channel=email');

        $resEmail->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel', 'email')
            ->assertJsonPath('stats.email', 1)
            ->assertJsonPath('stats.whatsapp', 1)
            ->assertJsonPath('stats.webchat', 1)
            ->assertJsonPath('stats.all', 3);

        // Filter by all
        $resAll = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/conversations?channel=all');

        $resAll->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function it_handles_email_sending_and_threading()
    {
        $emailAccount = EmailAccount::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Support Desk',
            'email_address' => 'help@acme.com',
            'provider' => 'smtp_imap',
            'is_active' => true,
            'is_default' => true,
            'signature_html' => '<p>Regards,<br>Acme Support</p>',
        ]);

        $conversation = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'email',
            'customer_email' => 'client@clientdomain.com',
            'customer_name' => 'Vikram Patel',
            'phone_number' => 'N/A',
            'subject' => 'Contract Renewal 2026',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        // Seed initial inbound message
        $inbound = WhatsAppMessage::create([
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $conversation->id,
            'channel' => 'email',
            'provider_message_id' => '<orig123@clientdomain.com>',
            'direction' => 'inbound',
            'message_type' => 'email',
            'subject' => 'Contract Renewal 2026',
            'sender_actor' => 'customer',
            'sender_identity' => 'client@clientdomain.com',
            'recipient_identity' => 'help@acme.com',
            'content' => 'Please send the revised proposal.',
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
        ]);

        // Send reply via API
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/messages", [
                'content' => 'Here is the revised proposal with SLA amendments.',
                'subject' => 'Re: Contract Renewal 2026',
                'attachments' => [
                    ['name' => 'Proposal_2026.pdf', 'url' => 'https://cdn.acme.com/proposal.pdf', 'size' => 102400],
                ],
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.direction', 'outbound')
            ->assertJsonPath('data.in_reply_to', '<orig123@clientdomain.com>')
            ->assertJsonPath('data.recipient_identity', 'client@clientdomain.com');

        $this->assertDatabaseHas('whatsapp_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'in_reply_to' => '<orig123@clientdomain.com>',
        ]);
    }

    /** @test */
    public function it_enforces_communication_suppression_on_sending()
    {
        // Add recipient to suppression list
        CommunicationSuppression::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'email',
            'identifier' => 'unsubscribed@domain.com',
            'reason' => 'unsubscribe',
            'suppressed_at' => now(),
        ]);

        $conversation = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'email',
            'customer_email' => 'unsubscribed@domain.com',
            'phone_number' => 'N/A',
            'status' => 'open',
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/messages", [
                'content' => 'Promotional offer!',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function it_supports_assignment_and_team_routing()
    {
        $team = SalesTeam::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise Escalations',
            'leader_id' => $this->userA->id,
        ]);

        $conversation = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919111222333',
            'customer_name' => 'Pooja Verma',
            'status' => 'open',
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/conversations/{$conversation->id}/assign", [
                'assigned_user_id' => $this->userA->id,
                'assigned_team_id' => $team->id,
            ]);

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.assigned_user_id', $this->userA->id)
            ->assertJsonPath('data.assigned_team_id', $team->id);

        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => $conversation->id,
            'assigned_user_id' => $this->userA->id,
            'assigned_team_id' => $team->id,
        ]);

        // CrmActivity timeline entry logged
        $this->assertDatabaseHas('crm_activities', [
            'tenant_id' => $this->tenantA->id,
            'activity_type' => 'conversation_assigned',
        ]);
    }

    /** @test */
    public function it_keeps_uncertain_customer_matches_pending_review_without_automatic_merging()
    {
        // Lead 1: Rajesh Kumar, phone +919811111111, email rajesh1@test.com
        $lead1 = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Rajesh Kumar',
            'phone' => '+919811111111',
            'email' => 'rajesh1@test.com',
            'status' => 'new',
        ]);

        // Lead 2 with identical name but different phone and email!
        $lead2 = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Rajesh Kumar',
            'phone' => '+919822222222',
            'email' => 'rajesh2@other.com',
            'status' => 'new',
        ]);

        $identityService = app(\App\Domains\Omnichannel\Services\IdentityResolutionService::class);
        $pending = $identityService->evaluateAmbiguousMatches(
            $this->tenantA->id,
            'lead',
            $lead1->id,
            'Rajesh Kumar',
            $lead1->email,
            $lead1->phone
        );

        $this->assertNotNull($pending);
        $this->assertEquals('pending_review', $pending->status);

        // Fetch pending list via API
        $resList = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson('/api/v1/crm/inbox/identity/pending');

        $resList->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        // Review and approve merge
        $resReview = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/inbox/identity/pending/{$pending->id}/review", [
                'decision' => 'merged',
                'notes' => 'Audited: Both phone numbers belong to the same person.',
            ]);

        $resReview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'merged');
    }

    /** @test */
    public function it_handles_public_webchat_lead_capture_and_transcript()
    {
        $widget = WebsiteChatWidget::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Main Landing Chat',
            'widget_key' => 'widget_test_1234567890',
            'brand_title' => 'Nexus Concierge',
            'brand_color' => '#6366f1',
            'welcome_message' => 'Welcome! Ask us anything.',
            'is_active' => true,
        ]);

        // 1. Init widget
        $resInit = $this->getJson('/api/v1/public/webchat/init?widget_key=' . $widget->widget_key);
        $resInit->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.brand_title', 'Nexus Concierge');

        // 2. Send visitor message with pre-chat lead info
        $resSend = $this->postJson('/api/v1/public/webchat/send', [
            'widget_key' => $widget->widget_key,
            'visitor_id' => 'vis_alpha_99',
            'name' => 'Sneha Sen',
            'email' => 'sneha@fintech.io',
            'phone' => '+919877700011',
            'requirement' => 'Looking for Enterprise CRM quote',
            'content' => 'Hello, can you share the enterprise pricing plan?',
        ]);

        $resSend->assertOk()
            ->assertJsonPath('success', true);

        // Verify CRM Lead was automatically captured
        $this->assertDatabaseHas('leads', [
            'tenant_id' => $this->tenantA->id,
            'email' => 'sneha@fintech.io',
            'name' => 'Sneha Sen',
            'source' => 'Website Live Chat',
        ]);

        // 3. Retrieve visitor transcript
        $resMessages = $this->getJson("/api/v1/public/webchat/messages?widget_key={$widget->widget_key}&visitor_id=vis_alpha_99");
        $resMessages->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function it_aggregates_unified_timeline_across_messages_calls_and_activities()
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Manish Gupta',
            'phone' => '+919988776655',
            'status' => 'qualified',
        ]);

        $conversation = OmnichannelConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919988776655',
            'lead_id' => $lead->id,
            'status' => 'open',
        ]);

        WhatsAppMessage::create([
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $conversation->id,
            'channel' => 'whatsapp',
            'direction' => 'inbound',
            'message_type' => 'text',
            'sender_actor' => 'customer',
            'content' => 'Interested in the CRM enterprise tier.',
            'created_at' => now()->subHours(2),
        ]);

        \App\Domains\Voice\Models\VoiceCall::create([
            'tenant_id' => $this->tenantA->id,
            'lead_id' => $lead->id,
            'direction' => 'outbound',
            'from_number' => '+918047190000',
            'to_number' => '+919988776655',
            'duration_seconds' => 140,
            'status' => 'completed',
            'disposition' => 'interested',
            'qualification_result' => 'qualified',
            'ai_summary' => 'Customer confirmed budget ₹150,000 for sales automation.',
            'cost_amount' => 0.05,
            'cost_currency' => 'USD',
            'human_transferred' => false,
            'created_at' => now()->subHour(),
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/crm/inbox/timeline?lead_id={$lead->id}");

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data'); // 1 message + 1 call
    }

    /** @test */
    public function it_strictly_enforces_cross_tenant_isolation_on_conversations()
    {
        // Conversation in Tenant B
        $convB = OmnichannelConversation::create([
            'tenant_id' => $this->tenantB->id,
            'channel' => 'email',
            'customer_email' => 'confidential@rival.com',
            'phone_number' => 'N/A',
            'status' => 'open',
        ]);

        // Tenant A tries to access Tenant B's conversation
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->getJson("/api/v1/crm/inbox/conversations/{$convB->id}");

        $res->assertNotFound();
    }
}
