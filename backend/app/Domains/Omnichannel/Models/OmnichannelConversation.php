<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Builder;

class OmnichannelConversation extends WhatsAppConversation
{
    // Inherits table, fillable, relationships from WhatsAppConversation

    public function scopeChannel(Builder $query, ?string $channel): Builder
    {
        if (!$channel || $channel === 'all') {
            return $query;
        }

        return $query->where('channel', $channel);
    }

    public function scopeStatusFilter(Builder $query, ?string $status): Builder
    {
        if (!$status || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeAssignedFilter(Builder $query, ?string $filter, ?int $userId = null): Builder
    {
        if ($filter === 'my' && $userId) {
            return $query->where('assigned_user_id', $userId);
        }

        if ($filter === 'unassigned') {
            return $query->whereNull('assigned_user_id');
        }

        if ($filter === 'team') {
            return $query->whereNotNull('assigned_team_id');
        }

        return $query;
    }
}
