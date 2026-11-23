<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\PlatformAdmin\Models\IndustryTemplate;
use App\Domains\PlatformAdmin\Models\PlatformAnnouncement;
use App\Domains\PlatformAdmin\Models\PlatformBackupRecord;
use App\Domains\PlatformAdmin\Models\TenantAppliedTemplate;
use App\Domains\PlatformAdmin\Services\IndustryTemplateService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiTenantSaasSuperAdminAndTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create standard tenant and tenant admin
        $this->tenant = Tenant::create([
            'name' => 'Apex Cloud Systems India',
            'slug' => 'apex-cloud-systems',
            'status' => 'active',
            'subscription_tier' => 'pro',
            'state_code' => '27',
            'gstin' => '27ABCDE1234F1Z5',
        ]);

        $this->adminUser = User::factory()->create([
            'email' => 'founder@apexcloud.in',
            'current_tenant_id' => $this->tenant->id,
            'is_platform_admin' => false,
        ]);

        $this->adminUser->workspaces()->attach($this->tenant->id, [
            'role' => 'Business Admin',
        ]);

        // 2. Create Platform Super Admin
        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@crmplatform.in',
            'current_tenant_id' => $this->tenant->id,
            'is_platform_admin' => true,
            'platform_role' => 'superadmin',
        ]);
    }

    /**
     * Test 1: Public SaaS Onboarding & Organization Registration
     */
    public function test_saas_public_registration_and_onboarding(): void
    {
        $payload = [
            'organization_name' => 'Svastha Healthcare Group',
            'owner_name' => 'Dr. Ramesh Sharma',
            'email' => 'dr.ramesh@svasthahealth.in',
            'password' => 'Password@1234',
            'phone' => '+919876543210',
            'business_type' => 'Healthcare & Diagnostic',
            'industry_template_code' => 'healthcare',
            'plan_code' => 'pro',
            'billing_cycle' => 'monthly',
            'timezone' => 'Asia/Kolkata',
            'state_code' => '29',
            'gstin' => '29AABCS1429B1ZB',
        ];

        $response = $this->postJson('/api/v1/saas/onboarding/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.name', 'Svastha Healthcare Group')
            ->assertJsonPath('data.user.email', 'dr.ramesh@svasthahealth.in');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Svastha Healthcare Group',
            'industry_template_code' => 'healthcare',
        ]);

        $newTenant = Tenant::where('name', 'Svastha Healthcare Group')->first();
        $this->assertNotNull($newTenant);
        $this->assertEquals('healthcare', $newTenant->industry_template_code);
    }

    /**
     * Test 2: Industry Templates Listing & Application (12 Verticals Verified)
     */
    public function test_industry_templates_listing_and_application(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Fetch public/tenant templates catalog
        $response = $this->getJson('/api/v1/saas/templates');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $templates = $response->json('data');
        $this->assertGreaterThanOrEqual(12, count($templates));

        $codes = collect($templates)->pluck('template_code')->toArray();
        $expectedCodes = [
            'real_estate', 'education', 'healthcare', 'it_agencies',
            'digital_marketing', 'automobile', 'insurance', 'travel_tourism',
            'manufacturing', 'retail', 'consultancy', 'coaching_centres'
        ];

        foreach ($expectedCodes as $code) {
            $this->assertContains($code, $codes, "Expected template code {$code} to exist in catalog.");
        }

        // 2. Apply Real Estate template to tenant
        $applyResponse = $this->postJson('/api/v1/saas/templates/apply', [
            'template_code' => 'real_estate',
            'replace_existing' => true,
        ]);

        $applyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.template.template_code', 'real_estate');

        // Check active template
        $currentResponse = $this->getJson('/api/v1/saas/templates/current');
        $currentResponse->assertStatus(200)
            ->assertJsonPath('data.template.template_code', 'real_estate');

        // Verify tenant updated
        $this->tenant->refresh();
        $this->assertEquals('real_estate', $this->tenant->industry_template_code);
    }

    /**
     * Test 3: Centralized Feature Entitlement 5-Tier Engine
     */
    public function test_feature_entitlement_engine(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Get full catalog of entitlements
        $response = $this->getJson('/api/v1/saas/entitlements');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'plan_code',
                    'is_suspended',
                    'entitlements' => [
                        'leads',
                        'deals',
                        'gst_invoicing',
                        'multi_branch',
                        'workflow_automation',
                    ],
                ],
            ]);

        // 2. Verify single feature
        $verifyResponse = $this->postJson('/api/v1/saas/entitlements/verify', [
            'feature_key' => 'gst_invoicing',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.permitted', true);
    }

    /**
     * Test 4: Platform Super Admin Overview & Indian GST SAC 998313 Revenue Metrics
     */
    public function test_platform_super_admin_overview_and_revenue(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Overview Telemetry
        $overviewResponse = $this->getJson('/api/v1/platform/console/overview');

        $overviewResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_tenants',
                        'active_tenants',
                        'trialing_tenants',
                        'suspended_tenants',
                    ],
                    'mrr_inr',
                    'arr_inr',
                    'system_health' => [
                        'database',
                        'storage_writable',
                        'php_version',
                    ],
                ],
            ]);

        // 2. Revenue Report & SAC 998313 Breakdown
        $revenueResponse = $this->getJson('/api/v1/platform/console/revenue');

        $revenueResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.gst_tax_breakdown.hsn_sac', '998313')
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_invoiced_inr',
                        'total_collected_inr',
                        'mrr_inr',
                        'arr_inr',
                    ],
                    'gst_tax_breakdown' => [
                        'hsn_sac',
                        'cgst_9_pct',
                        'sgst_9_pct',
                        'igst_18_pct',
                        'total_gst',
                    ],
                ],
            ]);
    }

    /**
     * Test 5: Tenant Lifecycle Management (Suspension & Reactivation)
     */
    public function test_tenant_suspension_and_reactivation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Suspend tenant
        $suspendResponse = $this->postJson("/api/v1/platform/console/tenants/{$this->tenant->id}/suspend", [
            'reason' => 'Compliance audit hold for PAN/GSTIN verification',
        ]);

        $suspendResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.status', 'suspended');

        $this->tenant->refresh();
        $this->assertEquals('suspended', $this->tenant->status);
        $this->assertNotNull($this->tenant->suspended_at);

        // 2. Verify that tenant user is denied feature execution
        Sanctum::actingAs($this->adminUser);
        $denialResponse = $this->postJson('/api/v1/saas/entitlements/verify', [
            'feature_key' => 'leads',
        ]);

        $denialResponse->assertStatus(200)
            ->assertJsonPath('data.permitted', false)
            ->assertJsonPath('data.reason', 'Tenant organization is currently suspended.');

        // 3. Super Admin reactivates tenant
        Sanctum::actingAs($this->superAdmin);
        $reactivateResponse = $this->postJson("/api/v1/platform/console/tenants/{$this->tenant->id}/reactivate");

        $reactivateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.status', 'active');

        $this->tenant->refresh();
        $this->assertEquals('active', $this->tenant->status);
        $this->assertNull($this->tenant->suspended_at);
    }

    /**
     * Test 6: Platform Announcements Dispatch & Broadcasting
     */
    public function test_platform_announcements_broadcast(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $announcementPayload = [
            'title' => 'Scheduled Indian GST System Upgrade',
            'message' => 'The GST portal connection will undergo scheduled maintenance this Sunday from 02:00 to 04:00 IST.',
            'type' => 'warning',
            'target_audience' => 'all',
            'action_url' => 'https://status.crmplatform.in',
        ];

        $postResponse = $this->postJson('/api/v1/platform/console/announcements', $announcementPayload);

        $postResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Scheduled Indian GST System Upgrade');

        // Check announcement retrieval by tenant user
        Sanctum::actingAs($this->adminUser);
        $listResponse = $this->getJson('/api/v1/platform/announcements');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $listResponse->json('data');
        $this->assertGreaterThanOrEqual(1, count($items));
        $this->assertEquals('Scheduled Indian GST System Upgrade', $items[0]['title']);
    }

    /**
     * Test 7: Encrypted Platform Automated Backup Trigger
     */
    public function test_encrypted_platform_backup_trigger(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $backupResponse = $this->postJson('/api/v1/platform/console/backups', [
            'backup_type' => 'full',
            'notes' => 'Pre-release security verification baseline snapshot',
        ]);

        $backupResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.backup_type', 'full')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.encryption_enabled', true);

        $backupId = $backupResponse->json('data.id');
        $this->assertDatabaseHas('platform_backup_records', [
            'id' => $backupId,
            'status' => 'completed',
        ]);
    }

    /**
     * Test 8: Cross-Tenant Data Isolation Security Gate
     */
    public function test_cross_tenant_data_isolation_security(): void
    {
        // 1. Tenant A (apex cloud) has a lead
        $leadTenantA = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ananya Roy',
            'first_name' => 'Ananya',
            'last_name' => 'Roy',
            'email' => 'ananya@royenterprises.in',
            'phone' => '+919988776655',
            'status' => 'new',
            'source' => 'Direct Inbound',
        ]);

        // 2. Tenant B (independent competitor)
        $tenantB = Tenant::create([
            'name' => 'Zenith Tech Solutions',
            'slug' => 'zenith-tech',
            'status' => 'active',
        ]);

        $userTenantB = User::factory()->create([
            'email' => 'founder@zenithtech.in',
            'current_tenant_id' => $tenantB->id,
            'is_platform_admin' => false,
        ]);

        $userTenantB->workspaces()->attach($tenantB->id, [
            'role' => 'Business Admin',
        ]);

        // Act as Tenant B
        Sanctum::actingAs($userTenantB);

        // Fetch leads for Tenant B
        $leadsResponse = $this->getJson('/api/v1/crm/leads');
        $leadsResponse->assertStatus(200);

        $leadsData = $leadsResponse->json('data') ?? [];
        $leadIds = collect($leadsData)->pluck('id')->toArray();

        // Strict assertion: Tenant B MUST NOT see Tenant A's lead
        $this->assertNotContains($leadTenantA->id, $leadIds, 'Security violation: Tenant B accessed Tenant A record!');

        // Try direct access to Tenant A lead by Tenant B
        $directResponse = $this->getJson("/api/v1/crm/leads/{$leadTenantA->id}");
        $this->assertTrue(
            in_array($directResponse->status(), [403, 404]),
            'Expected 403 or 404 when querying another tenant record directly'
        );
    }
}
