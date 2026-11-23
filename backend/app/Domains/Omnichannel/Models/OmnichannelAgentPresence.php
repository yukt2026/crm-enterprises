<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Domains\WhatsApp\Models\WhatsAppConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichannelAgentPresence extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'omnichannel_agent_presences';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'conversation_id',
        'is_typing',
        'is_online',
        'last_seen_at',
    ];

    protected $casts = [
        'is_typing' => 'boolean',
        'is_online' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function scopeActiveRecently(Builder $query, int $seconds = 60): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subSeconds($seconds));
    }
}
