<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Support\Models\SupportSlaPolicy;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\SupportTicketCategory;
use App\Domains\Support\Models\SupportTicketMessage;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketHelpdeskTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $supportAgentUser;
    protected User $managerUser;
    protected User $tenantBUser;
    protected Customer $customerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Support Hub',
            'domain' => 'acme-support.test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Apex Cloud',
            'domain' => 'apex-support.test',
            'status' => 'active',
        ]);

        $this->supportAgentUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Support Executive',
            'name' => 'Sam Agent',
            'email' => 'sam.agent@acme-support.test',
        ]);
        $this->supportAgentUser->workspaces()->attach($this->tenantA->id, ['role' => 'Support Executive', 'status' => 'active']);

        $this->managerUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Admin',
            'name' => 'Megan Support Manager',
            'email' => 'megan.mgr@acme-support.test',
        ]);
        $this->managerUser->workspaces()->attach($this->tenantA->id, ['role' => 'Admin', 'status' => 'active']);

        $this->tenantBUser = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'role' => 'Admin',
            'name' => 'Bob Apex',
            'email' => 'bob@apex-support.test',
        ]);
        $this->tenantBUser->workspaces()->attach($this->tenantB->id, ['role' => 'Admin', 'status' => 'active']);

        $this->customerA = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Global Retail Enterprise',
            'email' => 'tech@globalretail.test',
            'phone' => '+14155552671',
        ]);
    }

    public function test_can_create_ticket_with_auto_generated_ticket_number_and_sla_target(): void
    {
        Sanctum::actingAs($this->supportAgentUser);

        $category = SupportTicketCategory::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Billing & Payment Gateways',
        ]);

        SupportSlaPolicy::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Urgent Payment Outage SLA',
            'priority' => 'urgent',
            'response_target_minutes' => 30,
            'resolution_target_minutes' => 120,
            'business_hours_only' => false,
            'is_default' => false,
        ]);

        $payload = [
            'subject' => 'Webhook Payment Confirmation Failing for Razorpay Orders',
            'description' => 'Customers are paying successfully but invoices remain unpaid in CRM.',
            'priority' => 'urgent',
            'customer_id' => $this->customerA->id,
            'category_id' => $category->id,
            'source_channel' => 'web_chat',
            'tags' => ['payments', 'webhook', 'p0'],
        ];

        $res = $this->postJson('/api/v1/crm/tickets', $payload);
        $res->assertStatus(201);
        $res->assertJsonPath('data.priority', 'urgent');
        $res->assertJsonPath('data.status', 'new');

        $ticketNumber = $res->json('data.ticket_number');
        $this->assertNotEmpty($ticketNumber);
        $this->assertStringStartsWith('TICK-', $ticketNumber);

        $ticketId = $res->json('data.id');
        $ticket = SupportTicket::with('messages')->findOrFail($ticketId);

        $this->assertNotNull($ticket->first_response_due_at);
        $this->assertNotNull($ticket->resolution_due_at);
        $this->assertCount(1, $ticket->messages); // Initial customer description created as message
    }

    public function test_can_assign_ticket_to_agent_and_transition_status(): void
    {
        Sanctum::actingAs($this->managerUser);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-TEST-001',
            'subject' => 'Slow response on search endpoint',
            'status' => 'new',
            'priority' => 'medium',
        ]);

        $res = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/assign", [
            'assigned_user_id' => $this->supportAgentUser->id,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('data.status', 'open');
        $res->assertJsonPath('data.assigned_user_id', $this->supportAgentUser->id);

        $this->assertEquals('open', $ticket->fresh()->status);
        $this->assertEquals($this->supportAgentUser->id, $ticket->fresh()->assigned_user_id);
    }

    public function test_internal_note_vs_customer_visible_reply_handling(): void
    {
        Sanctum::actingAs($this->supportAgentUser);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-TEST-002',
            'subject' => 'Need IP whitelisting for SFTP ingest',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        // 1. Post internal note
        $noteRes = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/messages", [
            'message' => 'Checked with devops. IP 203.0.113.42 is already whitelisted on staging.',
            'is_internal' => true,
        ]);
        $noteRes->assertStatus(201);
        $noteRes->assertJsonPath('data.is_internal', true);

        // Internal note does not set first_responded_at
        $this->assertNull($ticket->fresh()->first_responded_at);

        // 2. Post customer-facing public reply
        $replyRes = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/messages", [
            'message' => 'Hello team, the SFTP IP has been whitelisted on our production gateway.',
            'is_internal' => false,
            'status' => 'waiting_customer',
        ]);
        $replyRes->assertStatus(201);
        $replyRes->assertJsonPath('data.is_internal', false);

        // Public reply sets first_responded_at and status
        $this->assertNotNull($ticket->fresh()->first_responded_at);
        $this->assertEquals('waiting_customer', $ticket->fresh()->status);
    }

    public function test_sla_calculation_evaluates_business_hours_and_escalates_on_breach(): void
    {
        Sanctum::actingAs($this->managerUser);

        // Ticket created with due date in the past -> breach
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-BREACH-001',
            'subject' => 'Critical DB Latency Spike',
            'status' => 'open',
            'priority' => 'urgent',
            'first_response_due_at' => Carbon::now()->subMinutes(45),
            'first_responded_at' => null,
            'sla_status' => 'within_sla',
        ]);

        $slaPolicy = SupportSlaPolicy::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Escalation Policy',
            'response_target_minutes' => 30,
            'resolution_target_minutes' => 120,
            'escalate_to_user_id' => $this->managerUser->id,
            'is_default' => true,
        ]);
        $ticket->update(['sla_policy_id' => $slaPolicy->id]);

        // Trigger show or check which evaluates SLA
        $res = $this->getJson("/api/v1/crm/tickets/{$ticket->id}");
        $res->assertStatus(200);

        $fresh = $ticket->fresh();
        $this->assertEquals('breached', $fresh->sla_status);
        $this->assertEquals($this->managerUser->id, $fresh->escalated_to_user_id);
        $this->assertNotNull($fresh->escalated_at);
    }

    public function test_can_generate_ai_assistance_summary_and_draft_reply(): void
    {
        Sanctum::actingAs($this->supportAgentUser);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-AI-001',
            'customer_id' => $this->customerA->id,
            'subject' => 'Cannot reset password with Google SSO 2FA',
            'description' => 'I tried logging in via OAuth but the screen loops back to 2FA PIN screen.',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $res = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/ai-assistance", [
            'context' => 'Customer is on Chrome 120 on Mac OS.',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('data.ticket_id', $ticket->id);
        $this->assertNotEmpty($res->json('data.summary'));
        $this->assertNotEmpty($res->json('data.draft_reply'));
        $this->assertNotEmpty($res->json('data.troubleshooting_steps'));

        $fresh = $ticket->fresh();
        $this->assertNotNull($fresh->ai_reply_draft);
        $this->assertNotNull($fresh->ai_summary);
    }

    public function test_ai_reply_is_strictly_draft_by_default(): void
    {
        Sanctum::actingAs($this->supportAgentUser);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-DRAFT-001',
            'subject' => 'Query regarding invoice VAT breakdown',
            'description' => 'Does this invoice include 18% GST?',
            'status' => 'open',
            'ai_reply_draft' => 'Based on our tax policy, all Indian domestic invoices include 18% IGST or CGST/SGST.',
        ]);

        // By default, no public message has been sent to customer
        $this->assertEquals(0, $ticket->messages()->where('is_internal', false)->count());

        // Agent reviews and posts message explicitly
        $sendRes = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/messages", [
            'message' => $ticket->ai_reply_draft,
            'is_internal' => false,
            'is_ai_generated' => true,
        ]);

        $sendRes->assertStatus(201);
        $this->assertEquals(1, $ticket->messages()->where('is_internal', false)->count());
    }

    public function test_can_resolve_and_reopen_ticket_with_audit_trail(): void
    {
        Sanctum::actingAs($this->supportAgentUser);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-LIFECYCLE-001',
            'subject' => 'CSV Export Encoding Error in Excel',
            'status' => 'in_progress',
        ]);

        // 1. Resolve
        $resolveRes = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/resolve", [
            'resolution_notes' => 'Added UTF-8 BOM byte sequence to CSV stream for Excel UTF-8 compatibility.',
        ]);
        $resolveRes->assertStatus(200);
        $resolveRes->assertJsonPath('data.status', 'resolved');
        $this->assertEquals('resolved', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        // 2. Customer reopens
        $reopenRes = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/reopen", [
            'reopen_reason' => 'French accent characters are still displaying improperly in Excel 2016.',
        ]);
        $reopenRes->assertStatus(200);
        $reopenRes->assertJsonPath('data.status', 'open');
        $this->assertEquals(1, $ticket->fresh()->reopen_count);
        $this->assertNotNull($ticket->fresh()->reopened_at);

        // 3. Final closure
        $closeRes = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/close", [
            'closure_reason' => 'Customer confirmed fix after testing on Mac and Windows.',
        ]);
        $closeRes->assertStatus(200);
        $closeRes->assertJsonPath('data.status', 'closed');
        $this->assertNotNull($ticket->fresh()->closed_at);
    }

    public function test_can_record_customer_satisfaction_csat_feedback(): void
    {
        Sanctum::actingAs($this->supportAgentUser);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-CSAT-001',
            'subject' => 'API Rate Limit Increase Request',
            'status' => 'resolved',
        ]);

        $csatRes = $this->postJson("/api/v1/crm/tickets/{$ticket->id}/csat", [
            'rating' => 5,
            'comment' => 'Fastest resolution time we have experienced. Support engineer was exceptionally helpful!',
        ]);

        $csatRes->assertStatus(200);
        $csatRes->assertJsonPath('data.csat_rating', 5);
        $this->assertEquals(5, $ticket->fresh()->csat_rating);
    }

    public function test_strictly_enforces_cross_tenant_isolation_on_tickets_and_messages(): void
    {
        $ticketA = SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TICK-SECRET-001',
            'subject' => 'Confidential Acquisition Security Inquiry',
            'status' => 'open',
        ]);

        SupportTicketMessage::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_id' => $ticketA->id,
            'message' => 'Internal investigation details for Acme only.',
            'is_internal' => true,
        ]);

        // Login as Tenant B user
        Sanctum::actingAs($this->tenantBUser);

        // Tenant B cannot view Tenant A ticket
        $this->getJson("/api/v1/crm/tickets/{$ticketA->id}")->assertStatus(404);

        // Tenant B cannot post message to Tenant A ticket
        $this->postJson("/api/v1/crm/tickets/{$ticketA->id}/messages", [
            'message' => 'Intruder message',
        ])->assertStatus(404);

        // Listing tickets returns empty for Tenant B
        $listRes = $this->getJson('/api/v1/crm/tickets');
        $listRes->assertStatus(200);
        $this->assertEmpty($listRes->json('data'));
    }
}
