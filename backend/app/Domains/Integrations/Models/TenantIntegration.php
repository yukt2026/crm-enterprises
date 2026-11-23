<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TenantIntegration extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'provider',
        'name',
        'status',
        'auth_type',
        'encrypted_credentials',
        'scopes',
        'metadata',
        'connected_by_user_id',
        'connected_at',
        'last_sync_at',
        'next_sync_at',
        'last_error',
        'is_sync_enabled',
        'sync_frequency_minutes',
    ];

    protected $casts = [
        'encrypted_credentials' => 'encrypted:array',
        'scopes' => 'array',
        'metadata' => 'array',
        'is_sync_enabled' => 'boolean',
        'connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'next_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    protected $appends = [
        'masked_credentials',
    ];

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class, 'integration_id');
    }

    /**
     * Return masked credentials so secrets are NEVER leaked to the frontend
     */
    public function getMaskedCredentialsAttribute(): array
    {
        $creds = $this->encrypted_credentials ?? [];
        $masked = [];

        foreach ($creds as $key => $val) {
            if (is_string($val) && strlen($val) > 6) {
                $masked[$key] = substr($val, 0, 3) . '••••••••' . substr($val, -3);
            } elseif (is_string($val)) {
                $masked[$key] = '••••••';
            } else {
                $masked[$key] = $val;
            }
        }

        return $masked;
    }
}
