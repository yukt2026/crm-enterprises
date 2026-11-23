<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppAccount extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'tenant_id',
        'name',
        'waba_id',
        'app_id',
        'system_user_token',
        'webhook_verify_token',
        'status', // connected, disconnected, restricted
        'business_verification_status', // verified, pending, failed
        'settings',
        'metadata',
        'token_expires_at',
        'last_tested_at',
        'last_error',
    ];

    protected $casts = [
        'system_user_token' => 'encrypted',
        'settings' => 'array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'last_tested_at' => 'datetime',
    ];

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(WhatsAppPhoneNumber::class, 'whatsapp_account_id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whatsapp_account_id');
    }

    public function integrationLogs(): HasMany
    {
        return $this->hasMany(WhatsAppIntegrationLog::class, 'whatsapp_account_id');
    }
}
