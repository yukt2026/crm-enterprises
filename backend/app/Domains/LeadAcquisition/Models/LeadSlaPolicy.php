<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSlaPolicy extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'is_default',
        'first_response_time_minutes',
        'followup_deadline_hours',
        'max_idle_hours',
        'working_hours_only',
        'business_hours_start',
        'business_hours_end',
        'working_days',
        'holiday_calendar',
        'escalation_level_1_minutes',
        'escalation_level_2_minutes',
        'auto_reassign_on_escalation',
        'manager_user_id',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'first_response_time_minutes' => 'integer',
        'followup_deadline_hours' => 'integer',
        'max_idle_hours' => 'integer',
        'working_hours_only' => 'boolean',
        'working_days' => 'array',
        'holiday_calendar' => 'array',
        'escalation_level_1_minutes' => 'integer',
        'escalation_level_2_minutes' => 'integer',
        'auto_reassign_on_escalation' => 'boolean',
    ];

    public function managerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'sla_policy_id');
    }
}
