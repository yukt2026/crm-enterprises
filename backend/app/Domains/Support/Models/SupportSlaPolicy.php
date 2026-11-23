<?php

namespace App\Domains\Support\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportSlaPolicy extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'priority', // all, urgent, high, medium, low
        'response_target_minutes',
        'resolution_target_minutes',
        'business_hours_only',
        'escalation_threshold_percent',
        'escalate_to_user_id',
        'is_default',
    ];

    protected $casts = [
        'response_target_minutes' => 'integer',
        'resolution_target_minutes' => 'integer',
        'business_hours_only' => 'boolean',
        'escalation_threshold_percent' => 'integer',
        'is_default' => 'boolean',
    ];

    public function escalateToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalate_to_user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'sla_policy_id');
    }
}
