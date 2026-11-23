<?php

namespace Tests\Feature;

use App\Domains\Billing\Models\SubscriptionPlan;
use App\Domains\PlatformAdmin\Models\PlatformAiProvider;
use App\Domains\PlatformAdmin\Models\PlatformAuditLog;
use App\Domains\PlatformAdmin\Models\PlatformFeatureFlag;
use App\Domains\PlatformAdmin\Models\PlatformSupportAccessLog;
use App\Domains\PlatformAdmin\Models\PlatformSystemSetting;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSuperAdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected User $tenantAdmin;
    protected User $superAdmin;
    protected User $supportStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'subscription_tier' => 'starter',
            'status' => 'active',
        ]);

        $this->tenantAdmin = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alice Business Admin',
            'email' => 'alice@acme.com',
            'password' => bcrypt('Password123!'),
            'role' => 'Business Admin',
            'is_platform_admin' => false,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Root Super Admin',
            'email' => 'superadmin@crmplatform.io',
            'password' => bcrypt('SuperSecure999!'),
            'role' => 'Platform Super Admin',
            'is_platform_admin' => true,
            'platform_role' => 'super_admin',
        ]);

        $this->supportStaff = User::create([
            'name' => 'Sarah Support Ops',
            'email' => 'sarah.support@crmplatform.io',
            'password' => bcrypt('SupportSecure123!'),
            'role' => 'Support Executive',
            'is_platform_admin' => true,
            'platform_role' => 'platform_support',
        ]);
    }

    public function test_tenant_business_admin_cannot_access_platform_super_admin_routes(): void
    {
        Sanctum::actingAs($this->tenantAdmin);

        $response1 = $this->getJson('/api/v1/platform/overview');
        $response1->assertStatus(403)
            ->assertJsonPath('success', false);

        $response2 = $this->getJson('/api/v1/platform/tenants');
        $response2->assertStatus(403);

        $response3 = $this->getJson('/api/v1/platform/providers');
        $response3->assertStatus(403);
    }

    public function test_super_admin_can_access_platform_overview_metrics(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/v1/platform/overview');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'tenants' => ['total', 'active', 'trialing', 'suspended', 'cancelled', 'total_users'],
                    'financials' => ['active_subscriptions', 'mrr', 'arr', 'total_collections', 'retail_usage_billed', 'wholesale_provider_cost', 'gross_profit', 'gross_margin_percent'],
                    'usage_volumes' => ['ai_tokens', 'calling_minutes', 'whatsapp_messages', 'sms_segments', 'email_volume'],
                    'infrastructure' => ['failed_integrations', 'failed_jobs', 'system_health'],
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['tenants']['total']);
        $this->assertEquals('healthy', $data['infrastructure']['system_health']['database']);
    }

    public function test_super_admin_can_suspend_and_restore_tenant_with_audit_logging(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Suspend tenant
        $suspendRes = $this->postJson("/api/v1/platform/tenants/{$this->tenantA->id}/status", [
            'status' => 'suspended',
            'reason' => 'Payment default after dunning expiry',
        ]);

        $suspendRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'suspended');

        $this->tenantA->refresh();
        $this->assertEquals('suspended', $this->tenantA->status);

        // Verify audit log created
        $log1 = PlatformAuditLog::where('action', 'tenant.suspended')
            ->where('target_id', (string) $this->tenantA->id)
            ->first();
        $this->assertNotNull($log1);
        $this->assertEquals('Root Super Admin', $log1->admin_name);
        $this->assertEquals('Payment default after dunning expiry', $log1->details['reason']);

        // 2. Restore tenant
        $restoreRes = $this->postJson("/api/v1/platform/tenants/{$this->tenantA->id}/status", [
            'status' => 'active',
            'reason' => 'Billing settled via manual bank transfer',
        ]);

        $restoreRes->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $this->tenantA->refresh();
        $this->assertEquals('active', $this->tenantA->status);

        $log2 = PlatformAuditLog::where('action', 'tenant.active')
            ->where('target_id', (string) $this->tenantA->id)
            ->first();
        $this->assertNotNull($log2);
    }

    public function test_unauthorized_platform_role_cannot_modify_billing_plans_or_providers(): void
    {
        // Sarah is 'platform_support'
        Sanctum::actingAs($this->supportStaff);

        // 1. Can view tenants
        $viewRes = $this->getJson('/api/v1/platform/tenants');
        $viewRes->assertStatus(200);

        // 2. But CANNOT create a commercial billing plan (requires 'billing' platform capability)
        $planRes = $this->postJson('/api/v1/platform/billing/plans', [
            'name' => 'Hacker Tier',
            'code' => 'hacker_plan',
            'billing_cycle' => 'monthly',
            'price' => 0.00,
        ]);
        $planRes->assertStatus(403)
            ->assertJsonPath('message', "Forbidden. Your platform role does not possess the 'billing' administrative capability.");

        // 3. And CANNOT update provider credentials (requires 'provider' platform capability)
        $provRes = $this->putJson('/api/v1/platform/providers/gemini', [
            'name' => 'Compromised Provider',
            'credentials' => 'fake_secret_key',
        ]);
        $provRes->assertStatus(403)
            ->assertJsonPath('message', "Forbidden. Your platform role does not possess the 'provider' administrative capability.");
    }

    public function test_breakglass_support_access_requires_justification_and_creates_expiring_session(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Rejects without justification reason
        $failRes = $this->postJson("/api/v1/platform/tenants/{$this->tenantA->id}/support-access", [
            'justification_reason' => '',
        ]);
        $failRes->assertStatus(422);

        // 2. Success with detailed justification
        $passRes = $this->postJson("/api/v1/platform/tenants/{$this->tenantA->id}/support-access", [
            'justification_reason' => 'Ticket #INC-8942: Investigating customer reported WhatsApp webhook delivery failure',
            'duration_minutes' => 60,
        ]);

        $passRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'session_id',
                    'tenant_id',
                    'tenant_name',
                    'access_token',
                    'expires_at',
                    'justification_reason',
                ],
            ]);

        $sessionId = $passRes->json('data.session_id');

        // Verify session logged in database
        $session = PlatformSupportAccessLog::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals('active', $session->status);
        $this->assertEquals($this->tenantA->id, $session->tenant_id);

        // Verify security audit log recorded
        $audit = PlatformAuditLog::where('action', 'support.breakglass_start')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('Ticket #INC-8942: Investigating customer reported WhatsApp webhook delivery failure', $audit->details['justification']);

        // 3. Revoke breakglass session
        $revokeRes = $this->postJson("/api/v1/platform/support-access/{$sessionId}/revoke");
        $revokeRes->assertStatus(200);

        $session->refresh();
        $this->assertEquals('revoked', $session->status);
    }

    public function test_provider_credentials_are_encrypted_at_rest_and_hidden_from_api(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Ensure default providers seeded
        $this->getJson('/api/v1/platform/providers');

        $rawKey = 'AIzaSySecretTestKeyProduction999';

        $updateRes = $this->putJson('/api/v1/platform/providers/gemini', [
            'credentials' => ['api_key' => $rawKey],
            'default_model' => 'gemini-2.0-flash',
        ]);

        $updateRes->assertStatus(200);

        // 1. API response must NEVER reveal the plain text secret
        $data = $updateRes->json('data');
        $this->assertArrayNotHasKey('encrypted_credentials', $data);
        $this->assertTrue($data['is_configured']);

        // 2. Verify stored in database encrypted at rest
        $rawDbRow = DB::table('platform_ai_providers')->where('provider_key', 'gemini')->first();
        $this->assertNotEquals($rawKey, $rawDbRow->encrypted_credentials);
        $this->assertStringNotContainsString($rawKey, $rawDbRow->encrypted_credentials);

        // 3. Test health check execution
        $healthRes = $this->postJson('/api/v1/platform/providers/gemini/test-health');
        $healthRes->assertStatus(200)
            ->assertJsonPath('data.status', 'healthy');
    }

    public function test_feature_flags_and_maintenance_mode_controls(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Feature flags listing & toggling
        $flagsRes = $this->getJson('/api/v1/platform/system/feature-flags');
        $flagsRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $toggleRes = $this->putJson('/api/v1/platform/system/feature-flags/whatsapp_flow_builder', [
            'is_enabled' => true,
        ]);
        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.is_enabled', true);

        $flag = PlatformFeatureFlag::where('flag_key', 'whatsapp_flow_builder')->first();
        $this->assertTrue($flag->is_enabled);

        // 2. Maintenance mode toggle
        $maintRes = $this->postJson('/api/v1/platform/system/maintenance', [
            'is_enabled' => true,
            'message' => 'Scheduled core database upgrade.',
            'bypass_secret' => 'maint_bypass_secret_9988',
        ]);

        $maintRes->assertStatus(200)
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.bypass_secret', 'maint_bypass_secret_9988');

        $statusRes = $this->getJson('/api/v1/platform/system/maintenance');
        $statusRes->assertStatus(200)
            ->assertJsonPath('data.is_enabled', true);

        // Disable maintenance mode
        $disableRes = $this->postJson('/api/v1/platform/system/maintenance', [
            'is_enabled' => false,
        ]);
        $disableRes->assertStatus(200)
            ->assertJsonPath('data.is_enabled', false);

        // Verify audit logs
        $maintAudit = PlatformAuditLog::where('action', 'system.maintenance_enable')->first();
        $this->assertNotNull($maintAudit);
    }
}
