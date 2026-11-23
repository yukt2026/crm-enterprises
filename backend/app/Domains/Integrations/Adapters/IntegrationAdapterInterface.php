<?php

namespace App\Domains\Integrations\Adapters;

use App\Domains\Integrations\Models\TenantIntegration;

interface IntegrationAdapterInterface
{
    public function getProvider(): string;
    public function getName(): string;
    public function getCategory(): string;
    public function getDescription(): string;
    public function getSupportedFeatures(): array;
    public function getRequiredScopes(): array;
    public function getSetupInstructions(): string;
    public function getLimitations(): array;

    public function generateAuthorizationUrl(string $state, string $redirectUri): string;
    public function exchangeCodeForTokens(string $code, string $redirectUri): array;
    public function refreshToken(array $credentials): array;
    public function revokeToken(array $credentials): bool;
    public function testConnection(array $credentials): array;
    public function syncData(TenantIntegration $integration, string $syncType = 'incremental'): array;
}
