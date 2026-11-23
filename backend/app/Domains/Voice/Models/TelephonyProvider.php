<?php

namespace App\Domains\Voice\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class TelephonyProvider extends Model
{
    protected $table = 'telephony_providers';

    protected $fillable = [
        'tenant_id',
        'provider',
        'name',
        'account_sid',
        'auth_token',
        'api_key',
        'subdomain',
        'caller_id',
        'dlt_entity_id',
        'is_active',
        'supports_recordings',
        'supports_realtime_streams',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_recordings' => 'boolean',
        'supports_realtime_streams' => 'boolean',
        'settings' => 'array',
    ];

    public function setAuthTokenAttribute($value): void
    {
        $this->attributes['auth_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAuthTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(TelephonyPhoneNumber::class, 'telephony_provider_id');
    }
}
