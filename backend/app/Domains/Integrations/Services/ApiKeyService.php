<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\TenantApiKey;
use App\Domains\Integrations\Models\TenantApiRequestLog;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ApiKeyService
{
    /**
     * Create a new tenant API key and return unmasked raw secret ONLY ONCE
     */
    public function createApiKey(int $tenantId, int $userId, array $data): array
    {
        $rawSecret = 'crm_live_' . Str::random(32);
        $prefix = substr($rawSecret, 0, 15);
        $keyHash = hash('sha256', $rawSecret);

        $apiKey = TenantApiKey::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'name' => $data['name'] ?? 'Production API Key',
            'key_prefix' => $prefix,
            'key_hash' => $keyHash,
            'encrypted_secret' => $rawSecret, // encrypted by cast
            'scopes' => $data['scopes'] ?? ['*'],
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 60,
            'expires_at' => !empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            'is_revoked' => false,
        ]);

        return [
            'api_key' => $apiKey,
            'raw_secret' => $rawSecret, // Provided ONLY once to developer
            'warning' => 'Store this secret securely. It cannot be shown again.',
        ];
    }

    /**
     * List all API keys for tenant (secrets masked)
     */
    public function listApiKeys(int $tenantId)
    {
        return TenantApiKey::where('tenant_id', $tenantId)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Revoke an active API key immediately
     */
    public function revokeApiKey(int $tenantId, int $keyId): bool
    {
        $key = TenantApiKey::where('tenant_id', $tenantId)->findOrFail($keyId);
        $key->update([
            'is_revoked' => true,
            'revoked_at' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * Rotate an API key: revokes existing key and generates a new key with identical scopes & policies
     */
    public function rotateApiKey(int $tenantId, int $userId, int $keyId): array
    {
        $oldKey = TenantApiKey::where('tenant_id', $tenantId)->findOrFail($keyId);
        $oldKey->update([
            'is_revoked' => true,
            'revoked_at' => Carbon::now(),
        ]);

        return $this->createApiKey($tenantId, $userId, [
            'name' => $oldKey->name . ' (Rotated)',
            'scopes' => $oldKey->scopes,
            'allowed_ips' => $oldKey->allowed_ips,
            'rate_limit_per_minute' => $oldKey->rate_limit_per_minute,
            'expires_at' => $oldKey->expires_at,
        ]);
    }

    /**
     * Validate an API key against hash, status, expiration, scopes, and IP whitelisting
     */
    public function validateKey(string $rawKey, string $requiredScope = '*', ?string $clientIp = null): ?TenantApiKey
    {
        $hash = hash('sha256', $rawKey);
        $apiKey = TenantApiKey::where('key_hash', $hash)
            ->where('is_revoked', false)
            ->first();

        if (!$apiKey || !$apiKey->isValid()) {
            return null;
        }

        // Validate IP whitelist if specified
        if (!empty($apiKey->allowed_ips) && $clientIp) {
            if (!in_array($clientIp, $apiKey->allowed_ips)) {
                return null;
            }
        }

        // Validate scope
        if ($requiredScope !== '*' && !$apiKey->hasScope($requiredScope)) {
            return null;
        }

        // Update last used metadata
        $apiKey->update([
            'last_used_at' => Carbon::now(),
            'last_used_ip' => $clientIp,
        ]);

        return $apiKey;
    }

    /**
     * Log API request for analytics, security auditing, and rate limiting
     */
    public function logRequest(int $tenantId, ?int $apiKeyId, string $method, string $endpoint, int $statusCode, ?string $ip = null, ?string $userAgent = null, int $durationMs = 0, ?string $errorMessage = null): TenantApiRequestLog
    {
        return TenantApiRequestLog::create([
            'tenant_id' => $tenantId,
            'api_key_id' => $apiKeyId,
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
            'response_time_ms' => $durationMs,
            'error_message' => $errorMessage,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Retrieve audit request logs
     */
    public function getRequestLogs(int $tenantId, ?int $apiKeyId = null, int $limit = 50)
    {
        $query = TenantApiRequestLog::where('tenant_id', $tenantId)
            ->with('apiKey:id,name,key_prefix')
            ->orderBy('created_at', 'desc');

        if ($apiKeyId) {
            $query->where('api_key_id', $apiKeyId);
        }

        return $query->take($limit)->get();
    }
}
