<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AttributionTouchpoint;
use App\Domains\Analytics\Models\TrackingEvent;
use App\Domains\Analytics\Models\TrackingSetting;
use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use App\Domains\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTrackingSdkGa4Test extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantAlpha;
    protected Tenant $tenantBeta;
    protected User $userAlpha;
    protected User $userBeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAlpha = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'domain' => 'acme.test',
            'is_active' => true,
        ]);

        $this->tenantBeta = Tenant::create([
            'name' => 'Beta Global',
            'slug' => 'beta-global',
            'domain' => 'beta.test',
            'is_active' => true,
        ]);

        $this->userAlpha = User::factory()->create([
            'tenant_id' => $this->tenantAlpha->id,
            'email' => 'admin@acme.test',
            'role' => 'admin',
        ]);

        $this->userBeta = User::factory()->create([
            'tenant_id' => $this->tenantBeta->id,
            'email' => 'admin@beta.test',
            'role' => 'admin',
        ]);

        TenantUser::create([
            'tenant_id' => $this->tenantAlpha->id,
            'user_id' => $this->userAlpha->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        TenantUser::create([
            'tenant_id' => $this->tenantBeta->id,
            'user_id' => $this->userBeta->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: GA4 property connection, property selection and reporting permissions
     */
    public function test_ga4_connection_and_reporting_permissions(): void
    {
        $this->actingAs($this->userAlpha, 'sanctum');
        $headers = [
            'Authorization' => 'Bearer ' . $this->userAlpha->createToken('test')->plainTextToken,
            'X-Workspace-Id' => $this->tenantAlpha->id,
        ];

        // 1. Connect GA4 property
        $connectRes = $this->postJson('/api/v1/crm/tracking/ga4/connect', [
            'property_id' => '123456789',
            'measurement_id' => 'G-ACMEPROD99',
            'api_secret' => 'secret_meas_protocol_xyz',
            'display_name' => 'Acme Main Website',
        ], $headers);

        $connectRes->assertStatus(200);
        $this->assertTrue($connectRes->json('success'));
        $this->assertEquals('properties/123456789', $connectRes->json('data.ga4_property_id'));

        // 2. Fetch Available Properties
        $propRes = $this->getJson('/api/v1/crm/tracking/ga4/properties', $headers);
        $propRes->assertStatus(200);
        $this->assertTrue($propRes->json('data.connected'));
        $this->assertEquals('properties/123456789', $propRes->json('data.active_property_id'));

        // 3. Fetch GA4 Aggregated Report
        $reportRes = $this->getJson('/api/v1/crm/tracking/ga4/report?start_date=2026-10-01&end_date=2026-10-31', $headers);
        $reportRes->assertStatus(200);
        $reportData = $reportRes->json('data');

        $this->assertArrayHasKey('summary', $reportData);
        $this->assertArrayHasKey('traffic_sources', $reportData);
        $this->assertArrayHasKey('campaigns', $reportData);
        $this->assertArrayHasKey('landing_pages', $reportData);
        $this->assertArrayHasKey('trend', $reportData);

        // Verify privacy boundaries: GA4 is strictly aggregated traffic / attribution, no PII extracted
        $this->assertStringContainsString('GA4 data is aggregated', $reportData['privacy_notice']);
        $this->assertGreaterThan(0, $reportData['summary']['sessions']);
        $this->assertGreaterThan(0, $reportData['summary']['conversions']);

        // 4. Disconnect GA4
        $disRes = $this->postJson('/api/v1/crm/tracking/ga4/disconnect', [], $headers);
        $disRes->assertStatus(200);

        $propResAfter = $this->getJson('/api/v1/crm/tracking/ga4/properties', $headers);
        $this->assertFalse($propResAfter->json('data.connected'));
    }

    /**
     * Test 2: First-party tracking event ingestion, UTM capture, and IP anonymization
     */
    public function test_public_event_ingestion_and_utm_capture(): void
    {
        $setting = TrackingSetting::forTenant($this->tenantAlpha->id);
        $trackingKey = $setting->tracking_key;

        // Ingest anonymous page view with campaign UTMs
        $payload = [
            'tracking_key' => $trackingKey,
            'session_id' => 'sess_anon_987654',
            'event_name' => 'page_view',
            'event_id' => 'evt_page_001',
            'page_url' => 'https://acme.test/solutions/enterprise-crm?utm_source=google_ads&utm_medium=cpc&utm_campaign=q4_launch&utm_term=saas_crm',
            'page_title' => 'Enterprise CRM Solutions',
            'referrer' => 'https://www.google.com',
            'utm_source' => 'google_ads',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'q4_launch',
            'utm_term' => 'saas_crm',
            'consent_given' => true,
        ];

        $response = $this->postJson('/api/v1/tracking/events', $payload, [
            'REMOTE_ADDR' => '203.0.113.195',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertEquals('success', $response->json('data.status'));

        // Verify stored event in DB
        $event = TrackingEvent::withoutGlobalScopes()->where('event_id', 'evt_page_001')->first();
        $this->assertNotNull($event);
        $this->assertEquals($this->tenantAlpha->id, $event->tenant_id);
        $this->assertEquals('google_ads', $event->utm_source);
        $this->assertEquals('q4_launch', $event->utm_campaign);
        $this->assertEquals('203.0.113.0', $event->ip_anonymized); // Masked IP for privacy
        $this->assertNull($event->lead_id); // Anonymous prior to form submission
    }

    /**
     * Test 3: Consent restrictions prevent tracking when consent is required and denied
     */
    public function test_consent_restrictions_block_unauthorized_tracking(): void
    {
        // Configure tenant to require explicit consent
        $setting = TrackingSetting::forTenant($this->tenantAlpha->id);
        $setting->update(['require_consent' => true]);

        // Attempt event ingestion with consent_given = false
        $response = $this->postJson('/api/v1/tracking/events', [
            'tracking_key' => $setting->tracking_key,
            'session_id' => 'sess_no_consent_11',
            'event_name' => 'page_view',
            'page_url' => 'https://acme.test/pricing',
            'consent_given' => false,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('ignored', $response->json('data.status'));
        $this->assertStringContainsString('Visitor has not granted analytical consent', $response->json('data.reason'));

        // Verify no record persisted in database
        $count = TrackingEvent::withoutGlobalScopes()->where('session_id', 'sess_no_consent_11')->count();
        $this->assertEquals(0, $count);
    }

    /**
     * Test 4: Bot & automated crawler filtering
     */
    public function test_bot_filtering_rejects_automated_crawlers(): void
    {
        $setting = TrackingSetting::forTenant($this->tenantAlpha->id);

        $response = $this->postJson('/api/v1/tracking/events', [
            'tracking_key' => $setting->tracking_key,
            'session_id' => 'sess_bot_test',
            'event_name' => 'page_view',
            'page_url' => 'https://acme.test/about',
        ], [
            'HTTP_USER_AGENT' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('ignored', $response->json('data.status'));
        $this->assertStringContainsString('Bot or automated crawler traffic detected', $response->json('data.reason'));
    }

    /**
     * Test 5: Duplicate event prevention (idempotency within session)
     */
    public function test_duplicate_event_deduplication(): void
    {
        $setting = TrackingSetting::forTenant($this->tenantAlpha->id);

        $payload = [
            'tracking_key' => $setting->tracking_key,
            'session_id' => 'sess_dedup_100',
            'event_name' => 'cta_click',
            'event_id' => 'evt_btn_click_xyz',
            'page_url' => 'https://acme.test/signup',
            'metadata' => ['button' => 'Request Free Trial'],
        ];

        // First dispatch
        $res1 = $this->postJson('/api/v1/tracking/events', $payload);
        $res1->assertStatus(200);
        $this->assertEquals('success', $res1->json('data.status'));

        // Second dispatch with same event_id
        $res2 = $this->postJson('/api/v1/tracking/events', $payload);
        $res2->assertStatus(200);
        $this->assertEquals('duplicate', $res2->json('data.status'));

        // Only 1 record in database
        $count = TrackingEvent::withoutGlobalScopes()
            ->where('session_id', 'sess_dedup_100')
            ->where('event_id', 'evt_btn_click_xyz')
            ->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 6: Form conversion linking and backfilling session touchpoint attribution
     */
    public function test_form_conversion_linking_and_multi_touch_attribution(): void
    {
        $setting = TrackingSetting::forTenant($this->tenantAlpha->id);
        $sessionId = 'sess_buyer_journey_42';

        // 1. Anonymous First Touch: Organic search visit to landing page
        $this->postJson('/api/v1/tracking/events', [
            'tracking_key' => $setting->tracking_key,
            'session_id' => $sessionId,
            'event_name' => 'page_view',
            'event_id' => 'evt_touch_1',
            'page_url' => 'https://acme.test/blog/saas-automation',
            'utm_source' => 'google_organic',
            'utm_medium' => 'organic',
        ]);

        // 2. Intermediate Touch: CTA click
        $this->postJson('/api/v1/tracking/events', [
            'tracking_key' => $setting->tracking_key,
            'session_id' => $sessionId,
            'event_name' => 'cta_click',
            'event_id' => 'evt_touch_2',
            'page_url' => 'https://acme.test/demo',
            'metadata' => ['cta' => 'Book Demo'],
        ]);

        // Prior to form submission, lead_id is null
        $eventsBefore = TrackingEvent::withoutGlobalScopes()
            ->where('session_id', $sessionId)
            ->whereNull('lead_id')
            ->count();
        $this->assertEquals(2, $eventsBefore);

        // 3. Lead Creation in CRM
        $lead = Lead::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Sarah Connor',
            'email' => 'sarah.connor@cyberdyne.test',
            'company_name' => 'Cyberdyne Systems',
            'status' => 'new',
        ]);

        // 4. Voluntary Form Submission containing email
        $submitRes = $this->postJson('/api/v1/tracking/events', [
            'tracking_key' => $setting->tracking_key,
            'session_id' => $sessionId,
            'event_name' => 'form_submission',
            'event_id' => 'evt_touch_3',
            'page_url' => 'https://acme.test/contact',
            'email' => 'sarah.connor@cyberdyne.test',
            'metadata' => [
                'form_name' => 'Enterprise Demo Request',
                'team_size' => '50-200',
            ],
        ]);

        $submitRes->assertStatus(200);
        $this->assertTrue($submitRes->json('data.lead_associated'));

        // Verify that ALL earlier events for this session were backfilled with lead_id
        $unassociated = TrackingEvent::withoutGlobalScopes()
            ->where('session_id', $sessionId)
            ->whereNull('lead_id')
            ->count();
        $this->assertEquals(0, $unassociated);

        $associated = TrackingEvent::withoutGlobalScopes()
            ->where('session_id', $sessionId)
            ->where('lead_id', $lead->id)
            ->count();
        $this->assertEquals(3, $associated);

        // Verify AttributionTouchpoint record was created
        $touchpoint = AttributionTouchpoint::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantAlpha->id)
            ->where('lead_id', $lead->id)
            ->first();
        $this->assertNotNull($touchpoint);
        $this->assertEquals('form_submit', $touchpoint->touchpoint_type);

        // 5. Query Attribution Endpoint
        $this->actingAs($this->userAlpha, 'sanctum');
        $headers = [
            'Authorization' => 'Bearer ' . $this->userAlpha->createToken('test')->plainTextToken,
            'X-Workspace-Id' => $this->tenantAlpha->id,
        ];
        $attrRes = $this->getJson('/api/v1/crm/tracking/attribution', $headers);
        $attrRes->assertStatus(200);
        $journeys = $attrRes->json('data.lead_attribution_journeys');
        $this->assertNotEmpty($journeys);
        $this->assertEquals($lead->id, $journeys[0]['lead_id']);
        $this->assertEquals('Sarah Connor', $journeys[0]['lead_name']);
        $this->assertEquals(3, $journeys[0]['total_touches']);
    }

    /**
     * Test 7: Serving dynamic tracking script snippet
     */
    public function test_serve_dynamic_tracking_script(): void
    {
        $setting = TrackingSetting::forTenant($this->tenantAlpha->id);

        $response = $this->get("/api/v1/tracking/script/{$setting->tracking_key}.js");
        $response->assertStatus(200);
        $this->assertStringContainsString('application/javascript', $response->headers->get('Content-Type'));
        $content = $response->getContent();

        $this->assertStringContainsString($setting->tracking_key, $content);
        $this->assertStringContainsString('window.crmTrack', $content);
        $this->assertStringContainsString('page_view', $content);
        $this->assertStringContainsString('utm_source', $content);
    }

    /**
     * Test 8: Strict multi-tenant isolation on tracking settings and events
     */
    public function test_strict_multi_tenant_isolation_on_tracking(): void
    {
        $headersAlpha = [
            'Authorization' => 'Bearer ' . $this->userAlpha->createToken('test')->plainTextToken,
            'X-Workspace-Id' => $this->tenantAlpha->id,
        ];

        $headersBeta = [
            'Authorization' => 'Bearer ' . $this->userBeta->createToken('test')->plainTextToken,
            'X-Workspace-Id' => $this->tenantBeta->id,
        ];

        // Seed events in Tenant Alpha
        $settingAlpha = TrackingSetting::forTenant($this->tenantAlpha->id);
        $this->postJson('/api/v1/tracking/events', [
            'tracking_key' => $settingAlpha->tracking_key,
            'session_id' => 'sess_alpha_1',
            'event_name' => 'page_view',
            'page_url' => 'https://acme.test/alpha-exclusive',
        ]);

        // Tenant Alpha can view their event
        $this->actingAs($this->userAlpha, 'sanctum');
        $resAlpha = $this->getJson('/api/v1/crm/tracking/events', $headersAlpha);
        $resAlpha->assertStatus(200);
        $this->assertCount(1, $resAlpha->json('data.data'));
        $this->assertEquals('https://acme.test/alpha-exclusive', $resAlpha->json('data.data.0.page_url'));

        // Tenant Beta querying events receives ZERO events from Tenant Alpha
        $this->actingAs($this->userBeta, 'sanctum');
        $resBeta = $this->getJson('/api/v1/crm/tracking/events', $headersBeta);
        $resBeta->assertStatus(200);
        $this->assertCount(0, $resBeta->json('data.data'));

        // Tenant Beta has their own independent tracking settings and tracking key
        $settingBetaRes = $this->getJson('/api/v1/crm/tracking/settings', $headersBeta);
        $settingBetaRes->assertStatus(200);
        $this->assertNotEquals($settingAlpha->tracking_key, $settingBetaRes->json('data.tracking_key'));
    }
}
