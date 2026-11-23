<?php

namespace Tests\Feature;

use App\Domains\Integrations\Models\OAuthState;
use App\Domains\Integrations\Models\OutboundWebhook;
use App\Domains\Integrations\Models\TenantApiKey;
use App\Domains\Integrations\Models\TenantIntegration;
use App\Domains\Integrations\Services\ApiKeyService;
use App\Domains\Integrations\Services\OutboundWebhookService;
use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationManagementPlatformTest extends TestCase
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
            'name' => 'Acme Integrations Hub',
            'domain' => 'acme-integrations.test',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Apex Competitor Tech',
            'domain' => 'apex-tech.test',
            'status' => 'active',
        ]);

        $this->adminUserA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'role' => 'Business Admin',
            'name' => 'Alice Integration Admin',
            'email' => 'alice@acme-integrations.test',
        ]);
        $this->adminUserA->workspaces()->attach($this->tenantA->id, ['role' => 'Business Admin', 'status' => 'active']);

        $this->adminUserB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'role' => 'Business Admin',
            'name' => 'Bob Competitor Admin',
            'email' => 'bob@apex-tech.test',
        ]);
        $this->adminUserB->workspaces()->attach($this->tenantB->id, ['role' => 'Business Admin', 'status' => 'active']);
    }

    public function test_integrations_directory_returns_supported_providers_and_statuses(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $response = $this->getJson('/api/v1/crm/integrations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(10, count($data));

        $providers = collect($data)->pluck('provider')->all();
        $this->assertContains('google_ads', $providers);
        $this->assertContains('meta_ads', $providers);
        $this->assertContains('razorpay', $providers);
        $this->assertContains('ga4', $providers);
        $this->assertContains('whatsapp_cloud', $providers);
        $this->assertContains('email_providers', $providers);
        $this->assertContains('sms_providers', $providers);
        $this->assertContains('telephony_providers', $providers);
        $this->assertContains('calendar_providers', $providers);
        $this->assertContains('cloud_storage', $providers);

        $first = $data[0];
        $this->assertArrayHasKey('supported_features', $first);
        $this->assertArrayHasKey('required_scopes', $first);
        $this->assertArrayHasKey('setup_instructions', $first);
        $this->assertArrayHasKey('limitations', $first);
    }

    public function test_oauth_state_generation_and_csrf_validation(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // 1. Generate OAuth state and redirect URL
        $urlResponse = $this->postJson('/api/v1/crm/integrations/oauth-url', [
            'provider' => 'google_ads',
            'redirect_uri' => 'https://acme-integrations.test/oauth/callback',
        ]);

        $urlResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $stateToken = $urlResponse->json('data.state_token');
        $this->assertNotEmpty($stateToken);

        $this->assertDatabaseHas('oauth_states', [
            'tenant_id' => $this->tenantA->id,
            'provider' => 'google_ads',
            'state_token' => $stateToken,
        ]);

        // 2. Reject callback with invalid state token (CSRF defense)
        $invalidCallback = $this->postJson('/api/v1/crm/integrations/oauth-callback', [
            'provider' => 'google_ads',
            'code' => 'valid_auth_code',
            'state' => 'forged_or_invalid_state_token',
            'redirect_uri' => 'https://acme-integrations.test/oauth/callback',
        ]);

        $invalidCallback->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired OAuth state token. Cross-site request verification failed.');

        // 3. Accept callback with valid state token
        $validCallback = $this->postJson('/api/v1/crm/integrations/oauth-callback', [
            'provider' => 'google_ads',
            'code' => '4/0AVn_valid_code_123',
            'state' => $stateToken,
            'redirect_uri' => 'https://acme-integrations.test/oauth/callback',
        ]);

        $validCallback->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.provider', 'google_ads');

        $this->assertDatabaseHas('tenant_integrations', [
            'tenant_id' => $this->tenantA->id,
            'provider' => 'google_ads',
            'status' => 'connected',
        ]);
    }

    public function test_credential_encryption_and_secrets_masking(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $rawApiKey = 'rzp_live_secret_key_84920194018241';
        $payload = [
            'provider' => 'razorpay',
            'name' => 'Corporate Razorpay Gateway',
            'credentials' => [
                'key_id' => 'rzp_live_abc123',
                'key_secret' => $rawApiKey,
            ],
            'metadata' => ['merchant_id' => 'M_849201'],
        ];

        $response = $this->postJson('/api/v1/crm/integrations/connect-credentials', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Verify API response never exposes raw key_secret
        $maskedCredentials = $response->json('data.masked_credentials');
        $this->assertNotEquals($rawApiKey, $maskedCredentials['key_secret']);
        $this->assertStringContainsString('••••••••', $maskedCredentials['key_secret']);

        // Verify DB stores encrypted payload rather than plaintext secret
        $rawDbRecord = DB::table('tenant_integrations')->where('tenant_id', $this->tenantA->id)->first();
        $this->assertStringNotContainsString($rawApiKey, $rawDbRecord->encrypted_credentials);
    }

    public function test_integration_sync_execution_and_log_recording(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $integration = TenantIntegration::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'meta_ads',
            'name' => 'Primary Meta Account',
            'status' => 'connected',
            'auth_type' => 'oauth2',
            'encrypted_credentials' => ['access_token' => 'meta_token_123'],
            'sync_frequency_minutes' => 60,
        ]);

        $response = $this->postJson("/api/v1/crm/integrations/{$integration->id}/sync");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items_processed', 28);

        $this->assertDatabaseHas('integration_sync_logs', [
            'tenant_id' => $this->tenantA->id,
            'integration_id' => $integration->id,
            'status' => 'success',
            'items_processed' => 28,
        ]);

        $integration->refresh();
        $this->assertNotNull($integration->last_sync_at);
        $this->assertNotNull($integration->next_sync_at);
    }

    public function test_tenant_api_key_lifecycle_scope_enforcement_and_rotation(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // 1. Create API key
        $createResponse = $this->postJson('/api/v1/crm/api-keys', [
            'name' => 'Backend Sync Service Key',
            'scopes' => ['leads:read', 'deals:read'],
            'rate_limit_per_minute' => 120,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $rawSecret = $createResponse->json('data.raw_secret');
        $keyId = $createResponse->json('data.api_key.id');
        $this->assertStringStartsWith('crm_live_', $rawSecret);

        // Verify key validator
        $apiKeyService = app(ApiKeyService::class);
        $validated = $apiKeyService->validateKey($rawSecret, 'leads:read');
        $this->assertNotNull($validated);
        $this->assertEquals($keyId, $validated->id);

        // Verify invalid scope rejection
        $invalidScope = $apiKeyService->validateKey($rawSecret, 'invoices:delete');
        $this->assertNull($invalidScope);

        // 2. Rotate API key
        $rotateResponse = $this->postJson("/api/v1/crm/api-keys/{$keyId}/rotate");
        $rotateResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $newSecret = $rotateResponse->json('data.raw_secret');
        $this->assertNotEquals($rawSecret, $newSecret);

        // Verify old key is now rejected
        $oldValidated = $apiKeyService->validateKey($rawSecret, 'leads:read');
        $this->assertNull($oldValidated);

        // Verify new key is accepted
        $newValidated = $apiKeyService->validateKey($newSecret, 'leads:read');
        $this->assertNotNull($newValidated);

        // 3. Revoke/Delete API key
        $newKeyId = $rotateResponse->json('data.api_key.id');
        $deleteResponse = $this->deleteJson("/api/v1/crm/api-keys/{$newKeyId}");
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $revokedValidated = $apiKeyService->validateKey($newSecret, 'leads:read');
        $this->assertNull($revokedValidated);
    }

    public function test_outbound_webhook_hmac_signature_generation_and_verification(): void
    {
        Sanctum::actingAs($this->adminUserA);

        Http::fake([
            'https://erp.partner.test/webhook' => Http::response(['received' => true], 200),
        ]);

        $webhookService = app(OutboundWebhookService::class);

        // 1. Create webhook subscription
        $createResponse = $this->postJson('/api/v1/crm/webhooks', [
            'name' => 'Fulfillment ERP Outbound Webhook',
            'url' => 'https://erp.partner.test/webhook',
            'events' => ['lead.created', 'deal.won'],
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $webhookId = $createResponse->json('data.id');
        $webhook = OutboundWebhook::find($webhookId);
        $this->assertStringStartsWith('whsec_', $webhook->signing_secret);

        // 2. Dispatch event
        $payload = ['lead_id' => 101, 'name' => 'John Doe', 'status' => 'qualified'];
        $dispatched = $webhookService->dispatchEvent($this->tenantA->id, 'lead.created', $payload);

        $this->assertCount(1, $dispatched);
        $log = $dispatched[0];
        $this->assertEquals('success', $log->status);

        // 3. Verify HMAC signature computation & verification
        $timestamp = time();
        $payloadJson = json_encode(['event_id' => 'evt_1', 'data' => $payload]);
        $signature = $webhookService->computeSignature($timestamp, $payloadJson, $webhook->signing_secret);
        $header = "t={$timestamp},v1={$signature}";

        $isValid = $webhookService->verifySignature($payloadJson, $header, $webhook->signing_secret);
        $this->assertTrue($isValid);

        // 4. Verify replay attack defense (reject expired timestamp beyond tolerance)
        $oldTimestamp = time() - 600; // 10 minutes ago
        $oldSignature = $webhookService->computeSignature($oldTimestamp, $payloadJson, $webhook->signing_secret);
        $oldHeader = "t={$oldTimestamp},v1={$oldSignature}";

        $isOldValid = $webhookService->verifySignature($payloadJson, $oldHeader, $webhook->signing_secret, 300);
        $this->assertFalse($isOldValid);
    }

    public function test_openapi_documentation_endpoint_returns_valid_spec(): void
    {
        Sanctum::actingAs($this->adminUserA);

        $response = $this->getJson('/api/v1/crm/docs/openapi.json');

        $response->assertStatus(200)
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'CRM Enterprises RESTful API & Webhook Platform');

        $paths = $response->json('paths');
        $this->assertArrayHasKey('/auth/login', $paths);
        $this->assertArrayHasKey('/crm/leads', $paths);
        $this->assertArrayHasKey('/crm/deals', $paths);
        $this->assertArrayHasKey('/crm/invoices', $paths);
        $this->assertArrayHasKey('/crm/webhooks', $paths);

        $components = $response->json('components');
        $this->assertArrayHasKey('securitySchemes', $components);
        $this->assertArrayHasKey('BearerAuth', $components['securitySchemes']);
        $this->assertArrayHasKey('ApiKeyAuth', $components['securitySchemes']);
    }

    public function test_strict_cross_tenant_isolation_on_integrations_keys_and_webhooks(): void
    {
        Sanctum::actingAs($this->adminUserA);

        // Tenant A creates integration, API key, and Webhook
        TenantIntegration::create([
            'tenant_id' => $this->tenantA->id,
            'provider' => 'razorpay',
            'name' => 'Tenant A Razorpay',
            'status' => 'connected',
            'auth_type' => 'credentials',
            'encrypted_credentials' => ['key_secret' => 'tenantA_secret'],
        ]);

        TenantApiKey::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Secret Key',
            'key_prefix' => 'crm_live_aaaa',
            'key_hash' => hash('sha256', 'raw_secret_a'),
            'scopes' => ['*'],
        ]);

        OutboundWebhook::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Tenant A Webhook',
            'url' => 'https://tenant-a.test/hook',
            'signing_secret' => 'whsec_a',
            'events' => ['*'],
        ]);

        // Tenant B queries API keys
        Sanctum::actingAs($this->adminUserB);

        $keysB = $this->getJson('/api/v1/crm/api-keys');
        $keysB->assertStatus(200)->assertJsonCount(0, 'data');

        // Tenant B queries webhooks
        $webhooksB = $this->getJson('/api/v1/crm/webhooks');
        $webhooksB->assertStatus(200)->assertJsonCount(0, 'data');

        // Tenant B queries integrations directory
        $integrationsB = $this->getJson('/api/v1/crm/integrations');
        $integrationsB->assertStatus(200);

        // All integrations for Tenant B should show is_connected = false
        $connectedB = collect($integrationsB->json('data'))->where('is_connected', true);
        $this->assertCount(0, $connectedB);
    }
}
