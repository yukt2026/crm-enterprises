<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Domains\WhatsApp\Models\WhatsAppAiAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialChannelAccount extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'social_channel_accounts';

    protected $fillable = [
        'tenant_id',
        'provider', // 'facebook' or 'instagram'
        'page_id',
        'page_name',
        'page_access_token',
        'instagram_business_id',
        'instagram_username',
        'is_connected',
        'connected_at',
        'token_expires_at',
        'webhook_subscribed',
        'webhook_verify_token',
        'permissions',
        'metadata',
        'ai_enabled',
        'ai_agent_id',
        'ai_instructions',
        'ai_handover_keywords',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'webhook_subscribed' => 'boolean',
        'ai_enabled' => 'boolean',
        'page_access_token' => 'encrypted',
        'connected_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'permissions' => 'array',
        'metadata' => 'array',
        'ai_handover_keywords' => 'array',
    ];

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAiAgent::class, 'ai_agent_id');
    }

    public function scopeFacebook(Builder $query): Builder
    {
        return $query->where('provider', 'facebook');
    }

    public function scopeInstagram(Builder $query): Builder
    {
        return $query->where('provider', 'instagram');
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('is_connected', true);
    }
}
