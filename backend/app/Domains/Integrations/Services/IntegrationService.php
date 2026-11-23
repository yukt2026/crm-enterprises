<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Adapters\IntegrationRegistry;
use App\Domains\Integrations\Models\IntegrationSyncLog;
use App\Domains\Integrations\Models\OAuthState;
use App\Domains\Integrations\Models\TenantIntegration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IntegrationService
{
    public function __construct(
        protected IntegrationRegistry $registry
    ) {}

    /**
     * Get directory catalog of all supported integrations with current tenant connection status
     */
    public function getDirectory(int $tenantId): array
    {
        $tenantIntegrations = TenantIntegration::where('tenant_id', $tenantId)
            ->get()
            ->keyBy('provider');

        $directory = [];

        foreach ($this->registry->all() as $provider => $adapter) {
            $connected = $tenantIntegrations->get($provider);

            $directory[] = [
                'provider' => $provider,
                'name' => $adapter->getName(),
                'category' => $adapter->getCategory(),
                'description' => $adapter->getDescription(),
                'supported_features' => $adapter->getSupportedFeatures(),
                'required_scopes' => $adapter->getRequiredScopes(),
                'setup_instructions' => $adapter->getSetupInstructions(),
                'limitations' => $adapter->getLimitations(),
                'is_connected' => $connected ? ($connected->status === 'connected') : false,
                'connection' => $connected ? [
                    'id' => $connected->id,
                    'name' => $connected->name,
                    'status' => $connected->status,
                    'auth_type' => $connected->auth_type,
                    'masked_credentials' => $connected->masked_credentials,
                    'scopes' => $connected->scopes,
                    'metadata' => $connected->metadata,
                    'connected_at' => $connected->connected_at ? $connected->connected_at->toIso8601String() : null,
                    'last_sync_at' => $connected->last_sync_at ? $connected->last_sync_at->toIso8601String() : null,
                    'next_sync_at' => $connected->next_sync_at ? $connected->next_sync_at->toIso8601String() : null,
                    'last_error' => $connected->last_error,
                    'is_sync_enabled' => $connected->is_sync_enabled,
                ] : null,
            ];
        }

        return $directory;
    }

    /**
     * Generate secure, CSRF-protected OAuth state token
     */
    public function generateOAuthUrl(int $tenantId, int $userId, string $provider, string $redirectUri): array
    {
        $adapter = $this->registry->get($provider);
        if (!$adapter) {
            throw new \InvalidArgumentException("Unsupported integration provider: {$provider}");
        }

        $stateToken = Str::random(40);

        OAuthState::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'provider' => $provider,
            'state_token' => $stateToken,
            'redirect_url' => $redirectUri,
            'expires_at' => Carbon::now()->addMinutes(15),
        ]);

        $authUrl = $adapter->generateAuthorizationUrl($stateToken, $redirectUri);

        return [
            'provider' => $provider,
            'auth_url' => $authUrl,
            'state_token' => $stateToken,
            'expires_at' => Carbon::now()->addMinutes(15)->toIso8601String(),
        ];
    }

    /**
     * Handle OAuth callback: validate state token, exchange code, and encrypt credentials
     */
    public function handleOAuthCallback(int $tenantId, int $userId, string $provider, string $code, string $state, string $redirectUri): TenantIntegration
    {
        // 1. Validate state token to block CSRF
        $oauthState = OAuthState::where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->where('state_token', $state)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$oauthState) {
            throw new \InvalidArgumentException('Invalid or expired OAuth state token. Cross-site request verification failed.');
        }

        $oauthState->update(['used_at' => Carbon::now()]);

        $adapter = $this->registry->get($provider);
        if (!$adapter) {
            throw new \InvalidArgumentException("Unsupported integration provider: {$provider}");
        }

        // 2. Exchange code for tokens
        $tokenData = $adapter->exchangeCodeForTokens($code, $redirectUri);

        // 3. Encrypt and persist
        return TenantIntegration::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $provider,
            ],
            [
                'name' => $adapter->getName(),
                'status' => 'connected',
                'auth_type' => 'oauth2',
                'encrypted_credentials' => $tokenData,
                'scopes' => $adapter->getRequiredScopes(),
                'metadata' => [
                    'account_id' => $tokenData['customer_id'] ?? ($tokenData['ad_account_id'] ?? null),
                    'token_type' => $tokenData['token_type'] ?? 'Bearer',
                ],
                'connected_by_user_id' => $userId,
                'connected_at' => Carbon::now(),
                'next_sync_at' => Carbon::now()->addMinutes(60),
                'last_error' => null,
                'is_sync_enabled' => true,
            ]
        );
    }

    /**
     * Connect an integration using direct API keys / credentials (e.g. Razorpay, SMTP, AWS S3)
     */
    public function connectWithCredentials(int $tenantId, int $userId, string $provider, string $name, array $credentials, array $metadata = []): TenantIntegration
    {
        $adapter = $this->registry->get($provider);
        if (!$adapter) {
            throw new \InvalidArgumentException("Unsupported integration provider: {$provider}");
        }

        // Verify connection before saving
        $testResult = $adapter->testConnection($credentials);
        if (!$testResult['success']) {
            throw new \RuntimeException($testResult['message'] ?? 'Connection verification failed with provided credentials.');
        }

        return TenantIntegration::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'provider' => $provider,
            ],
            [
                'name' => $name ?: $adapter->getName(),
                'status' => 'connected',
                'auth_type' => 'credentials',
                'encrypted_credentials' => $credentials,
                'scopes' => $adapter->getRequiredScopes(),
                'metadata' => array_merge($metadata, [
                    'verified_at' => Carbon::now()->toIso8601String(),
                ]),
                'connected_by_user_id' => $userId,
                'connected_at' => Carbon::now(),
                'next_sync_at' => Carbon::now()->addMinutes(60),
                'last_error' => null,
                'is_sync_enabled' => true,
            ]
        );
    }

    /**
     * Disconnect and revoke integration
     */
    public function disconnect(int $tenantId, int $integrationId): bool
    {
        $integration = TenantIntegration::where('tenant_id', $tenantId)->findOrFail($integrationId);

        $adapter = $this->registry->get($integration->provider);
        if ($adapter && !empty($integration->encrypted_credentials)) {
            try {
                $adapter->revokeToken($integration->encrypted_credentials);
            } catch (\Throwable $e) {
                // Log and continue disconnect
            }
        }

        $integration->update([
            'status' => 'disconnected',
            'is_sync_enabled' => false,
            'next_sync_at' => null,
        ]);

        return true;
    }

    /**
     * Test connection diagnostics for an existing connected integration
     */
    public function testConnectedIntegration(int $tenantId, int $integrationId): array
    {
        $integration = TenantIntegration::where('tenant_id', $tenantId)->findOrFail($integrationId);
        $adapter = $this->registry->get($integration->provider);

        if (!$adapter) {
            return [
                'success' => false,
                'message' => 'Adapter not found for provider ' . $integration->provider,
            ];
        }

        $res = $adapter->testConnection($integration->encrypted_credentials ?? []);

        if (!$res['success']) {
            $integration->update([
                'status' => 'error',
                'last_error' => $res['message'],
            ]);
        } else {
            $integration->update([
                'status' => 'connected',
                'last_error' => null,
            ]);
        }

        return $res;
    }

    /**
     * Execute data synchronization for a connected integration
     */
    public function syncIntegration(int $tenantId, int $integrationId, string $syncType = 'manual'): array
    {
        $integration = TenantIntegration::where('tenant_id', $tenantId)->findOrFail($integrationId);

        if ($integration->status !== 'connected') {
            throw new \RuntimeException("Cannot synchronize integration in '{$integration->status}' status.");
        }

        $adapter = $this->registry->get($integration->provider);
        if (!$adapter) {
            throw new \RuntimeException("Adapter not found for provider '{$integration->provider}'.");
        }

        $startTime = microtime(true);
        $syncLog = IntegrationSyncLog::create([
            'tenant_id' => $tenantId,
            'integration_id' => $integration->id,
            'sync_type' => $syncType,
            'status' => 'running',
            'started_at' => Carbon::now(),
        ]);

        try {
            $syncResult = $adapter->syncData($integration, $syncType);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $syncLog->update([
                'status' => 'success',
                'items_processed' => $syncResult['items_processed'] ?? 0,
                'items_failed' => $syncResult['items_failed'] ?? 0,
                'duration_ms' => $durationMs,
                'completed_at' => Carbon::now(),
            ]);

            $integration->update([
                'last_sync_at' => Carbon::now(),
                'next_sync_at' => Carbon::now()->addMinutes($integration->sync_frequency_minutes ?: 60),
                'last_error' => null,
            ]);

            return [
                'success' => true,
                'sync_log_id' => $syncLog->id,
                'items_processed' => $syncLog->items_processed,
                'duration_ms' => $durationMs,
                'details' => $syncResult['details'] ?? 'Sync completed successfully.',
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $syncLog->update([
                'status' => 'failed',
                'error_details' => $e->getMessage(),
                'duration_ms' => $durationMs,
                'completed_at' => Carbon::now(),
            ]);

            $integration->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
