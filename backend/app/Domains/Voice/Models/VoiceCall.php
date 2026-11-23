<?php

namespace App\Domains\Voice\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Deal;
use App\Models\User;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceCall extends Model
{
    protected $table = 'voice_calls';

    protected $fillable = [
        'tenant_id',
        'voice_agent_id',
        'campaign_id',
        'lead_id',
        'contact_id',
        'deal_id',
        'company_id',
        'branch_id',
        'assigned_user_id',
        'transfer_to_user_id',
        'provider',
        'provider_call_id',
        'idempotency_key',
        'direction',
        'call_type',
        'from_number',
        'to_number',
        'start_time',
        'end_time',
        'duration_seconds',
        'status',
        'hangup_cause',
        'disposition',
        'recording_url',
        'recording_duration_seconds',
        'recording_notice_played',
        'transcript',
        'ai_summary',
        'extracted_data',
        'qualification_result',
        'quality_score',
        'manager_feedback',
        'next_follow_up_at',
        'follow_up_required',
        'follow_up_notes',
        'cost_amount',
        'cost_currency',
        'human_transferred',
        'transfer_target',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'duration_seconds' => 'integer',
        'recording_duration_seconds' => 'integer',
        'recording_notice_played' => 'boolean',
        'extracted_data' => 'array',
        'cost_amount' => 'decimal:4',
        'human_transferred' => 'boolean',
        'follow_up_required' => 'boolean',
        'quality_score' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(VoiceAiAgent::class, 'voice_agent_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(VoiceCallingCampaign::class, 'campaign_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function transferredToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transfer_to_user_id');
    }

    public function qualityReviews(): HasMany
    {
        return $this->hasMany(CallQualityReview::class, 'voice_call_id');
    }

    public function recordingAccessLogs(): HasMany
    {
        return $this->hasMany(CallRecordingAccessLog::class, 'voice_call_id');
    }
}
