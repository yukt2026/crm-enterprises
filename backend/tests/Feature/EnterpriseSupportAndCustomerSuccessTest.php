<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Support\Models\CustomerFeedback;
use App\Domains\Support\Models\CustomerHealthScore;
use App\Domains\Support\Models\KnowledgeArticle;
use App\Domains\Support\Models\KnowledgeCategory;
use App\Domains\Support\Models\SupportSlaPolicy;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Models\WarrantyContract;
use App\Domains\Support\Services\CustomerSuccessService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseSupportAndCustomerSuccessTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Zenith Tech Enterprises',
            'slug' => 'zenith-tech',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'role' => 'Business Admin',
        ]);

        $this->user->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
            'status' => 'active',
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Global Corp',
            'email' => 'support@acmeglobal.com',
            'phone' => '+919876543210',
            'status' => 'active',
        ]);

        SupportSlaPolicy::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise Priority SLA',
            'priority' => 'high',
            'response_target_minutes' => 30,
            'resolution_target_minutes' => 240,
            'is_default' => true,
        ]);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_ticket_creation_with_sla_and_omnichannel_source(): void
    {
        $res = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/tickets', [
            'customer_id' => $this->customer->id,
            'subject' => 'API Gateway Webhook Timeout Incident',
            'description' => 'Our production payment webhook is timing out with HTTP 504.',
            'priority' => 'high',
            'source_channel' => 'whatsapp',
        ]);

        $res->assertStatus(201);
        $res->assertJsonPath('data.subject', 'API Gateway Webhook Timeout Incident');
        $res->assertJsonPath('data.source_channel', 'whatsapp');
        $this->assertNotEmpty($res->json('data.ticket_number'));
        $this->assertNotEmpty($res->json('data.first_response_due_at'));
    }

    public function test_ticket_status_lifecycle_transitions_and_audit_history(): void
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-2026-TEST1',
            'customer_id' => $this->customer->id,
            'subject' => 'Database Sync Latency',
            'status' => 'new',
            'priority' => 'medium',
        ]);

        // 1. Resolve ticket
        $res = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/tickets/{$ticket->id}/resolve", [
            'resolution_notes' => 'Optimized database index on customer_id column.',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('data.status', 'resolved');
        $this->assertNotNull($res->json('data.resolved_at'));

        // 2. Transition history verification
        $histRes = $this->withHeaders($this->authHeaders())->getJson("/api/v1/crm/tickets/{$ticket->id}/transitions");
        $histRes->assertStatus(200);
        $this->assertNotEmpty($histRes->json('data'));
        $this->assertEquals('resolved', $histRes->json('data.0.to_status'));

        // 3. Reopen ticket
        $reopenRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/tickets/{$ticket->id}/reopen", [
            'reopen_reason' => 'Issue recurred during peak morning batch.',
        ]);
        $reopenRes->assertStatus(200);
        $reopenRes->assertJsonPath('data.status', 'reopened');
        $this->assertEquals(1, $reopenRes->json('data.reopen_count'));
    }

    public function test_customer_reply_auto_reopens_completed_ticket(): void
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-2026-TEST2',
            'customer_id' => $this->customer->id,
            'subject' => 'SSL Certificate Expiry Warning',
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        // Customer sends reply message
        $msgRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/tickets/{$ticket->id}/messages", [
            'message' => 'Still seeing certificate mismatch error on mobile browsers.',
            'is_internal' => false,
            'sender_type' => 'customer',
        ]);

        $msgRes->assertStatus(201);
        $ticket->refresh();
        $this->assertEquals('reopened', $ticket->status);
        $this->assertGreaterThanOrEqual(1, $ticket->reopen_count);
    }

    public function test_internal_notes_vs_customer_replies(): void
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-2026-TEST3',
            'customer_id' => $this->customer->id,
            'subject' => 'Invoice Line Item Clarification',
            'status' => 'open',
        ]);

        // 1. Internal Note
        $intRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/tickets/{$ticket->id}/messages", [
            'message' => 'Consulted with accounts team; client was billed for extra storage add-on.',
            'is_internal' => true,
        ]);
        $intRes->assertStatus(201);
        $this->assertTrue($intRes->json('data.is_internal'));

        // 2. Customer-visible reply
        $pubRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/tickets/{$ticket->id}/messages", [
            'message' => 'Hello, the charge is for 500GB additional cloud storage requested last month.',
            'is_internal' => false,
            'status' => 'waiting_customer',
        ]);
        $pubRes->assertStatus(201);
        $this->assertFalse($pubRes->json('data.is_internal'));

        $ticket->refresh();
        $this->assertEquals('waiting_customer', $ticket->status);
    }

    public function test_bulk_ticket_operations(): void
    {
        $t1 = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-BULK-1',
            'customer_id' => $this->customer->id,
            'subject' => 'Bulk Test 1',
            'status' => 'new',
            'priority' => 'low',
        ]);
        $t2 = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-BULK-2',
            'customer_id' => $this->customer->id,
            'subject' => 'Bulk Test 2',
            'status' => 'new',
            'priority' => 'low',
        ]);

        $res = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/tickets/bulk-update', [
            'ticket_ids' => [$t1->id, $t2->id],
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_user_id' => $this->user->id,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('data.updated_count', 2);

        $t1->refresh();
        $this->assertEquals('in_progress', $t1->status);
        $this->assertEquals('high', $t1->priority);
        $this->assertEquals($this->user->id, $t1->assigned_user_id);
    }

    public function test_csat_and_nps_customer_feedback(): void
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'ticket_number' => 'TICK-FEEDBACK-1',
            'customer_id' => $this->customer->id,
            'subject' => 'Server Provisioning Request',
            'status' => 'resolved',
        ]);

        // Submit CSAT
        $csatRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/tickets/{$ticket->id}/csat", [
            'rating' => 5,
            'comment' => 'Outstanding response speed and flawless resolution!',
        ]);

        $csatRes->assertStatus(200);
        $csatRes->assertJsonPath('data.csat_rating', 5);

        // Feedback record verification
        $this->assertDatabaseHas('customer_feedback', [
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $ticket->id,
            'customer_id' => $this->customer->id,
            'csat_score' => 5,
        ]);
    }

    public function test_customer_success_health_score_with_explainable_factors(): void
    {
        $service = app(CustomerSuccessService::class);
        $healthScore = $service->calculateCustomerHealthScore($this->tenant->id, $this->customer->id);

        $this->assertNotNull($healthScore->health_score);
        $this->assertContains($healthScore->health_status, ['healthy', 'neutral', 'at_risk', 'critical']);
        $this->assertContains($healthScore->churn_risk_level, ['low', 'medium', 'high']);
        $this->assertArrayHasKey('ticket_support', $healthScore->factors);
        $this->assertArrayHasKey('customer_satisfaction', $healthScore->factors);
        $this->assertArrayHasKey('financial_health', $healthScore->factors);
        $this->assertArrayHasKey('onboarding_engagement', $healthScore->factors);
        $this->assertNotEmpty($healthScore->factors['summary_narrative']);

        // Check API endpoint
        $res = $this->withHeaders($this->authHeaders())->getJson("/api/v1/crm/customer-success/health-scores/{$this->customer->id}");
        $res->assertStatus(200);
        $res->assertJsonPath('data.customer_id', $this->customer->id);
    }

    public function test_warranty_and_amc_contracts_with_maintenance_schedule(): void
    {
        // 1. Create Warranty/AMC Contract
        $createRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/customer-success/contracts', [
            'customer_id' => $this->customer->id,
            'contract_type' => 'amc',
            'product_name' => 'Enterprise Server Cluster High-Availability Support',
            'renewal_price' => 75000,
            'preventive_maintenance_interval_days' => 90,
            'coverage_details' => '24/7 Phone Support, 4-hour SLA on critical hardware defects.',
        ]);

        $createRes->assertStatus(201);
        $contractId = $createRes->json('data.id');
        $this->assertNotEmpty($createRes->json('data.contract_number'));
        $this->assertNotEmpty($createRes->json('data.next_service_date'));

        // 2. Log Preventive Maintenance
        $maintRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/customer-success/contracts/{$contractId}/maintenance", [
            'notes' => 'Q1 Preventive check completed: Firmware upgraded, disk RAID health optimal.',
        ]);

        $maintRes->assertStatus(200);
        $maintRes->assertJsonPath('data.last_service_date', now()->toDateString());
    }

    public function test_knowledge_base_articles_and_ai_grounded_answer(): void
    {
        // 1. Categories
        $catRes = $this->withHeaders($this->authHeaders())->getJson('/api/v1/crm/knowledge-base/categories');
        $catRes->assertStatus(200);
        $this->assertNotEmpty($catRes->json('data'));

        // 2. Create Article
        $artRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/knowledge-base/articles', [
            'title' => 'How to Reconcile Bank Statements with Razorpay Gateway',
            'content' => 'Navigate to Finance > Reconciliation. Download the Razorpay monthly settlement payout CSV and upload to the transaction register.',
            'excerpt' => 'Step-by-step instructions for financial transaction reconciliation.',
            'tags' => ['finance', 'reconciliation', 'razorpay'],
            'visibility' => 'public',
        ]);

        $artRes->assertStatus(201);
        $articleId = $artRes->json('data.id');

        // 3. Vote helpful
        $voteRes = $this->withHeaders($this->authHeaders())->postJson("/api/v1/crm/knowledge-base/articles/{$articleId}/vote", [
            'helpful' => true,
        ]);
        $voteRes->assertStatus(200);
        $this->assertEquals(1, $voteRes->json('data.helpful_count'));

        // 4. AI suggested answer from Knowledge Base
        $aiRes = $this->withHeaders($this->authHeaders())->postJson('/api/v1/crm/knowledge-base/ai-suggest', [
            'query' => 'reconcile Razorpay gateway bank settlement',
        ]);
        $aiRes->assertStatus(200);
        $this->assertNotEmpty($aiRes->json('data.suggested_answer'));
        $this->assertGreaterThan(0.5, $aiRes->json('data.confidence_score'));
    }
}
