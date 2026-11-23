<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichannelSlaPolicy extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'omnichannel_sla_policies';

    protected $fillable = [
        'tenant_id',
        'name',
        'priority', // all, low, medium, high, urgent
        'channel', // all, whatsapp, facebook, instagram, email, sms, webchat, call, telegram
        'first_response_target_minutes',
        'resolution_target_minutes',
        'business_hours_enabled',
        'business_hours',
        'escalation_target_minutes',
        'escalate_to_user_id',
        'is_active',
    ];

    protected $casts = [
        'first_response_target_minutes' => 'integer',
        'resolution_target_minutes' => 'integer',
        'escalation_target_minutes' => 'integer',
        'business_hours_enabled' => 'boolean',
        'business_hours' => 'array',
        'is_active' => 'boolean',
    ];

    public function escalateToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalate_to_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
