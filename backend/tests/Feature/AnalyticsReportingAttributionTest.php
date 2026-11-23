<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AttributionTouchpoint;
use App\Domains\Analytics\Models\SavedReport;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsReportingAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $adminUserA;
    protected User $adminUserB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Analytics Enterprise',
            'domain' => 'acme-analytics.test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Apex Competitor Corp',
            'domain' => 'apex-corp.test',
            'status' => 'active',
        ]);

        $this->adminUserA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Business Admin',
            'name' => 'Alice Admin',
            'email' => 'alice@acme-analytics.test',
        ]);
        $this->adminUserA->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->adminUserB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'role' => 'Business Admin',
            'name' => 'Bob Competitor',
            'email' => 'bob@apex-corp.test',
        ]);
        $this->adminUserB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin', 'status' => 'active']);
    }

    public function test_executive_metrics_returns_real_kpis_and_comparison_trends(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // Seed Tenant A data
        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Lead1 Alpha',
            'status' => 'converted',
            'source' => 'Google Ads',
            'created_at' => Carbon::now()->subDays(5),
        ]);
        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Lead2 Beta',
            'status' => 'new',
            'source' => 'Meta Ads',
            'created_at' => Carbon::now()->subDays(2),
        ]);

        Deal::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise Deal A',
            'value' => 15000,
            'stage' => 'won',
            'probability' => 100,
            'won_at' => Carbon::now()->subDays(3),
            'created_at' => Carbon::now()->subDays(10),
        ]);
        Deal::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Enterprise Deal B',
            'value' => 25000,
            'stage' => 'proposal',
            'probability' => 60,
            'created_at' => Carbon::now()->subDays(4),
        ]);

        MarketingCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Google Search Q4',
            'channel' => 'google_ads',
            'spend_amount' => 1200,
            'spend_type' => 'provider_reported',
            'status' => 'running',
            'created_at' => Carbon::now()->subDays(7),
        ]);
        MarketingCampaign::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Influencer Outreach',
            'channel' => 'influencer',
            'spend_amount' => 800,
            'spend_type' => 'manual_estimate',
            'status' => 'completed',
            'created_at' => Carbon::now()->subDays(8),
        ]);

        SupportTicket::create([
            'tenant_id' => $this->tenantA->id,
            'ticket_number' => 'TCK-1001',
            'subject' => 'Billing query',
            'priority' => 'high',
            'status' => 'open',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/executive?from_date=' . Carbon::now()->subDays(15)->toDateString() . '&to_date=' . Carbon::now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'kpis' => [
                        'total_leads',
                        'new_leads',
                        'converted_leads',
                        'won_deal_value',
                        'pipeline_value',
                        'total_marketing_spend',
                        'provider_reported_spend',
                        'manual_estimated_spend',
                        'cost_per_lead',
                        'roas',
                        'open_support_tickets',
                    ],
                    'trends',
                ],
            ]);

        $data = $response->json('data.kpis');
        $this->assertEquals(2, $data['total_leads']);
        $this->assertEquals(1, $data['converted_leads']);
        $this->assertEquals(15000, $data['won_deal_value']);
        $this->assertEquals(25000, $data['pipeline_value']);
        $this->assertEquals(2000, $data['total_marketing_spend']);
        $this->assertEquals(1200, $data['provider_reported_spend']);
        $this->assertEquals(800, $data['manual_estimated_spend']);
        $this->assertEquals(1000, $data['cost_per_lead']); // 2000 / 2
        $this->assertEquals(7.5, $data['roas']); // 15000 / 2000
    }

    public function test_lead_analytics_returns_aging_buckets_response_time_and_conversion_funnel(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // Seed leads of different ages
        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Young Lead',
            'status' => 'new',
            'source' => 'Website',
            'created_at' => Carbon::now()->subDays(3),
        ]);
        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Medium Lead',
            'status' => 'qualified',
            'source' => 'Referral',
            'created_at' => Carbon::now()->subDays(10),
        ]);
        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Old Lead',
            'status' => 'lost',
            'lost_reason' => 'Budget constraints',
            'source' => 'Cold Email',
            'created_at' => Carbon::now()->subDays(35),
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/leads?from_date=' . Carbon::now()->subDays(40)->toDateString() . '&to_date=' . Carbon::now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(3, $data['total_leads']);
        $this->assertEquals(1, $data['qualified_leads']);
        $this->assertEquals(1, $data['lost_leads']);
        $this->assertArrayHasKey('aging_buckets', $data);
        $this->assertArrayHasKey('0_7_days', $data['aging_buckets']);
        $this->assertArrayHasKey('8_14_days', $data['aging_buckets']);
        $this->assertArrayHasKey('30_plus_days', $data['aging_buckets']);
        $this->assertArrayHasKey('lost_reasons', $data);
        $this->assertEquals('Budget constraints', $data['lost_reasons'][0]['reason']);
    }

    public function test_sales_analytics_calculates_weighted_forecast_win_rate_and_cycle_duration(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // Won deal: cycle duration 10 days
        $dealWon = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Won Client Deal',
            'value' => 50000,
            'stage' => 'won',
            'probability' => 100,
            'won_at' => Carbon::now()->subDays(5),
        ]);
        $dealWon->timestamps = false;
        $dealWon->created_at = Carbon::now()->subDays(15);
        $dealWon->save();

        // Open deal with probability 50%
        Deal::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'In Negotiation Deal',
            'value' => 20000,
            'stage' => 'negotiation',
            'probability' => 50,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/sales?from_date=' . Carbon::now()->subDays(20)->toDateString() . '&to_date=' . Carbon::now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(2, $data['total_deals']);
        $this->assertEquals(1, $data['won_deals_count']);
        $this->assertEquals(50000, $data['won_value']);
        $this->assertEquals(20000, $data['pipeline_value']);
        $this->assertEquals(10000, $data['weighted_forecast']); // 20000 * 50%
        $this->assertEquals(10, $data['avg_sales_cycle_days']);
        $this->assertNotEmpty($data['stage_breakdown']);
    }

    public function test_attribution_engine_compares_first_touch_last_touch_linear_and_u_shaped_models(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // Create a deal won at $10,000
        $deal = Deal::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Multi-Touch Enterprise Deal',
            'value' => 10000,
            'stage' => 'won',
            'probability' => 100,
            'won_at' => Carbon::now()->subDays(1),
            'created_at' => Carbon::now()->subDays(20),
        ]);

        // Record 3 touchpoints along the journey
        // 1. First Touch: Google Ads (20 days ago)
        AttributionTouchpoint::create([
            'tenant_id' => $this->tenantA->id,
            'deal_id' => $deal->id,
            'touchpoint_type' => 'ad_click',
            'channel' => 'google_ads',
            'utm_source' => 'google',
            'utm_campaign' => 'q4_search',
            'touchpoint_timestamp' => Carbon::now()->subDays(20),
        ]);

        // 2. Middle Touch: Organic Blog (10 days ago)
        AttributionTouchpoint::create([
            'tenant_id' => $this->tenantA->id,
            'deal_id' => $deal->id,
            'touchpoint_type' => 'page_view',
            'channel' => 'organic',
            'utm_source' => 'organic',
            'touchpoint_timestamp' => Carbon::now()->subDays(10),
        ]);

        // 3. Last Touch: WhatsApp Conversation (2 days ago)
        AttributionTouchpoint::create([
            'tenant_id' => $this->tenantA->id,
            'deal_id' => $deal->id,
            'touchpoint_type' => 'chat_message',
            'channel' => 'whatsapp',
            'utm_source' => 'whatsapp',
            'touchpoint_timestamp' => Carbon::now()->subDays(2),
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/attribution?from_date=' . Carbon::now()->subDays(30)->toDateString() . '&to_date=' . Carbon::now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(10000, $data['total_won_revenue']);
        $this->assertArrayHasKey('attribution_notice', $data);
        $this->assertStringContainsString('Attribution models represent algorithmic estimates', $data['attribution_notice']);

        $channels = collect($data['attribution_model_comparison'])->keyBy('channel');

        // First touch channel is Google: receives 10,000 in first-touch
        $this->assertEquals(10000, $channels['Google']['first_touch_revenue']);
        $this->assertEquals(0, $channels['Google']['last_touch_revenue']);

        // Last touch channel is Whatsapp: receives 10,000 in last-touch
        $this->assertEquals(10000, $channels['Whatsapp']['last_touch_revenue']);
        $this->assertEquals(0, $channels['Whatsapp']['first_touch_revenue']);

        // Linear: 10000 / 3 = 3333.33 for each channel
        $this->assertEquals(3333.33, $channels['Google']['linear_revenue']);
        $this->assertEquals(3333.33, $channels['Organic']['linear_revenue']);
        $this->assertEquals(3333.33, $channels['Whatsapp']['linear_revenue']);

        // U-shaped (3 touches): 40% (4000) first, 40% (4000) last, 20% (2000) middle
        $this->assertEquals(4000, $channels['Google']['u_shaped_revenue']);
        $this->assertEquals(2000, $channels['Organic']['u_shaped_revenue']);
        $this->assertEquals(4000, $channels['Whatsapp']['u_shaped_revenue']);
    }

    public function test_record_touchpoint_api_stores_touchpoint_with_utm_metadata(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $payload = [
            'touchpoint_type' => 'ad_click',
            'channel' => 'meta_ads',
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer_promo',
            'metadata' => ['device' => 'mobile', 'browser' => 'Chrome'],
        ];

        $response = $this->postJson('/api/v1/crm/analytics/attribution/touchpoint', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.utm_source', 'facebook')
            ->assertJsonPath('data.utm_campaign', 'summer_promo');

        $this->assertDatabaseHas('attribution_touchpoints', [
            'tenant_id' => $this->tenantA->id,
            'utm_source' => 'facebook',
            'utm_campaign' => 'summer_promo',
        ]);
    }

    public function test_report_export_service_generates_csv_and_filtered_columns(): void
    {
        Sanctum::actingAs($this->adminUserA);

        Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'ReportLead Test',
            'email' => 'reportlead@example.com',
            'source' => 'Inbound',
            'status' => 'new',
            'score' => 85,
        ]);

        $response = $this->get('/api/v1/crm/analytics/export?report_type=leads&format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertStringContainsString('reportlead@example.com', $content);
        $this->assertStringContainsString('ReportLead', $content);
    }

    public function test_saved_reports_crud_and_tenant_isolation(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // Create saved report for Tenant A
        $createResponse = $this->postJson('/api/v1/crm/analytics/saved-reports', [
            'title' => 'Weekly Executive KPI Snapshot',
            'description' => 'Summary of conversions and ROAS',
            'report_type' => 'executive_summary',
            'filters' => ['from_date' => '2026-01-01', 'to_date' => '2026-01-31'],
            'is_scheduled' => true,
            'schedule_frequency' => 'weekly',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Weekly Executive KPI Snapshot');

        $reportId = $createResponse->json('data.id');

        // Verify Tenant A can see it
        $listResponseA = $this->getJson('/api/v1/crm/analytics/saved-reports');
        $listResponseA->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Verify Tenant B cannot see it (Multi-Tenant Isolation)
        Sanctum::actingAs($this->adminUserB);
        $listResponseB = $this->getJson('/api/v1/crm/analytics/saved-reports');
        $listResponseB->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Delete report as Tenant A
        Sanctum::actingAs($this->adminUserA);
        $deleteResponse = $this->deleteJson('/api/v1/crm/analytics/saved-reports/' . $reportId);
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('saved_reports', ['id' => $reportId]);
    }
}
