<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class WhatsAppAiProvider extends Model
{
    protected $table = 'whatsapp_ai_providers';

    protected $fillable = [
        'tenant_id',
        'provider',
        'name',
        'api_key',
        'api_endpoint',
        'default_model',
        'available_models',
        'is_active',
        'priority',
        'monthly_budget_usd',
        'spent_usd_this_month',
        'timeout_seconds',
        'max_retries',
    ];

    protected $casts = [
        'available_models' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'monthly_budget_usd' => 'decimal:2',
        'spent_usd_this_month' => 'decimal:2',
        'timeout_seconds' => 'integer',
        'max_retries' => 'integer',
    ];

    /**
     * Mutator to encrypt api_key automatically.
     */
    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Accessor to decrypt api_key securely.
     */
    public function getApiKeyAttribute($value): ?string
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
}
