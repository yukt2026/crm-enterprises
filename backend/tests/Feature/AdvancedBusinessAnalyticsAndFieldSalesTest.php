<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\CustomAnalyticsAlert;
use App\Domains\Analytics\Models\DashboardWidgetLayout;
use App\Domains\Analytics\Models\FieldSalesVisit;
use App\Domains\Analytics\Models\SavedReport;
use App\Domains\Analytics\Services\IndiaFirstService;
use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\HR\Models\Employee;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Projects\Models\Project;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedBusinessAnalyticsAndFieldSalesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Branch $branch;
    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Bharat BI Enterprise',
            'slug' => 'bharat-bi-enterprise',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create([
            'email' => 'executive@bharatbi.in',
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->user->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
        ]);

        Sanctum::actingAs($this->user);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mumbai Head Office',
            'code' => 'BR-BOM-01',
            'status' => 'active',
        ]);

        $this->employee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-001',
            'name' => 'Rajesh Sharma',
            'first_name' => 'Rajesh',
            'last_name' => 'Sharma',
            'email' => 'rajesh.sharma@bharatbi.in',
            'department' => 'Sales',
            'designation' => 'Senior Field Sales Manager',
            'status' => 'active',
            'hire_date' => Carbon::now()->subMonths(6),
        ]);
    }

    /**
     * Test 1: Owner & Executive Dashboard Metrics with Actuals vs Forecast Distinction
     */
    public function test_owner_executive_dashboard_distinguishes_actuals_and_forecasts(): void
    {
        // Create Leads
        Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vikram Seth',
            'email' => 'vikram@seth.in',
            'status' => 'new',
            'source' => 'google_ads',
            'branch_id' => $this->branch->id,
        ]);
        Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Anita Desai',
            'email' => 'anita@desai.in',
            'status' => 'converted',
            'source' => 'referral',
            'branch_id' => $this->branch->id,
        ]);

        // Create Deals (Won vs Pipeline)
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Enterprise CRM Cloud Deal',
            'value' => 500000, // ₹5 Lakh
            'stage' => 'won',
            'branch_id' => $this->branch->id,
        ]);
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'IT Infrastructure Overhaul',
            'value' => 1200000, // ₹12 Lakh
            'probability' => 80,
            'stage' => 'proposal',
            'branch_id' => $this->branch->id,
        ]);

        // Create Invoices & Payments
        $inv = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-2026-001',
            'total_amount' => 500000,
            'balance_due' => 200000,
            'outstanding_amount' => 200000,
            'status' => 'partially_paid',
            'branch_id' => $this->branch->id,
        ]);

        Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $inv->id,
            'amount' => 300000,
            'status' => 'success',
            'payment_method' => 'upi',
        ]);

        // Create Expense
        Expense::create([
            'tenant_id' => $this->tenant->id,
            'expense_number' => 'EXP-2026-001',
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'category' => 'travel',
            'title' => 'Client Visit Travel',
            'amount' => 25000,
            'expense_date' => Carbon::now()->toDateString(),
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/owner-dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'actuals' => [
                        'total_leads',
                        'new_leads',
                        'converted_leads',
                        'won_revenue',
                        'won_revenue_inr',
                        'collected_revenue',
                        'invoiced_sales',
                        'monthly_expenses',
                        'net_profit',
                    ],
                    'forecasts' => [
                        'pipeline_value',
                        'pipeline_value_short',
                        'weighted_pipeline_forecast',
                        'committed_forecast',
                        'forecast_disclaimer',
                    ],
                    'performance' => [
                        'branches',
                        'employees',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['actuals']['total_leads']);
        $this->assertEquals(500000, $data['actuals']['won_revenue']);
        $this->assertEquals(300000, $data['actuals']['collected_revenue']);
        $this->assertEquals(1200000, $data['forecasts']['pipeline_value']);
        $this->assertStringContainsString('₹', $data['actuals']['won_revenue_inr']);
    }

    /**
     * Test 2: Dashboard Widget Layouts CRUD
     */
    public function test_dashboard_widget_layouts_crud(): void
    {
        // 1. Create Layout
        $createRes = $this->postJson('/api/v1/crm/analytics/dashboard-layouts', [
            'name' => 'Custom Executive Cockpit',
            'role' => 'owner',
            'is_default' => true,
            'widgets' => [
                ['id' => 'w1', 'type' => 'kpi_card', 'metric' => 'won_revenue', 'w' => 4, 'h' => 2],
                ['id' => 'w2', 'type' => 'chart', 'metric' => 'monthly_sales', 'chart_type' => 'bar', 'w' => 8, 'h' => 4],
            ],
            'filters' => ['date_range' => '30d'],
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Custom Executive Cockpit')
            ->assertJsonPath('data.is_default', true);

        $layoutId = $createRes->json('data.id');

        // 2. Get Layouts
        $getRes = $this->getJson('/api/v1/crm/analytics/dashboard-layouts');
        $getRes->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertCount(1, $getRes->json('data'));

        // 3. Update Layout
        $updateRes = $this->putJson("/api/v1/crm/analytics/dashboard-layouts/{$layoutId}", [
            'name' => 'Renamed Executive View',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed Executive View');

        // 4. Delete Layout
        $delRes = $this->deleteJson("/api/v1/crm/analytics/dashboard-layouts/{$layoutId}");
        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('dashboard_widget_layouts', ['id' => $layoutId]);
    }

    /**
     * Test 3: Custom Report Builder Catalog & Preview
     */
    public function test_custom_report_builder_catalog_and_preview(): void
    {
        // Catalog
        $catRes = $this->getJson('/api/v1/crm/analytics/report-builder/catalog');
        $catRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['leads', 'deals', 'invoices', 'customers', 'tickets', 'field_sales'],
            ]);

        // Create sample deals for preview
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'SaaS Subscription Tier A',
            'value' => 250000,
            'stage' => 'won',
        ]);
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Consulting Package B',
            'value' => 450000,
            'stage' => 'won',
        ]);

        // Preview query
        $prevRes = $this->postJson('/api/v1/crm/analytics/report-builder/preview', [
            'module' => 'deals',
            'selected_columns' => ['id', 'name', 'value', 'stage'],
            'filters' => [
                ['field' => 'stage', 'operator' => '=', 'value' => 'won'],
            ],
            'aggregations' => [
                'sum' => ['value'],
                'avg' => ['value'],
            ],
            'group_by' => 'stage',
        ]);

        $prevRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_records', 2);
        $this->assertEquals(700000, $prevRes->json('data.aggregations.sum_value'));
        $this->assertEquals(350000, $prevRes->json('data.aggregations.avg_value'));
    }

    /**
     * Test 4: Custom Report Save, Export CSV & Schedule
     */
    public function test_custom_report_save_export_and_schedule(): void
    {
        $saveRes = $this->postJson('/api/v1/crm/analytics/report-builder/save', [
            'title' => 'Quarterly Deals Report',
            'module' => 'deals',
            'selected_columns' => ['id', 'name', 'value'],
            'group_by' => 'stage',
            'is_scheduled' => true,
            'schedule_frequency' => 'weekly',
            'schedule_recipients' => ['cfo@bharatbi.in'],
        ]);

        $saveRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Quarterly Deals Report')
            ->assertJsonPath('data.is_scheduled', true);

        $reportId = $saveRes->json('data.id');

        // Export CSV
        $exportRes = $this->postJson('/api/v1/crm/analytics/report-builder/export', [
            'module' => 'deals',
            'selected_columns' => ['id', 'name', 'value'],
        ]);

        $exportRes->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $exportRes->headers->get('content-type'));

        // Schedule Update
        $schedRes = $this->postJson("/api/v1/crm/analytics/report-builder/{$reportId}/schedule", [
            'is_scheduled' => true,
            'schedule_frequency' => 'monthly',
            'schedule_recipients' => ['exec@bharatbi.in', 'cfo@bharatbi.in'],
        ]);

        $schedRes->assertStatus(200)
            ->assertJsonPath('data.schedule_frequency', 'monthly');
    }

    /**
     * Test 5: Sales Deep-Dive Analytics
     */
    public function test_sales_deep_dive_analytics(): void
    {
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High Value Enterprise Deal',
            'value' => 800000,
            'stage' => 'won',
            'branch_id' => $this->branch->id,
        ]);
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lost Competitor Deal',
            'value' => 300000,
            'stage' => 'lost',
            'lost_reason' => 'price_too_high',
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/sales-deep-dive');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'average_deal_value',
                    'average_deal_value_inr',
                    'sales_pipeline_value',
                    'weighted_forecast',
                    'sales_cycle_length_days',
                    'won_count',
                    'lost_count',
                    'lost_reasons_breakdown',
                    'branch_wise_sales',
                ],
            ]);

        $this->assertEquals(1, $response->json('data.won_count'));
        $this->assertEquals(1, $response->json('data.lost_count'));
    }

    /**
     * Test 6: Marketing Deep-Dive Analytics
     */
    public function test_marketing_deep_dive_analytics(): void
    {
        MarketingCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Google Ads Search Campaign',
            'channel' => 'google_ads',
            'spend_amount' => 50000,
            'leads_count' => 25,
            'cpl' => 2000,
            'roas' => 4.5,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/marketing-deep-dive');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_marketing_spend',
                    'total_marketing_spend_inr',
                    'cost_per_lead',
                    'cost_per_acquisition',
                    'campaign_performance',
                    'attribution_models_available',
                ],
            ]);

        $this->assertEquals(50000, $response->json('data.total_marketing_spend'));
    }

    /**
     * Test 7: Finance Deep-Dive Analytics & AR 4-Bracket Aging
     */
    public function test_finance_deep_dive_analytics(): void
    {
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-AR-001',
            'total_amount' => 150000,
            'balance_due' => 150000,
            'outstanding_amount' => 150000,
            'due_date' => Carbon::now()->subDays(10), // 0-30 days bracket
            'status' => 'sent',
        ]);
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-AR-002',
            'total_amount' => 250000,
            'balance_due' => 250000,
            'outstanding_amount' => 250000,
            'due_date' => Carbon::now()->subDays(60), // 46-90 days bracket
            'status' => 'overdue',
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/finance-deep-dive');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'invoiced_revenue',
                    'collected_cash',
                    'total_receivables',
                    'total_expenses',
                    'net_cash_flow',
                    'ar_aging_brackets' => [
                        '0_to_30_days',
                        '31_to_45_days',
                        '46_to_90_days',
                        '90_plus_days',
                    ],
                    'privacy_notice',
                ],
            ]);

        $this->assertEquals(400000, $response->json('data.total_receivables'));
        $this->assertEquals(150000, $response->json('data.ar_aging_brackets.0_to_30_days'));
        $this->assertEquals(250000, $response->json('data.ar_aging_brackets.46_to_90_days'));
    }

    /**
     * Test 8: AI Business Insights Engine
     */
    public function test_ai_business_insights_generation(): void
    {
        // Create an overdue invoice to trigger aging alert
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-AGING-001',
            'total_amount' => 600000,
            'balance_due' => 600000,
            'outstanding_amount' => 600000,
            'status' => 'overdue',
            'due_date' => Carbon::now()->subDays(40),
        ]);

        // Create deal closing this week
        Deal::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Imminent Mega Deal',
            'value' => 1500000,
            'stage' => 'negotiation',
            'expected_close_date' => Carbon::now()->addDays(3),
        ]);

        $response = $this->getJson('/api/v1/crm/analytics/ai-insights');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_insights',
                    'generated_at',
                    'generated_at_ist',
                    'insights' => [
                        '*' => [
                            'insight_key',
                            'title',
                            'category',
                            'type',
                            'supporting_data',
                            'explanation',
                            'suggested_action',
                            'confidence',
                            'limitations',
                        ],
                    ],
                ],
            ]);

        $insights = $response->json('data.insights');
        $this->assertNotEmpty($insights);
        $keys = array_column($insights, 'insight_key');
        $this->assertTrue(in_array('approaching_deal_closures', $keys) || in_array('accounts_receivable_aging_alert', $keys));
    }

    /**
     * Test 9: Custom Analytics Alerts Rule & Evaluation
     */
    public function test_custom_analytics_alerts_rule_and_evaluation(): void
    {
        // Create Alert Rule
        $alertRes = $this->postJson('/api/v1/crm/analytics/alerts', [
            'name' => 'High Overdue Balances Alert',
            'metric_type' => 'overdue_payments',
            'condition_operator' => '>',
            'threshold_value' => 100000, // ₹1 Lakh
            'notification_channels' => ['in_app', 'email'],
            'is_active' => true,
        ]);

        $alertRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.metric_type', 'overdue_payments');

        // Create Overdue Invoice exceeding threshold
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-BREACH-001',
            'total_amount' => 200000,
            'balance_due' => 200000,
            'outstanding_amount' => 200000,
            'status' => 'overdue',
        ]);

        // Evaluate Alerts
        $evalRes = $this->postJson('/api/v1/crm/analytics/alerts/evaluate');
        $evalRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.triggered_count', 1);

        $triggered = $evalRes->json('data.triggered_alerts.0');
        $this->assertEquals('High Overdue Balances Alert', $triggered['name']);
        $this->assertGreaterThanOrEqual(200000, $triggered['current_value']);
    }

    /**
     * Test 10: India-First Localization & Formatting Capabilities
     */
    public function test_india_first_capabilities(): void
    {
        $response = $this->getJson('/api/v1/crm/india/settings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.currency.code', 'INR')
            ->assertJsonPath('data.currency.symbol', '₹')
            ->assertJsonPath('data.timezone.default', 'Asia/Kolkata')
            ->assertJsonStructure([
                'data' => [
                    'currency',
                    'timezone',
                    'holidays',
                    'languages' => [
                        'available',
                        'dictionaries',
                    ],
                ],
            ]);

        // Test IndiaFirstService utility directly
        $formattedInr = IndiaFirstService::formatInr(1234567.89);
        $this->assertEquals('₹12,34,567.89', $formattedInr);

        $shortInr = IndiaFirstService::formatShortInr(25000000);
        $this->assertEquals('₹2.50 Cr', $shortInr);

        $phone = IndiaFirstService::normalizeIndianPhoneNumber('9876543210');
        $this->assertEquals('+919876543210', $phone);
    }

    /**
     * Test 11: Field Sales Visit Scheduling, Transparent GPS Check-In & Check-Out
     */
    public function test_field_sales_visit_transparent_gps_lifecycle(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tata Consultancy Services',
            'email' => 'procurement@tcs.in',
            'phone' => '+912267789999',
            'city' => 'Mumbai',
        ]);

        // 1. Schedule Visit
        $scheduleRes = $this->postJson('/api/v1/crm/field-sales/visits', [
            'employee_id' => $this->employee->id,
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'title' => 'Quarterly Enterprise Review Meeting',
            'scheduled_at' => Carbon::now()->addHours(2)->toIso8601String(),
            'route_waypoints' => [
                ['lat' => 19.0760, 'lng' => 72.8777, 'label' => 'Bandra Kurla Complex'],
            ],
        ]);

        $scheduleRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'scheduled');

        $visitId = $scheduleRes->json('data.id');

        // 2. User-Initiated GPS Check-In
        $checkInRes = $this->postJson("/api/v1/crm/field-sales/visits/{$visitId}/check-in", [
            'latitude' => 19.0657,
            'longitude' => 72.8688,
            'address' => 'One BKC, Bandra East, Mumbai, Maharashtra 400051',
        ]);

        $checkInRes->assertStatus(200)
            ->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.check_in_lat', 19.0657);

        // 3. User-Initiated GPS Check-Out with Outcome, Notes, Follow-up Task & Travel Expense
        $checkOutRes = $this->postJson("/api/v1/crm/field-sales/visits/{$visitId}/check-out", [
            'check_out_lat' => 19.0665,
            'check_out_lng' => 72.8695,
            'check_out_address' => 'One BKC Exit Gate, Mumbai',
            'distance_travelled_km' => 12.5,
            'visit_notes' => 'Client approved cloud trial. Next meeting scheduled for security audit.',
            'outcome' => 'deal_closed',
            'follow_up_date' => Carbon::now()->addDays(5)->toDateString(),
            'travel_expense_amount' => 850.00,
        ]);

        $checkOutRes->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.outcome', 'deal_closed')
            ->assertJsonPath('data.distance_travelled_km', 12.5);
        $this->assertEquals(850, $checkOutRes->json('data.travel_expense_amount'));

        // Verify automated follow-up task and expense claim
        $visit = FieldSalesVisit::find($visitId);
        $this->assertNotNull($visit->follow_up_task_id);
        $this->assertNotNull($visit->travel_expense_id);
        $this->assertDatabaseHas('expenses', ['id' => $visit->travel_expense_id, 'amount' => 850.00]);
    }

    /**
     * Test 12: Field Sales Daily Visit Plan & Safe Offline Sync Deduplication
     */
    public function test_field_sales_daily_plan_and_offline_sync(): void
    {
        // 1. Get Daily Plan
        $planRes = $this->getJson("/api/v1/crm/field-sales/daily-plan?employee_id={$this->employee->id}");
        $planRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'date',
                    'date_formatted_ist',
                    'employee',
                    'total_visits_planned',
                    'visits',
                ],
            ]);

        // 2. Safe Offline Sync with UUID Deduplication
        $uuid1 = 'offline_visit_' . uniqid();
        $uuid2 = 'offline_visit_' . uniqid();

        $syncRes = $this->postJson('/api/v1/crm/field-sales/sync-offline', [
            'drafts' => [
                [
                    'offline_client_uuid' => $uuid1,
                    'employee_id' => $this->employee->id,
                    'title' => 'Offline Client Consultation 1',
                    'scheduled_at' => Carbon::now()->toIso8601String(),
                    'check_in_lat' => 19.0760,
                    'check_in_lng' => 72.8777,
                    'status' => 'completed',
                    'visit_notes' => 'Collected customer cheque.',
                    'outcome' => 'payment_collected',
                ],
                [
                    'offline_client_uuid' => $uuid2,
                    'employee_id' => $this->employee->id,
                    'title' => 'Offline Client Consultation 2',
                    'scheduled_at' => Carbon::now()->toIso8601String(),
                    'status' => 'scheduled',
                ],
            ],
        ]);

        $syncRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.synced_count', 2)
            ->assertJsonPath('data.duplicates_ignored', 0);

        // 3. Retry with same UUID to ensure deduplication
        $retrySyncRes = $this->postJson('/api/v1/crm/field-sales/sync-offline', [
            'drafts' => [
                [
                    'offline_client_uuid' => $uuid1,
                    'employee_id' => $this->employee->id,
                    'title' => 'Offline Client Consultation 1',
                    'scheduled_at' => Carbon::now()->toIso8601String(),
                ],
            ],
        ]);

        $retrySyncRes->assertStatus(200)
            ->assertJsonPath('data.synced_count', 0)
            ->assertJsonPath('data.duplicates_ignored', 1);
    }
}
