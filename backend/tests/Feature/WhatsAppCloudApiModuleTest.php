<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Contact;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\WhatsApp\Models\WhatsAppAccount;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppMessage;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use App\Domains\WhatsApp\Models\WhatsAppTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppCloudApiModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userAdmin;
    protected User $userB;
    protected string $tokenAdmin;
    protected string $tokenB;
    protected WhatsAppAccount $accountA;
    protected WhatsAppPhoneNumber $phoneA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Global',
            'slug' => 'beta-global',
            'is_active' => true,
        ]);

        $this->userAdmin = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice.wa@acme.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $this->userAdmin->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->userB = User::create([
            'name' => 'Bob Beta',
            'email' => 'bob.wa@beta.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
            'current_tenant_id' => $this->tenantB->id,
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->tokenAdmin = $this->userAdmin->createToken('admin_token')->plainTextToken;
        $this->tokenB = $this->userB->createToken('b_token')->plainTextToken;

        // Pre-create WABA account and phone number for Tenant A
        $this->accountA = WhatsAppAccount::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Official WABA',
            'waba_id' => 'waba_acme_1001',
            'app_id' => 'app_acme_2001',
            'system_user_token' => 'test_token_acme_secret',
            'webhook_verify_token' => 'whatsapp_crm_verify_token_default',
            'status' => 'connected',
            'business_verification_status' => 'verified',
        ]);

        $this->phoneA = WhatsAppPhoneNumber::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_account_id' => $this->accountA->id,
            'phone_number_id' => 'phone_id_99001',
            'display_phone_number' => '+15551234567',
            'normalized_number' => '+15551234567',
            'verified_name' => 'Acme Support',
            'quality_rating' => 'GREEN',
            'status' => 'CONNECTED',
            'is_default' => true,
        ]);
    }

    protected function getAuthHeaders(Tenant $tenant, string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Workspace-Id' => (string) $tenant->id,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Test Meta Webhook Challenge Verification (GET)
     */
    public function test_webhook_challenge_verification(): void
    {
        // 1. Valid verification token returns hub.challenge string
        $challenge = 'meta_test_challenge_abc_123';
        $resValid = $this->get("/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=whatsapp_crm_verify_token_default&hub.challenge={$challenge}");
        $resValid->assertStatus(200);
        $this->assertEquals($challenge, $resValid->getContent());

        // 2. Wrong verification token returns 403 Forbidden
        $resInvalid = $this->get("/api/v1/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong_token&hub.challenge={$challenge}");
        $resInvalid->assertStatus(403);
    }

    /**
     * Test Cryptographic Webhook HMAC SHA256 Signature Verification
     */
    public function test_webhook_cryptographic_signature_verification_and_rejection(): void
    {
        $payloadArray = [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ];
        $rawPayload = json_encode($payloadArray);
        $appSecret = config('services.whatsapp.app_secret', 'meta_app_secret_default_key');
        $validSignature = 'sha256=' . hash_hmac('sha256', $rawPayload, $appSecret);

        // 1. Missing signature header returns 400
        $resNoSig = $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json'], $rawPayload);
        $resNoSig->assertStatus(400);

        // 2. Forged signature returns 400
        $resFakeSig = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp',
            [],
            [],
            [],
            ['HTTP_X-Hub-Signature-256' => 'sha256=invalid_hash_value', 'CONTENT_TYPE' => 'application/json'],
            $rawPayload
        );
        $resFakeSig->assertStatus(400);

        // 3. Genuine cryptographic signature returns 200
        $resGenuine = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp',
            [],
            [],
            [],
            ['HTTP_X-Hub-Signature-256' => $validSignature, 'CONTENT_TYPE' => 'application/json'],
            $rawPayload
        );
        $resGenuine->assertStatus(200);
    }

    /**
     * Test Inbound Message Ingestion and Automatic CRM Linking
     */
    public function test_inbound_message_ingestion_and_automatic_crm_linking(): void
    {
        // Pre-create contact in Tenant A with matching phone
        $contact = Contact::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Bruce Wayne',
            'first_name' => 'Bruce',
            'last_name' => 'Wayne',
            'email' => 'bruce@wayne.com',
            'phone' => '+19998887777',
        ]);

        $msgId = 'wamid.HB.inbound_msg_' . Str::random(16);
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => $this->accountA->waba_id,
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => $this->phoneA->display_phone_number,
                                    'phone_number_id' => $this->phoneA->phone_number_id,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Bruce Wayne'],
                                        'wa_id' => '19998887777',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '19998887777',
                                        'id' => $msgId,
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Need emergency infrastructure support for Gotham facility.'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload);
        $sig = 'sha256=' . hash_hmac('sha256', $rawPayload, config('services.whatsapp.app_secret', 'meta_app_secret_default_key'));

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/whatsapp',
            [],
            [],
            [],
            ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'],
            $rawPayload
        );
        $response->assertStatus(200);

        // Assert Conversation created and linked to CRM Contact
        $conversation = WhatsAppConversation::where('tenant_id', $this->tenantA->id)
            ->where('phone_number', '+19998887777')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertEquals($contact->id, $conversation->contact_id);
        $this->assertEquals('open', $conversation->status);
        $this->assertTrue($conversation->isWindowActive());
        $this->assertEquals(1, $conversation->unread_count);

        // Assert Message created
        $message = WhatsAppMessage::where('provider_message_id', $msgId)->first();
        $this->assertNotNull($message);
        $this->assertEquals('inbound', $message->direction);
        $this->assertEquals('Need emergency infrastructure support for Gotham facility.', $message->content);
        $this->assertEquals('delivered', $message->delivery_status);
    }

    /**
     * Test Webhook Idempotency Prevents Duplicate Ingestion
     */
    public function test_webhook_idempotency_prevents_duplicate_messages(): void
    {
        $msgId = 'wamid.HB.idempotent_test_' . Str::random(12);
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'metadata' => ['phone_number_id' => $this->phoneA->phone_number_id],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => $msgId,
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'Checking idempotency guarantee'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($payload);
        $sig = 'sha256=' . hash_hmac('sha256', $rawPayload, config('services.whatsapp.app_secret', 'meta_app_secret_default_key'));

        // 1st delivery
        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'], $rawPayload);

        // 2nd delivery (duplicate webhook retry)
        $res2 = $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'], $rawPayload);
        $res2->assertStatus(200);
        $this->assertEquals(1, $res2->json('data.ignored_duplicates'));

        // Assert strictly only 1 message exists in database
        $count = WhatsAppMessage::where('provider_message_id', $msgId)->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 24-Hour Customer Service Session Window Enforcement
     */
    public function test_24_hour_customer_service_window_enforcement(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);
        $this->actingAs($this->userAdmin, 'sanctum');

        // Create conversation
        $conversation = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'phone_number' => '+15553332222',
            'customer_name' => 'Clark Kent',
            'status' => 'open',
            'window_expires_at' => Carbon::now()->addHours(12), // ACTIVE window
        ]);

        // 1. Sending freeform text inside 24h window succeeds
        $resActive = $this->postJson("/api/v1/crm/whatsapp/conversations/{$conversation->id}/messages/text", [
            'text' => 'Hello Clark, our consultant is reviewing your proposal.',
        ], $headers);
        $resActive->assertStatus(201);
        $this->assertEquals('outbound', $resActive->json('data.direction'));

        // 2. Expire window (simulate 24 hours passed)
        $conversation->update(['window_expires_at' => Carbon::now()->subHour()]);

        // 3. Freeform text message outside 24h window MUST BE REJECTED with 422
        $resExpired = $this->postJson("/api/v1/crm/whatsapp/conversations/{$conversation->id}/messages/text", [
            'text' => 'Checking in after window expired.',
        ], $headers);
        $resExpired->assertStatus(422);
        $resExpired->assertJsonValidationErrors(['window']);

        // 4. Pre-approved template outside 24h window SUCCEEDS
        $template = WhatsAppTemplate::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_account_id' => $this->accountA->id,
            'template_name' => 'account_update_notice',
            'category' => 'UTILITY',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'body_text' => 'Hello {{1}}, an update is available on your ticket {{2}}.',
        ]);

        $resTemplate = $this->postJson("/api/v1/crm/whatsapp/conversations/{$conversation->id}/messages/template", [
            'template_name' => 'account_update_notice',
            'language' => 'en_US',
            'parameters' => ['Clark', 'TICK-909'],
        ], $headers);

        $resTemplate->assertStatus(201);
        $this->assertEquals('template', $resTemplate->json('data.message_type'));
    }

    /**
     * Test Message Template Variable Mapping and Rendering
     */
    public function test_template_sending_and_variable_mapping(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);
        $this->actingAs($this->userAdmin, 'sanctum');

        $conversation = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'phone_number' => '+15557778888',
            'customer_name' => 'Diana Prince',
            'status' => 'open',
            'window_expires_at' => Carbon::now()->subHours(5),
        ]);

        $template = WhatsAppTemplate::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_account_id' => $this->accountA->id,
            'template_name' => 'invoice_ready_v1',
            'category' => 'UTILITY',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'body_text' => 'Dear {{1}}, your invoice {{2}} for amount {{3}} has been generated.',
        ]);

        $response = $this->postJson("/api/v1/crm/whatsapp/conversations/{$conversation->id}/messages/template", [
            'template_name' => 'invoice_ready_v1',
            'parameters' => ['Diana', 'INV-2026-0045', '₹15,000'],
        ], $headers);

        $response->assertStatus(201);
        $msgContent = $response->json('data.content');
        $this->assertEquals('Dear Diana, your invoice INV-2026-0045 for amount ₹15,000 has been generated.', $msgContent);
    }

    /**
     * Test Delivery Status Webhooks (sent -> delivered -> read)
     */
    public function test_delivery_status_updates(): void
    {
        $conversation = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'phone_number' => '+15556667777',
            'status' => 'open',
            'window_expires_at' => Carbon::now()->addHours(24),
        ]);

        $providerMsgId = 'wamid.HB.status_track_' . Str::random(12);

        $message = WhatsAppMessage::create([
            'tenant_id' => $this->tenantA->id,
            'conversation_id' => $conversation->id,
            'provider_message_id' => $providerMsgId,
            'direction' => 'outbound',
            'message_type' => 'text',
            'content' => 'Proposal dispatched',
            'delivery_status' => 'sent',
            'sent_at' => Carbon::now(),
        ]);

        // Simulate Status Webhook from Meta: "read"
        $statusPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'metadata' => ['phone_number_id' => $this->phoneA->phone_number_id],
                                'statuses' => [
                                    [
                                        'id' => $providerMsgId,
                                        'status' => 'read',
                                        'timestamp' => (string) time(),
                                        'recipient_id' => '15556667777',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($statusPayload);
        $sig = 'sha256=' . hash_hmac('sha256', $rawPayload, config('services.whatsapp.app_secret', 'meta_app_secret_default_key'));

        $res = $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'], $rawPayload);
        $res->assertStatus(200);

        $updated = WhatsAppMessage::find($message->id);
        $this->assertEquals('read', $updated->delivery_status);
        $this->assertNotNull($updated->read_at);
    }

    /**
     * Test Opt-Out Keyword Suppression
     */
    public function test_opt_out_suppression_enforcement(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);
        $this->actingAs($this->userAdmin, 'sanctum');

        // Customer sends "STOP" via webhook
        $stopPayload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'field' => 'messages',
                            'value' => [
                                'metadata' => ['phone_number_id' => $this->phoneA->phone_number_id],
                                'messages' => [
                                    [
                                        'from' => '15554443333',
                                        'id' => 'wamid.HB.optout_' . Str::random(10),
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => ['body' => 'STOP'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($stopPayload);
        $sig = 'sha256=' . hash_hmac('sha256', $rawPayload, config('services.whatsapp.app_secret', 'meta_app_secret_default_key'));
        $this->call('POST', '/api/v1/webhooks/whatsapp', [], [], [], ['HTTP_X-Hub-Signature-256' => $sig, 'CONTENT_TYPE' => 'application/json'], $rawPayload);

        // Assert customer registered in suppression list
        $optOut = WhatsAppOptOut::where('tenant_id', $this->tenantA->id)
            ->where('phone_number', '+15554443333')
            ->first();
        $this->assertNotNull($optOut);

        // Attempting to send message to this number is rejected
        $conversation = WhatsAppConversation::where('phone_number', '+15554443333')->first();
        $this->assertNotNull($conversation);

        $outboundRes = $this->postJson("/api/v1/crm/whatsapp/conversations/{$conversation->id}/messages/text", [
            'text' => 'Hello again!',
        ], $headers);

        $outboundRes->assertStatus(422);
        $outboundRes->assertJsonValidationErrors(['opt_out']);
    }

    /**
     * Test Internal Team Notes and AI Handover Toggling
     */
    public function test_internal_team_notes_and_ai_handover(): void
    {
        $headers = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);
        $this->actingAs($this->userAdmin, 'sanctum');

        $conversation = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'phone_number' => '+15551112222',
            'status' => 'open',
            'is_ai_handled' => false,
            'window_expires_at' => Carbon::now()->addHours(20),
        ]);

        // 1. Add internal team note
        $noteRes = $this->postJson("/api/v1/crm/whatsapp/conversations/{$conversation->id}/notes", [
            'content' => 'Customer requested enterprise discount, discussing with finance.',
        ], $headers);
        $noteRes->assertStatus(201);
        $this->assertEquals('Customer requested enterprise discount, discussing with finance.', $noteRes->json('data.content'));

        // 2. Toggle AI takeover
        $aiRes = $this->postJson("/api/v1/crm/whatsapp/conversations/{$conversation->id}/toggle-ai", [
            'is_ai_handled' => true,
        ], $headers);
        $aiRes->assertStatus(200);
        $this->assertTrue(WhatsAppConversation::find($conversation->id)->is_ai_handled);
    }

    /**
     * Test Multi-Tenant Isolation across WhatsApp CRM
     */
    public function test_tenant_isolation_on_whatsapp_crm(): void
    {
        $headersA = $this->getAuthHeaders($this->tenantA, $this->tokenAdmin);
        $headersB = $this->getAuthHeaders($this->tenantB, $this->tokenB);

        // Tenant A creates conversation
        $this->actingAs($this->userAdmin, 'sanctum');
        $convA = WhatsAppConversation::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'phone_number' => '+15559990000',
            'status' => 'open',
            'window_expires_at' => Carbon::now()->addHours(24),
        ]);

        // Tenant B cannot view Tenant A's conversation
        $this->actingAs($this->userB, 'sanctum');
        $forbiddenView = $this->getJson("/api/v1/crm/whatsapp/conversations/{$convA->id}", $headersB);
        $this->assertTrue(in_array($forbiddenView->status(), [404, 403]));

        // Tenant B cannot send message to Tenant A's conversation
        $forbiddenSend = $this->postJson("/api/v1/crm/whatsapp/conversations/{$convA->id}/messages/text", [
            'text' => 'Infiltrating other tenant conversation',
        ], $headersB);
        $this->assertTrue(in_array($forbiddenSend->status(), [404, 403]));
    }
}
