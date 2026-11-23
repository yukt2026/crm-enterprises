<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Omnichannel\Models\CustomerSocialIdentity;
use App\Domains\Omnichannel\Models\SocialChannelAccount;
use App\Domains\Omnichannel\Models\SocialWebhookEvent;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\WhatsApp\Models\WhatsAppAiAgent;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialMessengerInstagramIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.meta.app_secret' => 'meta_secret_test_key_123']);
        config(['services.meta.webhook_verify_token' => 'crm_meta_webhook_secret_2026']);

        $this->tenantA = Tenant::create([
            'name' => 'Acme Social Brand',
            'slug' => 'acme-social',
            'subscription_tier' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Globex Competitor',
            'slug' => 'globex-competitor',
            'subscription_tier' => 'professional',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'name' => 'Sarah Social Admin',
            'email' => 'sarah@acme.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);
        $this->userA->workspaces()->attach($this->tenantA->id, ['role' => 'admin', 'status' => 'active']);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'name' => 'Bob Rival',
            'email' => 'bob@globex.com',
            'password' => bcrypt('Password123!'),
            'role' => 'admin',
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'admin', 'status' => 'active']);

        $this->tokenA = $this->userA->createToken('test-token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('test-token')->plainTextToken;
    }

    /**
     * Helper to compute Meta HMAC signature for webhooks.
     */
    protected function computeMetaSignature(string $content): string
    {
        return 'sha256=' . hash_hmac('sha256', $content, config('services.meta.app_secret'));
    }

    public function test_it_connects_facebook_page_and_instagram_professional_account(): void
    {
        $this->actingAs($this->userA, 'sanctum');

        $fbResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/crm/social/accounts', [
                'provider' => 'facebook',
                'page_id' => 'fb_page_1001',
                'page_name' => 'Acme Facebook Official',
                'page_access_token' => 'EAAB_test_mock_token_123',
                'permissions' => ['pages_messaging', 'pages_read_engagement'],
            ]);

        $fbResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.page_id', 'fb_page_1001')
            ->assertJsonPath('data.provider', 'facebook');

        $this->assertDatabaseHas('social_channel_accounts', [
            'tenant_id' => $this->tenantA->id,
            'page_id' => 'fb_page_1001',
            'provider' => 'facebook',
            'is_connected' => 1,
        ]);

        $igResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/crm/social/accounts', [
                'provider' => 'instagram',
                'page_id' => 'fb_page_1001',
                'page_name' => 'Acme IG Parent Page',
                'page_access_token' => 'EAAB_test_mock_token_456',
                'instagram_business_id' => 'ig_biz_2002',
                'instagram_username' => 'acme_official_ig',
                'permissions' => ['instagram_manage_messages', 'pages_show_list'],
            ]);

        $igResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.instagram_business_id', 'ig_biz_2002')
            ->assertJsonPath('data.provider', 'instagram');

        $this->assertDatabaseHas('social_channel_accounts', [
            'tenant_id' => $this->tenantA->id,
            'instagram_business_id' => 'ig_biz_2002',
            'provider' => 'instagram',
        ]);
    }

    public function test_it_verifies_meta_webhook_challenge(): void
    {
        $response = $this->get('/api/v1/webhooks/meta/social?hub.mode=subscribe&hub.challenge=test_challenge_999&hub.verify_token=crm_meta_webhook_secret_2026');

        $response->assertStatus(200);
        $this->assertEquals('test_challenge_999', $response->getContent());

        // Reject invalid verify token
        $badResponse = $this->get('/api/v1/webhooks/meta/social?hub.mode=subscribe&hub.challenge=test_challenge_999&hub.verify_token=wrong_token');
        $badResponse->assertStatus(403);
    }

    public function test_it_handles_incoming_facebook_messenger_webhook_and_creates_conversation(): void
    {
        SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'page_id' => 'fb_page_1001',
            'page_name' => 'Acme Facebook Official',
            'page_access_token' => 'EAAB_valid_token',
            'is_connected' => true,
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'fb_page_1001',
                    'time' => 1700000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'psid_user_777'],
                            'recipient' => ['id' => 'fb_page_1001'],
                            'timestamp' => 1700000000,
                            'message' => [
                                'mid' => 'mid.fb_msg_10001',
                                'text' => 'Hello from Facebook Messenger! Interested in enterprise pricing.',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $signature = $this->computeMetaSignature($jsonPayload);

        $response = $this->call(
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

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('whatsapp_conversations', [
            'tenant_id' => $this->tenantA->id,
            'channel' => 'facebook',
            'phone_number' => 'fb_psid_user_777',
            'channel_account_id' => 'fb_page_1001',
        ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'tenant_id' => $this->tenantA->id,
            'channel' => 'facebook',
            'provider_message_id' => 'mid.fb_msg_10001',
            'direction' => 'inbound',
            'content' => 'Hello from Facebook Messenger! Interested in enterprise pricing.',
        ]);

        $this->assertDatabaseHas('customer_social_identities', [
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'scoped_id' => 'psid_user_777',
        ]);
    }

    public function test_it_dispatches_outbound_facebook_messenger_reply_within_24h_window(): void
    {
        $this->actingAs($this->userA, 'sanctum');

        $account = SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'page_id' => 'fb_page_1001',
            'page_name' => 'Acme Facebook Official',
            'page_access_token' => 'EAAB_valid_token',
            'is_connected' => true,
        ]);

        $conversation = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'facebook',
            'phone_number' => 'fb_psid_user_777',
            'contact_name' => 'Visitor 777',
            'status' => 'open',
            'channel_account_id' => 'fb_page_1001',
            'window_expires_at' => Carbon::now()->addHours(12),
            'metadata' => [
                'psid' => 'psid_user_777',
                'page_id' => 'fb_page_1001',
            ],
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'recipient_id' => 'psid_user_777',
                'message_id' => 'mid.outbound_fb_reply_123',
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/social/conversations/{$conversation->id}/messages", [
                'content' => 'Thank you for reaching out! Here is our enterprise pricing info.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content', 'Thank you for reaching out! Here is our enterprise pricing info.')
            ->assertJsonPath('data.channel', 'facebook');

        $this->assertDatabaseHas('whatsapp_messages', [
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $conversation->id,
            'channel' => 'facebook',
            'direction' => 'outbound',
            'delivery_status' => 'sent',
        ]);
    }

    public function test_it_enforces_meta_24h_window_policy(): void
    {
        $this->actingAs($this->userA, 'sanctum');

        SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'page_id' => 'fb_page_1001',
            'page_name' => 'Acme Facebook Official',
            'page_access_token' => 'EAAB_valid_token',
            'is_connected' => true,
        ]);

        $expiredConversation = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'facebook',
            'phone_number' => 'fb_psid_user_old',
            'contact_name' => 'Old Visitor',
            'status' => 'open',
            'channel_account_id' => 'fb_page_1001',
            'window_expires_at' => Carbon::now()->subMinutes(10), // Expired!
            'metadata' => [
                'psid' => 'psid_user_old',
                'page_id' => 'fb_page_1001',
            ],
        ]);

        // Attempting to reply outside window without approved message tag fails with 422
        $blockedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/social/conversations/{$expiredConversation->id}/messages", [
                'content' => 'Late follow up message',
            ]);

        $blockedResponse->assertStatus(422)
            ->assertJsonValidationErrors(['window']);

        // Providing an approved message tag succeeds
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'recipient_id' => 'psid_user_old',
                'message_id' => 'mid.tagged_msg_789',
            ], 200),
        ]);

        $taggedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/social/conversations/{$expiredConversation->id}/messages", [
                'content' => 'Update on your support issue',
                'tag' => 'POST_PURCHASE_UPDATE',
            ]);

        $taggedResponse->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_it_handles_incoming_instagram_dm_webhook_and_outbound_reply(): void
    {
        SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'instagram',
            'page_id' => 'fb_page_1001',
            'page_name' => 'Acme Facebook Official',
            'instagram_business_id' => 'ig_biz_2002',
            'instagram_username' => 'acme_official_ig',
            'page_access_token' => 'EAAB_ig_token',
            'is_connected' => true,
        ]);

        $igPayload = [
            'object' => 'instagram',
            'entry' => [
                [
                    'id' => 'ig_biz_2002',
                    'time' => 1700000050,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'igsid_user_888'],
                            'recipient' => ['id' => 'ig_biz_2002'],
                            'timestamp' => 1700000050,
                            'message' => [
                                'mid' => 'mid.ig_msg_50001',
                                'text' => 'Hey love your products! Can you ship to Canada?',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($igPayload);
        $signature = $this->computeMetaSignature($jsonPayload);

        $response = $this->call(
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

        $response->assertStatus(200);

        $conversation = WhatsAppConversation::where('channel', 'instagram')
            ->where('phone_number', 'ig_igsid_user_888')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertEquals('ig_igsid_user_888', $conversation->phone_number);
        $this->assertEquals('ig_biz_2002', $conversation->channel_account_id);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'recipient_id' => 'igsid_user_888',
                'message_id' => 'mid.ig_outbound_reply_99',
            ], 200),
        ]);

        $this->actingAs($this->userA, 'sanctum');

        $replyResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/social/conversations/{$conversation->id}/messages", [
                'content' => 'Yes, we ship to Canada within 3-5 business days!',
            ]);

        $replyResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.channel', 'instagram');

        $this->assertDatabaseHas('whatsapp_messages', [
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $conversation->id,
            'channel' => 'instagram',
            'direction' => 'outbound',
            'delivery_status' => 'sent',
        ]);
    }

    public function test_it_rejects_duplicate_webhook_events_idempotently(): void
    {
        SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'page_id' => 'fb_page_1001',
            'page_name' => 'Acme Facebook Official',
            'page_access_token' => 'EAAB_valid_token',
            'is_connected' => true,
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'fb_page_1001',
                    'time' => 1700000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'psid_user_dup'],
                            'recipient' => ['id' => 'fb_page_1001'],
                            'timestamp' => 1700000000,
                            'message' => [
                                'mid' => 'mid.fb_duplicate_test_1',
                                'text' => 'Duplicate message check',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $signature = $this->computeMetaSignature($jsonPayload);

        // First delivery
        $res1 = $this->call('POST', '/api/v1/webhooks/meta/social', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $jsonPayload);
        $res1->assertStatus(200);

        // Second delivery (duplicate replay)
        $res2 = $this->call('POST', '/api/v1/webhooks/meta/social', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $jsonPayload);
        $res2->assertStatus(200);

        // Verify only 1 message was inserted in the database
        $count = WhatsAppMessage::where('provider_message_id', 'mid.fb_duplicate_test_1')->count();
        $this->assertEquals(1, $count);
    }

    public function test_it_triggers_ai_handover_on_social_keywords(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid.handover_sent_1'], 200),
        ]);

        $agent = WhatsAppAiAgent::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Support Bot',
            'system_prompt' => 'Helpful social agent',
            'is_active' => true,
        ]);

        SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'page_id' => 'fb_page_1001',
            'page_name' => 'Acme Facebook Official',
            'page_access_token' => 'EAAB_valid_token',
            'is_connected' => true,
            'ai_enabled' => true,
            'ai_agent_id' => $agent->id,
            'ai_handover_keywords' => ['human', 'agent', 'support rep'],
        ]);

        $payload = [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'fb_page_1001',
                    'time' => 1700000000,
                    'messaging' => [
                        [
                            'sender' => ['id' => 'psid_user_handover'],
                            'recipient' => ['id' => 'fb_page_1001'],
                            'timestamp' => 1700000000,
                            'message' => [
                                'mid' => 'mid.fb_handover_msg_1',
                                'text' => 'I need to speak to a human agent please!',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $jsonPayload = json_encode($payload);
        $signature = $this->computeMetaSignature($jsonPayload);

        $res = $this->call('POST', '/api/v1/webhooks/meta/social', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $jsonPayload);
        $res->assertStatus(200);

        $conv = WhatsAppConversation::where('channel', 'facebook')->latest('id')->first();
        $this->assertNotNull($conv);
        // Assert human handover occurred
        $this->assertFalse((bool)$conv->is_ai_handled);
        $this->assertEquals('high', $conv->priority);
    }

    public function test_it_links_social_identity_to_crm_lead_and_merges_identities(): void
    {
        $this->actingAs($this->userA, 'sanctum');

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Diana Prince',
            'first_name' => 'Diana',
            'last_name' => 'Prince',
            'email' => 'diana@themyscira.io',
            'phone' => '+15550192834',
            'status' => 'new',
            'source' => 'facebook',
        ]);

        $identity = CustomerSocialIdentity::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'scoped_id' => 'psid_diana_999',
            'display_name' => 'Diana P',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson("/api/v1/crm/social/identities/{$identity->id}/link", [
                'lead_id' => $lead->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead_id', $lead->id);

        $this->assertDatabaseHas('customer_social_identities', [
            'id' => $identity->id,
            'lead_id' => $lead->id,
        ]);

        // Create second identity (Instagram) and merge into primary identity
        $identity2 = CustomerSocialIdentity::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'instagram',
            'scoped_id' => 'igsid_diana_insta',
            'display_name' => 'diana_insta',
        ]);

        $mergeResponse = $this->withHeader('Authorization', 'Bearer ' . $this->tokenA)
            ->postJson('/api/v1/crm/social/identities/merge', [
                'primary_identity_id' => $identity->id,
                'secondary_identity_id' => $identity2->id,
            ]);

        $mergeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('customer_social_identities', [
            'id' => $identity2->id,
            'merged_with_id' => $identity->id,
            'lead_id' => $lead->id,
        ]);
    }

    public function test_it_strictly_enforces_cross_tenant_isolation(): void
    {
        // Tenant A creates an account and conversation
        $accountA = SocialChannelAccount::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'facebook',
            'page_id' => 'fb_page_tenant_a',
            'page_name' => 'Acme Official',
            'page_access_token' => 'EAAB_token_a',
            'is_connected' => true,
        ]);

        $convA = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'facebook',
            'phone_number' => 'fb_psid_tenant_a_user',
            'contact_name' => 'Acme Customer',
            'status' => 'open',
            'channel_account_id' => 'fb_page_tenant_a',
            'window_expires_at' => Carbon::now()->addHours(10),
        ]);

        // User B tries to disconnect Tenant A's account -> 404
        $this->actingAs($this->userB, 'sanctum');

        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->postJson("/api/v1/crm/social/accounts/{$accountA->id}/disconnect");
        $res1->assertStatus(404);

        // User B tries to reply to Tenant A's conversation -> 404
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->postJson("/api/v1/crm/social/conversations/{$convA->id}/messages", [
                'content' => 'Malicious cross tenant reply',
            ]);
        $res2->assertStatus(404);

        // User B's accounts list does not include Tenant A's account
        $res3 = $this->withHeader('Authorization', 'Bearer ' . $this->tokenB)
            ->getJson('/api/v1/crm/social/accounts');
        $res3->assertStatus(200)
            ->assertJsonMissing(['page_id' => 'fb_page_tenant_a']);
    }
}
