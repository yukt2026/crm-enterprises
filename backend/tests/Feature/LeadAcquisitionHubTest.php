<?php

namespace Tests\Feature;

use App\Domains\LeadAcquisition\Models\AdCampaignMetric;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFieldMapping;
use App\Domains\LeadAcquisition\Models\LeadForm;
use App\Domains\LeadAcquisition\Models\LeadIngestionEvent;
use App\Domains\LeadAcquisition\Models\LeadSource;
use App\Domains\LeadAcquisition\Services\LeadIngestionPipeline;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadAcquisitionHubTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Global',
            'slug' => 'acme-global',
            'is_active' => true,
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Industries',
            'slug' => 'beta-ind',
            'is_active' => true,
        ]);

        $this->userA = User::create([
            'name' => 'Alice Admin',
            'email' => 'alice@acme.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
        ]);
        $this->userA->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin']);

        $this->userB = User::create([
            'name' => 'Bob Beta',
            'email' => 'bob@beta.com',
            'password' => bcrypt('password123'),
            'role' => 'Business Admin',
        ]);
        $this->userB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin']);
    }

    /**
     * Test Google Ads Lead Form ingestion and attribution.
     */
    public function test_google_ads_lead_form_ingestion_and_attribution(): void
    {
        $source = LeadSource::create([
            'workspace_id' => $this->tenantA->id,
            'provider' => 'google_ads',
            'name' => 'Enterprise Google Ads',
            'account_id' => '123-456-7890',
            'status' => 'connected',
            'credentials' => [
                'google_key' => 'secret_google_key_999',
            ],
        ]);

        $payload = [
            'lead_id' => 'goog_lead_unique_101',
            'form_id' => 'form_goog_44',
            'campaign_id' => 'camp_8899',
            'campaign_name' => 'High Intent ERP Search',
            'gclid' => 'Cj0KCQjwmv6hBhDhARIsA',
            'google_key' => 'secret_google_key_999',
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'High Intent ERP Search',
            'user_column_data' => [
                ['column_id' => 'FULL_NAME', 'string_value' => 'David Miller'],
                ['column_id' => 'EMAIL', 'string_value' => 'david@millerindustries.com'],
                ['column_id' => 'PHONE_NUMBER', 'string_value' => '+14155552671'],
                ['column_id' => 'COMPANY_NAME', 'string_value' => 'Miller Industries'],
            ],
        ];

        $response = $this->postJson("/api/v1/webhooks/google-ads/{$source->id}", $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status' => 'processed',
        ]);

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'David Miller',
            'email' => 'david@millerindustries.com',
            'phone' => '+14155552671',
            'company_name' => 'Miller Industries',
            'source' => 'google_ads',
            'campaign' => 'High Intent ERP Search',
            'utm_source' => 'google',
        ]);

        $this->assertDatabaseHas('lead_ingestion_events', [
            'workspace_id' => $this->tenantA->id,
            'event_id' => 'goog_lead_unique_101',
            'status' => 'processed',
        ]);
    }

    /**
     * Test Meta Lead Ads webhook with HMAC SHA256 signature verification.
     */
    public function test_meta_lead_ads_signature_verification_and_rejection(): void
    {
        $appSecret = 'meta_secret_key_123';
        $source = LeadSource::create([
            'workspace_id' => $this->tenantA->id,
            'provider' => 'meta_ads',
            'name' => 'Meta Facebook Lead Ads',
            'account_id' => 'page_meta_777',
            'status' => 'connected',
            'credentials' => [
                'app_secret' => $appSecret,
            ],
        ]);

        $payload = [
            'entry' => [
                [
                    'id' => 'page_meta_777',
                    'time' => time(),
                    'changes' => [
                        [
                            'field' => 'leadgen',
                            'value' => [
                                'leadgen_id' => 'meta_leadgen_555',
                                'page_id' => 'page_meta_777',
                                'form_id' => 'meta_form_22',
                                'campaign_id' => 'cmp_meta_99',
                                'campaign_name' => 'Instagram Retargeting B2B',
                                'field_data' => [
                                    ['name' => 'full_name', 'values' => ['Sophia Loren']],
                                    ['name' => 'email', 'values' => ['sophia@lorenenterprises.com']],
                                    ['name' => 'phone_number', 'values' => ['+12125550199']],
                                    ['name' => 'company_name', 'values' => ['Loren Enterprises']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // 1. Invalid signature should be rejected (400 / unauthorized)
        $invalidHeaders = ['X-Hub-Signature-256' => 'sha256=invalid_hash'];
        $respInvalid = $this->postJson('/api/v1/webhooks/meta-ads', $payload, $invalidHeaders);
        $respInvalid->assertStatus(400);

        // 2. Valid signature computed with app_secret
        $rawBody = json_encode($payload);
        $validSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        $validHeaders = ['X-Hub-Signature-256' => $validSignature];

        $respValid = $this->call('POST', '/api/v1/webhooks/meta-ads', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $validSignature,
            'CONTENT_TYPE' => 'application/json',
        ], $rawBody);

        $respValid->assertStatus(200);

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sophia Loren',
            'email' => 'sophia@lorenenterprises.com',
            'source' => 'meta_ads',
        ]);
    }

    /**
     * Test Idempotency: Duplicate events do not create duplicate leads.
     */
    public function test_duplicate_webhook_idempotency_prevents_duplicate_leads(): void
    {
        $source = LeadSource::create([
            'workspace_id' => $this->tenantA->id,
            'provider' => 'webhook',
            'name' => 'Zapier Webhook Inbound',
            'status' => 'connected',
        ]);

        $payload = [
            'event_id' => 'idempotent_event_777888',
            'name' => 'Emma Watson',
            'email' => 'emma@watsoncorp.com',
            'phone' => '+14155550111',
            'company' => 'Watson Corp',
            'requirement' => 'Need 50 enterprise CRM seats',
        ];

        // First delivery: creates lead
        $res1 = $this->postJson("/api/v1/webhooks/generic/{$source->id}", $payload);
        $res1->assertStatus(200);
        $res1->assertJson(['status' => 'processed', 'action' => 'created']);

        $leadsCountBefore = Lead::where('tenant_id', $this->tenantA->id)->where('email', 'emma@watsoncorp.com')->count();
        $this->assertEquals(1, $leadsCountBefore);

        // Second delivery (duplicate retry / redelivery with same event_id): must recognize duplicate
        $res2 = $this->postJson("/api/v1/webhooks/generic/{$source->id}", $payload);
        $res2->assertStatus(200);
        $res2->assertJson(['status' => 'duplicate']);

        $leadsCountAfter = Lead::where('tenant_id', $this->tenantA->id)->where('email', 'emma@watsoncorp.com')->count();
        $this->assertEquals(1, $leadsCountAfter, 'Duplicate delivery should not create duplicate lead.');
    }

    /**
     * Test Tenant Field Mapping with transformation rules.
     */
    public function test_tenant_field_mapping_and_transformation(): void
    {
        $source = LeadSource::create([
            'workspace_id' => $this->tenantA->id,
            'provider' => 'generic',
            'name' => 'Custom Form Webhook',
            'status' => 'connected',
        ]);

        // Configure custom field mappings
        LeadFieldMapping::create([
            'workspace_id' => $this->tenantA->id,
            'lead_source_id' => $source->id,
            'provider' => 'generic',
            'source_field' => 'customer_telephone',
            'crm_field' => 'phone',
            'transform_rule' => 'phone_normalize',
        ]);

        LeadFieldMapping::create([
            'workspace_id' => $this->tenantA->id,
            'lead_source_id' => $source->id,
            'provider' => 'generic',
            'source_field' => 'org_name',
            'crm_field' => 'company_name',
            'transform_rule' => 'uppercase',
        ]);

        $payload = [
            'event_id' => 'evt_mapping_123',
            'name' => 'Michael Chang',
            'email' => 'michael@changholdings.com',
            'customer_telephone' => '+1 (800) 555-0199 ext 4',
            'org_name' => 'chang holdings corp',
        ];

        $res = $this->postJson("/api/v1/webhooks/generic/{$source->id}", $payload);
        $res->assertStatus(200);

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $this->tenantA->id,
            'email' => 'michael@changholdings.com',
            'phone' => '+180055501994', // Stripped non-digits via phone_normalize
            'company_name' => 'CHANG HOLDINGS CORP', // Uppercase via transform rule
        ]);
    }

    /**
     * Test Public Form Submission with Honeypot Bot Rejection.
     */
    public function test_public_form_submission_and_honeypot_rejection(): void
    {
        $form = LeadForm::create([
            'workspace_id' => $this->tenantA->id,
            'name' => 'Landing Page Hero Form',
            'provider' => 'website',
            'public_uuid' => 'form-uuid-acme-1234',
            'is_active' => true,
        ]);

        // 1. Spambot submitting honeypot field
        $botPayload = [
            'name' => 'Bot Spammer',
            'email' => 'bot@spammer.com',
            '_hp_check' => 'I am a robot trap',
        ];
        $botRes = $this->postJson("/api/v1/public/forms/{$form->public_uuid}/submit", $botPayload);
        $botRes->assertStatus(200); // Quiet 200 response to avoid alerting bots

        $this->assertDatabaseMissing('leads', ['email' => 'bot@spammer.com']);

        // 2. Legitimate human submission with UTM parameters
        $humanPayload = [
            'name' => 'Eleanor Vance',
            'email' => 'eleanor@vancearchitects.com',
            'phone' => '+14155550144',
            'company_name' => 'Vance Architects',
            'requirement' => 'Need architectural CRM setup',
            'utm_source' => 'google',
            'utm_medium' => 'organic',
            'utm_campaign' => 'Architectural CRM Software',
        ];

        $humanRes = $this->postJson("/api/v1/public/forms/{$form->public_uuid}/submit", $humanPayload);
        $humanRes->assertStatus(200);
        $humanRes->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $this->tenantA->id,
            'name' => 'Eleanor Vance',
            'email' => 'eleanor@vancearchitects.com',
            'utm_source' => 'google',
        ]);
    }

    /**
     * Test Failed Event Ingestion and Retry mechanism.
     */
    public function test_failed_event_inspection_and_retry(): void
    {
        $source = LeadSource::create([
            'workspace_id' => $this->tenantA->id,
            'provider' => 'webhook',
            'name' => 'Inbound Webhook',
            'status' => 'connected',
        ]);

        // 1. Submit event with missing required identity fields (no name, email, or phone)
        $invalidPayload = [
            'event_id' => 'evt_missing_identity_99',
            'message' => 'Hello there, someone interested',
        ];

        $res = $this->postJson("/api/v1/webhooks/generic/{$source->id}", $invalidPayload);
        $res->assertStatus(400);

        $event = LeadIngestionEvent::where('workspace_id', $this->tenantA->id)
            ->where('event_id', 'evt_missing_identity_99')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('failed', $event->status);

        // 2. Test retry endpoint with Sanctum Auth
        $token = $this->userA->createToken('test_token')->plainTextToken;

        // Fix payload in event raw_payload to simulate manual correction or schema update
        $event->update([
            'raw_payload' => json_encode([
                'event_id' => 'evt_missing_identity_99',
                'name' => 'Corrected Name',
                'email' => 'corrected@example.com',
                'message' => 'Fixed payload',
            ]),
        ]);

        $retryRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->postJson("/api/v1/crm/lead-ingestion-events/{$event->id}/retry");

        $retryRes->assertStatus(200);

        $event->refresh();
        $this->assertEquals('processed', $event->status);
        $this->assertNotNull($event->lead_id);
    }

    /**
     * Test Strict Cross-Tenant Isolation on Lead Acquisition.
     */
    public function test_tenant_isolation_on_lead_sources_and_events(): void
    {
        $sourceA = LeadSource::create([
            'workspace_id' => $this->tenantA->id,
            'provider' => 'google_ads',
            'name' => 'Tenant A Google Ads',
            'status' => 'connected',
        ]);

        $sourceB = LeadSource::create([
            'workspace_id' => $this->tenantB->id,
            'provider' => 'google_ads',
            'name' => 'Tenant B Google Ads',
            'status' => 'connected',
        ]);

        $tokenA = $this->userA->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->withHeader('X-Workspace-Id', $this->tenantA->id)
            ->getJson('/api/v1/crm/lead-sources');

        $response->assertStatus(200);
        $sourceIds = collect($response->json('data.sources'))->pluck('id')->all();

        $this->assertContains($sourceA->id, $sourceIds);
        $this->assertNotContains($sourceB->id, $sourceIds, 'User A should never see Tenant B lead sources.');
    }
}
