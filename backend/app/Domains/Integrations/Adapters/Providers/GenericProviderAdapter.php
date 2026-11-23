<?php

namespace App\Domains\Integrations\Adapters\Providers;

use App\Domains\Integrations\Adapters\AbstractIntegrationAdapter;
use App\Domains\Integrations\Models\TenantIntegration;

class GenericProviderAdapter extends AbstractIntegrationAdapter
{
    public function __construct(
        protected string $provider,
        protected string $name,
        protected string $category,
        protected string $description,
        protected array $features = [],
        protected array $scopes = [],
        protected string $instructions = '',
        protected array $limitations = []
    ) {}

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSupportedFeatures(): array
    {
        return $this->features;
    }

    public function getRequiredScopes(): array
    {
        return $this->scopes;
    }

    public function getSetupInstructions(): string
    {
        return $this->instructions;
    }

    public function getLimitations(): array
    {
        return $this->limitations;
    }

    public function generateAuthorizationUrl(string $state, string $redirectUri): string
    {
        return "https://auth.provider.test/oauth/authorize?provider={$this->provider}&state={$state}&redirect_uri=" . urlencode($redirectUri);
    }

    public function exchangeCodeForTokens(string $code, string $redirectUri): array
    {
        return [
            'access_token' => "{$this->provider}_token_" . bin2hex(random_bytes(16)),
            'refresh_token' => "{$this->provider}_refresh_" . bin2hex(random_bytes(20)),
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];
    }

    public function syncData(TenantIntegration $integration, string $syncType = 'incremental'): array
    {
        return [
            'status' => 'success',
            'items_processed' => 10,
            'items_failed' => 0,
            'details' => "Data synchronization successfully executed for {$this->name}.",
        ];
    }
}
