<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantApiKey extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'key_prefix',
        'key_hash',
        'encrypted_secret',
        'scopes',
        'allowed_ips',
        'rate_limit_per_minute',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'is_revoked',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_ips' => 'array',
        'rate_limit_per_minute' => 'integer',
        'is_revoked' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'key_hash',
        'encrypted_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(TenantApiRequestLog::class, 'api_key_id');
    }

    /**
     * Check if key has specific permission scope
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes) || in_array($scope, $scopes);
    }

    /**
     * Check if key is currently valid (not revoked and not expired)
     */
    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
