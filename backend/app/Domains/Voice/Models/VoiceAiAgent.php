<?php

namespace App\Domains\Voice\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoiceAiAgent extends Model
{
    use SoftDeletes;

    protected $table = 'voice_ai_agents';

    protected $fillable = [
        'tenant_id',
        'telephony_phone_number_id',
        'name',
        'description',
        'business_purpose',
        'status',
        'language',
        'regional_dialects',
        'voice_engine',
        'voice_id',
        'tone',
        'system_instructions',
        'call_opening',
        'knowledge_base',
        'qualification_script',
        'confidence_threshold',
        'max_call_duration_seconds',
        'human_transfer_number',
        'allowed_crm_actions',
        'escalation_rules',
        'business_hours',
        'monthly_call_minutes_budget',
        'used_call_minutes_this_month',
        'version',
    ];

    protected $casts = [
        'qualification_script' => 'array',
        'allowed_crm_actions' => 'array',
        'escalation_rules' => 'array',
        'business_hours' => 'array',
        'regional_dialects' => 'array',
        'confidence_threshold' => 'float',
        'max_call_duration_seconds' => 'integer',
        'monthly_call_minutes_budget' => 'integer',
        'used_call_minutes_this_month' => 'integer',
        'version' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(TelephonyPhoneNumber::class, 'telephony_phone_number_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(VoiceCall::class, 'voice_agent_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(VoiceCallingCampaign::class, 'voice_agent_id');
    }

    public function hasCallingMinutesAvailable(): bool
    {
        return $this->used_call_minutes_this_month < $this->monthly_call_minutes_budget;
    }
}
