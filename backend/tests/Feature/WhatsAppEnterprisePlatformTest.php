<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Contact;
use App\Domains\Omnichannel\Models\CustomerCommunicationConsent;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\WhatsApp\Models\WhatsAppAccount;
use App\Domains\WhatsApp\Models\WhatsAppAutomation;
use App\Domains\WhatsApp\Models\WhatsAppAutomationLog;
use App\Domains\WhatsApp\Models\WhatsAppCampaign;
use App\Domains\WhatsApp\Models\WhatsAppCampaignRecipient;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Domains\WhatsApp\Models\WhatsAppOptOut;
use App\Domains\WhatsApp\Models\WhatsAppPhoneNumber;
use App\Domains\WhatsApp\Models\WhatsAppTemplate;
use App\Domains\WhatsApp\Models\WhatsAppUsageRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WhatsAppEnterprisePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $adminUser;
    protected User $salesUser;
    protected User $tenantBUser;
    protected string $adminToken;
    protected string $salesToken;
    protected string $tenantBToken;
    protected WhatsAppAccount $accountA;
    protected WhatsAppPhoneNumber $phoneA;
    protected WhatsAppTemplate $templateA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tenants
        $this->tenantA = Tenant::create([
            'name' => 'Enterprise Alpha Corp',
            'slug' => 'alpha-corp',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Enterprise Beta Corp',
            'slug' => 'beta-corp',
            'is_active' => true,
        ]);

        // 2. Admin User (Tenant A)
        $this->adminUser = User::create([
            'name' => 'Alice Administrator',
            'email' => 'alice@alpha.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $this->adminUser->workspaces()->attach($this->tenantA->id, ['role' => 'admin', 'status' => 'active']);
        $this->adminToken = $this->adminUser->createToken('admin_token')->plainTextToken;

        // 3. Sales Rep User (Tenant A) - lacks campaigns_approve permission
        $this->salesUser = User::create([
            'name' => 'Sam Sales',
            'email' => 'sam@alpha.com',
            'password' => bcrypt('password123'),
            'role' => 'sales_executive',
            'current_tenant_id' => $this->tenantA->id,
        ]);
        $this->salesUser->workspaces()->attach($this->tenantA->id, ['role' => 'sales_executive', 'status' => 'active']);
        $this->salesToken = $this->salesUser->createToken('sales_token')->plainTextToken;

        // 4. User for Tenant B
        $this->tenantBUser = User::create([
            'name' => 'Bob Beta',
            'email' => 'bob@beta.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'current_tenant_id' => $this->tenantB->id,
        ]);
        $this->tenantBUser->workspaces()->attach($this->tenantB->id, ['role' => 'admin', 'status' => 'active']);
        $this->tenantBToken = $this->tenantBUser->createToken('beta_token')->plainTextToken;

        // 5. Account & Phone Number for Tenant A
        $this->accountA = WhatsAppAccount::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha WABA Primary',
            'waba_id' => 'waba_alpha_9999',
            'app_id' => 'meta_app_8888',
            'system_user_token' => 'test_token_alpha_mock',
            'webhook_verify_token' => 'alpha_webhook_verify_token',
            'status' => 'connected',
            'business_verification_status' => 'verified',
            'token_expires_at' => Carbon::now()->addDays(60),
        ]);

        $this->phoneA = WhatsAppPhoneNumber::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_account_id' => $this->accountA->id,
            'phone_number_id' => 'phone_id_alpha_1',
            'display_phone_number' => '+91 98765 43210',
            'normalized_number' => '+919876543210',
            'verified_name' => 'Alpha Official Support',
            'quality_rating' => 'GREEN',
            'messaging_limit_tier' => 'TIER_10K',
            'channel_status' => 'active',
            'is_default' => true,
            'working_hours' => [
                'monday' => ['start' => '09:00', 'end' => '18:00', 'active' => true],
                'tuesday' => ['start' => '09:00', 'end' => '18:00', 'active' => true],
            ],
            'default_reply_rules' => [
                'after_hours_message' => 'Thank you for contacting us. We will get back to you during business hours.',
            ],
        ]);

        // 6. Template for Tenant A
        $this->templateA = WhatsAppTemplate::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_account_id' => $this->accountA->id,
            'template_name' => 'enterprise_welcome_greeting',
            'category' => 'MARKETING',
            'language' => 'en_US',
            'body_text' => 'Hello {{1}}, welcome to {{2}}! We are delighted to partner with you.',
            'status' => 'APPROVED',
            'variables_schema' => ['1' => 'Customer Name', '2' => 'Company Name'],
            'sent_count' => 0,
        ]);

        $this->actingAs($this->adminUser, 'sanctum');
    }

    /**
     * Test 1: Multiple Number Management, Department / Branch config & connectivity
     */
    public function test_multiple_number_management_and_working_hours(): void
    {
        // Add a secondary sales number
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson('/api/v1/crm/whatsapp/phone-numbers', [
            'whatsapp_account_id' => $this->accountA->id,
            'phone_number_id' => 'phone_id_alpha_sales_2',
            'display_phone_number' => '+91 98765 00001',
            'normalized_number' => '+919876500001',
            'verified_name' => 'Alpha Sales Hotline',
        ]);

        $response->assertStatus(201);
        $phoneId = $response->json('data.id');

        $branch = \App\Domains\CRM\Models\Branch::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'HQ Delhi',
            'code' => 'HQ-DEL',
        ]);

        $department = \App\Domains\HR\Models\Department::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise Sales',
            'code' => 'SALES',
        ]);

        // Update phone number settings with branch and messaging limit tier
        $updateResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->putJson("/api/v1/crm/whatsapp/phone-numbers/{$phoneId}", [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'messaging_limit_tier' => 'TIER_100K',
            'working_hours' => [
                'monday' => ['start' => '10:00', 'end' => '19:00', 'active' => true],
            ],
        ]);

        $updateResp->assertStatus(200);
        $this->assertEquals('TIER_100K', $updateResp->json('data.messaging_limit_tier'));

        // Disconnect phone number
        $delResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->deleteJson("/api/v1/crm/whatsapp/phone-numbers/{$phoneId}");

        $delResp->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_phone_numbers', [
            'id' => $phoneId,
            'channel_status' => 'inactive',
        ]);
    }

    /**
     * Test 2: Template Sync, Preview, Test Dispatch & Analytics
     */
    public function test_template_sync_test_dispatch_and_analytics(): void
    {
        // 1. Sync templates
        $syncResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson('/api/v1/crm/whatsapp/templates/sync', [
            'whatsapp_account_id' => $this->accountA->id,
        ]);

        $syncResp->assertStatus(200);
        $this->assertDatabaseHas('whatsapp_templates', [
            'tenant_id' => $this->tenantA->id,
            'template_name' => 'payment_reminder_urgent',
        ]);

        // 2. Test template dispatch
        $testResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson("/api/v1/crm/whatsapp/templates/{$this->templateA->id}/test", [
            'phone_number' => '+919999988888',
            'parameters' => ['Rohan Sharma', 'Alpha Corp'],
        ]);

        $testResp->assertStatus(200);
        $this->assertEquals(1, $this->templateA->fresh()->sent_count);

        // 3. Analytics
        $analyticsResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->getJson('/api/v1/crm/whatsapp/templates/analytics');

        $analyticsResp->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $analyticsResp->json('data.total_templates'));
        $this->assertGreaterThanOrEqual(1, $analyticsResp->json('data.total_sent'));
    }

    /**
     * Test 3: Campaign Creation with CSV Audience, Duplicate Removal & Cost Estimation
     */
    public function test_campaign_creation_with_csv_and_duplicate_detection(): void
    {
        $csvRows = [
            ['phone' => '9876511111', 'name' => 'Dev Sharma', 'company' => 'Acme Inc'],
            ['phone' => '9876511111', 'name' => 'Dev Sharma Dup', 'company' => 'Acme Inc'], // Duplicate!
            ['phone' => '+91 98765 22222', 'name' => 'Priya Patel', 'company' => 'Priya Ltd'],
            ['phone' => '123', 'name' => 'Invalid Phone', 'company' => 'Invalid'], // Invalid length
            ['phone' => '9876533333', 'name' => 'Vikram Roy', 'company' => 'Roy Holdings'],
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson('/api/v1/crm/whatsapp/campaigns', [
            'name' => 'Q4 Enterprise Product Launch',
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'whatsapp_template_id' => $this->templateA->id,
            'audience_type' => 'csv_import',
            'csv_rows' => $csvRows,
            'variable_mappings' => ['1' => 'name', '2' => 'company'],
        ]);

        $response->assertStatus(201);
        $campaignId = $response->json('data.id');

        $this->assertDatabaseHas('whatsapp_campaigns', [
            'id' => $campaignId,
            'status' => 'draft',
            'total_recipients' => 3, // Only 3 valid unique contacts
        ]);

        $this->assertEquals(3, WhatsAppCampaignRecipient::where('campaign_id', $campaignId)->count());
    }

    /**
     * Test 4: Opt-Out Suppression List Filtering
     */
    public function test_campaign_opt_out_suppression_list_filtering(): void
    {
        // Opt out one recipient
        WhatsAppOptOut::create([
            'tenant_id' => $this->tenantA->id,
            'phone_number' => '+919876522222',
            'reason' => 'USER_STOP',
            'opted_out_at' => now(),
        ]);

        $csvRows = [
            ['phone' => '9876511111', 'name' => 'Dev Sharma', 'company' => 'Acme Inc'],
            ['phone' => '9876522222', 'name' => 'Priya Patel', 'company' => 'Priya Ltd'], // Opted out!
        ];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson('/api/v1/crm/whatsapp/campaigns', [
            'name' => 'Suppression Test Campaign',
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'whatsapp_template_id' => $this->templateA->id,
            'audience_type' => 'csv_import',
            'csv_rows' => $csvRows,
            'variable_mappings' => ['1' => 'name', '2' => 'company'],
        ]);

        $response->assertStatus(201);
        $campaignId = $response->json('data.id');

        // Check recipients
        $suppressed = WhatsAppCampaignRecipient::where('campaign_id', $campaignId)
            ->where('phone_number', '+919876522222')
            ->first();

        $this->assertNotNull($suppressed);
        $this->assertEquals('suppressed_opt_out', $suppressed->status);
        $this->assertEquals('Recipient has opted out', $suppressed->error_message);
    }

    /**
     * Test 5: Two-Step Campaign Approval RBAC Enforcement
     */
    public function test_two_step_campaign_approval_rbac_enforcement(): void
    {
        // 1. Create campaign as Sales Rep
        $campaign = WhatsAppCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_account_id' => $this->accountA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'whatsapp_template_id' => $this->templateA->id,
            'created_by_user_id' => $this->salesUser->id,
            'name' => 'Sales Team Campaign',
            'status' => 'draft',
            'total_recipients' => 1,
            'audience_type' => 'contacts',
        ]);

        // Submit for approval as Sales
        $this->actingAs($this->salesUser, 'sanctum');
        $this->withHeaders([
            'Authorization' => "Bearer {$this->salesToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson("/api/v1/crm/whatsapp/campaigns/{$campaign->id}/submit")
          ->assertStatus(200);

        $this->assertEquals('pending_approval', $campaign->fresh()->status);

        // 2. Sales Rep attempts to approve campaign -> 403 Forbidden!
        $forbiddenResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->salesToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson("/api/v1/crm/whatsapp/campaigns/{$campaign->id}/approve");

        $forbiddenResp->assertStatus(403);
        $this->assertEquals('pending_approval', $campaign->fresh()->status);

        // 3. Admin approves campaign -> 200 OK!
        $this->actingAs($this->adminUser, 'sanctum');
        $adminApproveResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson("/api/v1/crm/whatsapp/campaigns/{$campaign->id}/approve");

        $adminApproveResp->assertStatus(200);
        $this->assertTrue(in_array($campaign->fresh()->status, ['approved', 'queued']));
        $this->assertEquals($this->adminUser->id, $campaign->fresh()->approved_by_user_id);
    }

    /**
     * Test 6: Campaign Execution, Batch Processing & Billing Records
     */
    public function test_campaign_batch_execution_and_billing_generation(): void
    {
        // Create approved campaign with recipients
        $campaign = WhatsAppCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_account_id' => $this->accountA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'whatsapp_template_id' => $this->templateA->id,
            'created_by_user_id' => $this->adminUser->id,
            'approved_by_user_id' => $this->adminUser->id,
            'name' => 'Execution Test Campaign',
            'status' => 'approved',
            'total_recipients' => 2,
            'audience_type' => 'csv_import',
        ]);

        $recipient1 = WhatsAppCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '+919876543211',
            'recipient_name' => 'Recipient One',
            'variables' => ['Recipient One', 'Alpha Corp'],
            'status' => 'queued',
        ]);

        $recipient2 = WhatsAppCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '+919876543212',
            'recipient_name' => 'Recipient Two',
            'variables' => ['Recipient Two', 'Alpha Corp'],
            'status' => 'queued',
        ]);

        // Launch campaign
        $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson("/api/v1/crm/whatsapp/campaigns/{$campaign->id}/start")
          ->assertStatus(200);

        // Process batch
        $batchResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson("/api/v1/crm/whatsapp/campaigns/{$campaign->id}/process-batch", [
            'batch_size' => 10,
        ]);

        $batchResp->assertStatus(200);
        $this->assertEquals(2, $batchResp->json('data.processed_count'));

        // Recipients should now be 'sent'
        $this->assertEquals('sent', $recipient1->fresh()->status);
        $this->assertEquals('sent', $recipient2->fresh()->status);
        $this->assertNotNull($recipient1->fresh()->whatsapp_message_id);

        // Campaign should be marked 'completed'
        $this->assertEquals('completed', $campaign->fresh()->status);
        $this->assertEquals(2, $campaign->fresh()->sent_count);

        // Usage records created
        $this->assertEquals(2, WhatsAppUsageRecord::where('campaign_id', $campaign->id)->count());

        // Check Analytics Funnel
        $analyticsResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->getJson("/api/v1/crm/whatsapp/campaigns/{$campaign->id}");

        $analyticsResp->assertStatus(200);
        $this->assertEquals(2, $analyticsResp->json('data.analytics.sent'));
        $this->assertEquals(100, $analyticsResp->json('data.analytics.delivery_rate_percent'));
    }

    /**
     * Test 7: Automated Customer Journeys (New Enquiry & Payment Reminder)
     */
    public function test_automated_customer_journeys(): void
    {
        // 1. Create welcome journey
        $journeyResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson('/api/v1/crm/whatsapp/automations', [
            'name' => 'New Lead Welcome Journey',
            'journey_type' => 'new_enquiry_welcome',
            'trigger_event' => 'crm_lead_created',
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'whatsapp_template_id' => $this->templateA->id,
            'delay_minutes' => 0,
            'status' => 'active',
            'stop_conditions' => 'customer_replied',
            'variable_mappings' => ['1' => 'lead_name', '2' => 'company_name'],
        ]);

        $journeyResp->assertStatus(201);
        $automationId = $journeyResp->json('data.id');

        // 2. Trigger test event
        $triggerResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->postJson('/api/v1/crm/whatsapp/automations/trigger-test', [
            'event' => 'crm_lead_created',
            'phone_number' => '+919988776655',
            'payload' => [
                'lead_name' => 'Ananya Roy',
                'company_name' => 'Alpha Corp',
            ],
        ]);

        $triggerResp->assertStatus(200);

        // Check automation log created
        $this->assertDatabaseHas('whatsapp_automation_logs', [
            'automation_id' => $automationId,
            'phone_number' => '+919988776655',
            'status' => 'sent',
        ]);

        $this->assertEquals(1, WhatsAppAutomation::find($automationId)->execution_count);
    }

    /**
     * Test 8: Webhook Opt-Out STOP keyword compliance
     */
    public function test_webhook_stop_keyword_opt_out_compliance(): void
    {
        $webhookPayload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => $this->accountA->waba_id,
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '919876543210',
                                    'phone_number_id' => $this->phoneA->phone_number_id,
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Customer OptOut'],
                                        'wa_id' => '919876599999',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '919876599999',
                                        'id' => 'wamid.HB.optout123',
                                        'timestamp' => (string) time(),
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'STOP',
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        $rawPayload = json_encode($webhookPayload);
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

        // Check WhatsAppOptOut table
        $this->assertDatabaseHas('whatsapp_opt_outs', [
            'tenant_id' => $this->tenantA->id,
            'phone_number' => '+919876599999',
        ]);

        // Check CustomerCommunicationConsent sync
        $this->assertDatabaseHas('customer_communication_consents', [
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'destination' => '+919876599999',
            'consent_status' => 'opted_out',
        ]);
    }

    /**
     * Test 9: WhatsApp Usage & Billing Reports
     */
    public function test_usage_and_billing_reports(): void
    {
        // Seed usage record
        WhatsAppUsageRecord::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'conversation_category' => 'marketing',
            'provider_reported_charge' => 0.0500,
            'rate_currency' => 'USD',
            'billable_conversations_count' => 1,
            'recorded_date' => now()->toDateString(),
        ]);

        WhatsAppUsageRecord::create([
            'tenant_id' => $this->tenantA->id,
            'whatsapp_phone_number_id' => $this->phoneA->id,
            'conversation_category' => 'utility',
            'provider_reported_charge' => 0.0150,
            'rate_currency' => 'USD',
            'billable_conversations_count' => 1,
            'recorded_date' => now()->toDateString(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->getJson('/api/v1/crm/whatsapp/usage/summary');

        $response->assertStatus(200);
        $this->assertEquals(0.065, $response->json('data.total_cost'));
        $this->assertEquals(2, $response->json('data.total_conversations'));
        $this->assertCount(2, $response->json('data.by_category'));
    }

    /**
     * Test 10: Strict Cross-Tenant Isolation
     */
    public function test_strict_cross_tenant_isolation(): void
    {
        // Tenant B creates a campaign
        $accountB = WhatsAppAccount::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta WABA',
            'waba_id' => 'waba_beta_123',
            'system_user_token' => 'beta_token',
            'webhook_verify_token' => 'beta_token_verify',
        ]);

        $phoneB = WhatsAppPhoneNumber::create([
            'tenant_id' => $this->tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'phone_number_id' => 'phone_beta_1',
            'display_phone_number' => '+1 555 123 4567',
            'normalized_number' => '+15551234567',
        ]);

        $templateB = WhatsAppTemplate::create([
            'tenant_id' => $this->tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'template_name' => 'beta_notice',
            'body_text' => 'Beta notice message',
            'status' => 'APPROVED',
        ]);

        $campaignB = WhatsAppCampaign::create([
            'tenant_id' => $this->tenantB->id,
            'whatsapp_account_id' => $accountB->id,
            'whatsapp_phone_number_id' => $phoneB->id,
            'whatsapp_template_id' => $templateB->id,
            'created_by_user_id' => $this->tenantBUser->id,
            'name' => 'Beta Private Campaign',
            'status' => 'draft',
            'total_recipients' => 1,
            'audience_type' => 'contacts',
        ]);

        // Tenant A admin tries to fetch Tenant B's campaign -> 404
        $resp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->getJson("/api/v1/crm/whatsapp/campaigns/{$campaignB->id}");

        $resp->assertStatus(404);

        // Tenant A listing does not show Tenant B's campaign
        $listResp = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
            'X-Tenant-ID' => $this->tenantA->id,
        ])->getJson('/api/v1/crm/whatsapp/campaigns');

        $listResp->assertStatus(200);
        $ids = collect($listResp->json('data.data'))->pluck('id')->toArray();
        $this->assertNotContains($campaignB->id, $ids);
    }
}
