<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutboundWebhook extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'signing_secret',
        'events',
        'is_active',
        'retry_limit',
        'timeout_seconds',
        'headers',
        'last_delivery_at',
        'last_delivery_status',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'retry_limit' => 'integer',
        'timeout_seconds' => 'integer',
        'last_delivery_at' => 'datetime',
    ];

    protected $hidden = [
        'signing_secret',
    ];

    protected $appends = [
        'masked_signing_secret',
    ];

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(WebhookDeliveryLog::class, 'outbound_webhook_id');
    }

    public function getMaskedSigningSecretAttribute(): string
    {
        $sec = $this->signing_secret ?? '';
        if (strlen($sec) > 8) {
            return substr($sec, 0, 4) . '••••••••' . substr($sec, -4);
        }
        return '••••••••';
    }
}
