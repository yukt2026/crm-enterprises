<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Branch;
use App\Domains\Finance\Models\Invoice;
use App\Domains\HR\Models\Department;
use App\Domains\Omnichannel\Models\OmnichannelConversation;
use App\Domains\Omnichannel\Models\OmnichannelRoutingRule;
use App\Domains\Omnichannel\Models\OmnichannelSlaPolicy;
use App\Domains\Omnichannel\Models\TelegramAccount;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OmnichannelCustomerCommunicationHubTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant1;
    protected Tenant $tenant2;
    protected User $owner1;
    protected User $agent1;
    protected User $agent2;
    protected User $accountant1;
    protected User $tenant2User;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->tenant1 = Tenant::create([
            'name' => 'Omni Enterprise 1',
            'domain' => 'omni1.com',
            'database' => 'crm_test_omni1',
            'is_active' => true,
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'Omni Enterprise 2',
            'domain' => 'omni2.com',
            'database' => 'crm_test_omni2',
            'is_active' => true,
        ]);

        $this->owner1 = User::create([
            'tenant_id' => $this->tenant1->id,
            'current_tenant_id' => $this->tenant1->id,
            'name' => 'Owner Alice',
            'email' => 'alice@omni1.com',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'Business Owner',
            'is_active' => true,
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->owner1->id,
            'role' => 'Business Owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->agent1 = User::create([
            'tenant_id' => $this->tenant1->id,
            'current_tenant_id' => $this->tenant1->id,
            'name' => 'Agent Bob',
            'email' => 'bob@omni1.com',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'Support Executive',
            'is_active' => true,
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->agent1->id,
            'role' => 'Support Executive',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->agent2 = User::create([
            'tenant_id' => $this->tenant1->id,
            'current_tenant_id' => $this->tenant1->id,
            'name' => 'Agent Carol',
            'email' => 'carol@omni1.com',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'Support Executive',
            'is_active' => true,
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->agent2->id,
            'role' => 'Support Executive',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->accountant1 = User::create([
            'tenant_id' => $this->tenant1->id,
            'current_tenant_id' => $this->tenant1->id,
            'name' => 'Accountant Dave',
            'email' => 'dave@omni1.com',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'Accountant',
            'is_active' => true,
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->accountant1->id,
            'role' => 'Accountant',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->tenant2User = User::create([
            'tenant_id' => $this->tenant2->id,
            'current_tenant_id' => $this->tenant2->id,
            'name' => 'Tenant 2 Agent',
            'email' => 'agent@omni2.com',
            'password' => Hash::make('SecretPass123!'),
            'role' => 'Support Executive',
            'is_active' => true,
        ]);
        TenantUser::create([
            'tenant_id' => $this->tenant2->id,
            'user_id' => $this->tenant2User->id,
            'role' => 'Support Executive',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_telegram_bot_account_connection_and_inbound_outbound(): void
    {
        // 1. Connect Telegram Bot Account
        $res = $this->actingAs($this->owner1)->postJson('/api/v1/crm/inbox/channels/telegram', [
            'bot_name' => 'SupportBot',
            'bot_username' => '@NexusSupportBot',
            'bot_token' => 'mock_123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'business_hours' => [
                'timezone' => 'Asia/Kolkata',
                'days' => [1, 2, 3, 4, 5],
                'start' => '09:00',
                'end' => '18:00',
            ],
        ]);
        $res->assertStatus(200);
        $res->assertJsonPath('data.is_connected', true);

        // 2. Inbound Telegram Webhook simulation
        $webhookPayload = [
            'update_id' => 10001,
            'message' => [
                'message_id' => 9988,
                'from' => [
                    'id' => 55443322,
                    'is_bot' => false,
                    'first_name' => 'Bruce',
                    'last_name' => 'Wayne',
                    'username' => 'brucewayne',
                ],
                'chat' => [
                    'id' => 55443322,
                    'first_name' => 'Bruce',
                    'last_name' => 'Wayne',
                    'username' => 'brucewayne',
                    'type' => 'private',
                ],
                'date' => 1700000000,
                'text' => 'Hello, I have an enterprise billing inquiry.',
            ],
        ];

        $inboundRes = $this->postJson("/api/v1/webhooks/telegram/{$this->tenant1->id}", $webhookPayload);
        $inboundRes->assertStatus(200);

        // Verify conversation was created
        $conv = OmnichannelConversation::where('tenant_id', $this->tenant1->id)
            ->where('channel', 'telegram')
            ->where('phone_number', '55443322')
            ->first();

        $this->assertNotNull($conv);
        $this->assertEquals('Bruce Wayne (@brucewayne)', $conv->customer_name);
        $this->assertEquals('open', $conv->status);
        $this->assertEquals(1, $conv->unread_count);

        // 3. Outbound reply over Telegram
        $replyRes = $this->actingAs($this->agent1)->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/messages", [
            'content' => 'Hi Bruce, happy to assist you with your billing inquiry!',
            'direction' => 'outbound',
            'sender_actor' => 'human',
        ]);
        $replyRes->assertStatus(200);
        $this->assertEquals('sent', $replyRes->json('data.delivery_status'));
        $this->assertEquals('telegram', $replyRes->json('data.channel'));
    }

    public function test_channel_settings_assignment_to_branch_and_department(): void
    {
        $branch = Branch::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Mumbai Head Office',
            'code' => 'MUM-01',
            'city' => 'Mumbai',
        ]);

        $department = Department::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Customer Success',
            'code' => 'CS',
        ]);

        $res = $this->actingAs($this->owner1)->postJson('/api/v1/crm/inbox/channels/settings', [
            'channel' => 'whatsapp',
            'name' => 'VIP Customer WhatsApp Line',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'business_hours' => [
                'timezone' => 'Asia/Kolkata',
                'days' => [1, 2, 3, 4, 5, 6],
                'start' => '08:30',
                'end' => '19:30',
            ],
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('data.branch_id', $branch->id);
        $res->assertJsonPath('data.department_id', $department->id);
        $res->assertJsonPath('data.name', 'VIP Customer WhatsApp Line');
    }

    public function test_channel_test_connection_endpoints(): void
    {
        $channels = ['whatsapp', 'facebook', 'email', 'sms', 'webchat', 'call'];

        foreach ($channels as $chan) {
            $res = $this->actingAs($this->owner1)->postJson('/api/v1/crm/inbox/channels/test-connection', [
                'channel' => $chan,
            ]);
            $res->assertStatus(200);
            $res->assertJsonPath('success', true);
            $res->assertJsonPath('data.channel', $chan);
        }
    }

    public function test_conversation_locking_and_unlocking(): void
    {
        $conv = WhatsAppConversation::create([
            'tenant_id' => $this->tenant1->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919876543210',
            'customer_name' => 'Locked Customer',
            'status' => 'open',
        ]);

        // Lock to Agent Bob
        $lockRes = $this->actingAs($this->agent1)->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/lock");
        $lockRes->assertStatus(200);
        $this->assertEquals($this->agent1->id, $lockRes->json('data.locked_by_user_id'));

        // Unlock
        $unlockRes = $this->actingAs($this->agent1)->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/unlock");
        $unlockRes->assertStatus(200);
        $this->assertNull($unlockRes->json('data.locked_by_user_id'));
    }

    public function test_conversation_archiving_and_filtering(): void
    {
        $conv = WhatsAppConversation::create([
            'tenant_id' => $this->tenant1->id,
            'channel' => 'email',
            'phone_number' => 'archive@customer.com',
            'customer_email' => 'archive@customer.com',
            'customer_name' => 'Archive Test',
            'status' => 'open',
            'is_archived' => false,
        ]);

        // Archive conversation
        $archRes = $this->actingAs($this->agent1)->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/archive", [
            'archive' => true,
        ]);
        $archRes->assertStatus(200);
        $this->assertTrue($archRes->json('data.is_archived'));

        // Verify default listing does NOT contain archived conversation
        $listRes = $this->actingAs($this->agent1)->getJson('/api/v1/crm/inbox/conversations');
        $listRes->assertStatus(200);
        $ids = collect($listRes->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($conv->id, $ids);

        // Verify archived listing DOES contain it
        $archivedListRes = $this->actingAs($this->agent1)->getJson('/api/v1/crm/inbox/conversations?filter=archived');
        $archivedListRes->assertStatus(200);
        $archivedIds = collect($archivedListRes->json('data'))->pluck('id')->toArray();
        $this->assertContains($conv->id, $archivedIds);
    }

    public function test_agent_presence_heartbeat_and_collision_detection(): void
    {
        $conv = WhatsAppConversation::create([
            'tenant_id' => $this->tenant1->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919988776655',
            'customer_name' => 'Collision Target',
            'status' => 'open',
        ]);

        // Agent 1 posts heartbeat typing on this conversation
        $presenceRes = $this->actingAs($this->agent1)->postJson('/api/v1/crm/inbox/presence', [
            'conversation_id' => $conv->id,
            'is_typing' => true,
        ]);
        $presenceRes->assertStatus(200);

        // Agent 2 checks collision on same conversation
        $collisionRes = $this->actingAs($this->agent2)->getJson("/api/v1/crm/inbox/conversations/{$conv->id}/collision");
        $collisionRes->assertStatus(200);
        $this->assertTrue($collisionRes->json('collision_detected'));
        $this->assertCount(1, $collisionRes->json('active_agents'));
        $this->assertEquals($this->agent1->id, $collisionRes->json('active_agents.0.user_id'));
    }

    public function test_routing_rules_creation_and_execution(): void
    {
        $branch = Branch::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Delhi Regional Branch',
            'city' => 'Delhi',
        ]);

        // Assign Agent 2 to Delhi branch
        $this->agent2->update(['branch_id' => $branch->id]);

        // Create Branch Routing Rule
        $ruleRes = $this->actingAs($this->owner1)->postJson('/api/v1/crm/inbox/routing-rules', [
            'name' => 'Route Delhi Enquiries to Agent Carol',
            'rule_type' => 'direct',
            'channel' => 'all',
            'conditions' => [
                'branch_id' => $branch->id,
            ],
            'target_user_id' => $this->agent2->id,
            'priority' => 1,
            'is_active' => true,
        ]);
        $ruleRes->assertStatus(200);

        // Create conversation tagged with Delhi branch
        $conv = WhatsAppConversation::create([
            'tenant_id' => $this->tenant1->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919111222333',
            'customer_name' => 'Delhi Lead',
            'branch_id' => $branch->id,
            'status' => 'open',
        ]);

        // Trigger auto-assign
        $autoRes = $this->actingAs($this->owner1)->postJson("/api/v1/crm/inbox/conversations/{$conv->id}/auto-assign");
        $autoRes->assertStatus(200);
        $this->assertEquals($this->agent2->id, $autoRes->json('data.assigned_user_id'));
    }

    public function test_sla_policy_calculation_and_business_hours_pause(): void
    {
        // 1. Create Urgent SLA Policy with Business Hours
        $slaRes = $this->actingAs($this->owner1)->postJson('/api/v1/crm/inbox/sla-policies', [
            'name' => 'Urgent Priority SLA',
            'priority' => 'urgent',
            'channel' => 'all',
            'first_response_target_minutes' => 15,
            'resolution_target_minutes' => 120,
            'business_hours_enabled' => true,
            'business_hours' => [
                'timezone' => 'Asia/Kolkata',
                'days' => [1, 2, 3, 4, 5],
                'start' => '09:00',
                'end' => '18:00',
            ],
            'escalation_target_minutes' => 30,
            'escalate_to_user_id' => $this->owner1->id,
            'is_active' => true,
        ]);
        $slaRes->assertStatus(200);

        // 2. Initialize SLA via recalculation
        $recalcRes = $this->actingAs($this->owner1)->postJson('/api/v1/crm/inbox/sla-policies/recalculate');
        $recalcRes->assertStatus(200);
        $recalcRes->assertJsonStructure(['success', 'summary' => ['total', 'on_track', 'warning', 'breached']]);
    }

    public function test_financial_data_access_gating_in_unified_inbox(): void
    {
        $conv = WhatsAppConversation::create([
            'tenant_id' => $this->tenant1->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919998887776',
            'customer_name' => 'Financial Test Customer',
            'status' => 'open',
        ]);

        // Create an unpaid invoice
        Invoice::create([
            'tenant_id' => $this->tenant1->id,
            'invoice_number' => 'INV-OMNI-001',
            'total_amount' => 50000.00,
            'balance_due' => 50000.00,
            'status' => 'unpaid',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
        ]);

        // Agent 1 (Support Executive - NO financial permission)
        $agentRes = $this->actingAs($this->agent1)->getJson("/api/v1/crm/inbox/conversations/{$conv->id}");
        $agentRes->assertStatus(200);
        $this->assertFalse($agentRes->json('financials.has_permission'));
        $this->assertArrayNotHasKey('invoices', $agentRes->json('financials'));

        // Accountant Dave (HAS financial permission)
        $accountantRes = $this->actingAs($this->accountant1)->getJson("/api/v1/crm/inbox/conversations/{$conv->id}");
        $accountantRes->assertStatus(200);
        $this->assertTrue($accountantRes->json('financials.has_permission'));
        $this->assertArrayHasKey('invoices', $accountantRes->json('financials'));
    }

    public function test_cross_tenant_omnichannel_isolation(): void
    {
        $convTenant1 = WhatsAppConversation::create([
            'tenant_id' => $this->tenant1->id,
            'channel' => 'whatsapp',
            'phone_number' => '+919999999999',
            'customer_name' => 'Tenant 1 Customer',
            'status' => 'open',
        ]);

        // Tenant 2 agent attempts to access Tenant 1 conversation
        $res = $this->actingAs($this->tenant2User)->getJson("/api/v1/crm/inbox/conversations/{$convTenant1->id}");
        $res->assertStatus(404);

        // Tenant 2 agent attempts to lock Tenant 1 conversation
        $lockRes = $this->actingAs($this->tenant2User)->postJson("/api/v1/crm/inbox/conversations/{$convTenant1->id}/lock");
        $lockRes->assertStatus(404);
    }
}
