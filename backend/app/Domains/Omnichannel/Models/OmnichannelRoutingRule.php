<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\CRM\Models\SalesTeam;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichannelRoutingRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'omnichannel_routing_rules';

    protected $fillable = [
        'tenant_id',
        'name',
        'rule_type', // round_robin, least_active, branch, department, language, lead_source, product, working_hours
        'channel', // all, whatsapp, facebook, instagram, email, sms, webchat, call, telegram
        'conditions',
        'target_user_id',
        'target_team_id',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'conditions' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetTeam(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'target_team_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('priority', 'asc');
    }
}
