<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Omnichannel\Models\EmailAccount;
use App\Domains\Omnichannel\Models\SmsConfiguration;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Domains\WhatsApp\Models\WhatsAppAccount;
use App\Domains\WhatsApp\Models\WhatsAppTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingCampaign extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'description',
        'channel', // whatsapp, email, sms
        'type', // broadcast, drip, triggered, ad_campaign
        'objective', // lead_generation, product_launch, retention, nurturing, festive_offer, event_invitation
        'status', // draft, in_review, scheduled, running, paused, completed, cancelled, archived
        'approval_status', // not_submitted, pending_approval, approved, rejected
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
        'segment_id',
        'whatsapp_template_id',
        'whatsapp_account_id',
        'email_account_id',
        'sms_config_id',
        'subject',
        'content',
        'ai_drafted',
        'variable_mappings',
        'scheduled_at',
        'start_date',
        'end_date',
        'timezone',
        'business_hours_only',
        'frequency_cap_days',
        'rate_limit_per_minute',
        'budget',
        'total_recipients_count',
        'sent_count',
        'delivered_count',
        'read_count',
        'clicks_count',
        'opens_count',
        'failed_count',
        'replied_count',
        'unsubscribed_count',
        'conversions_count',
        'cost_per_lead',
        'cost_per_acquisition',
        'attributed_revenue',
        'spend_amount',
        'spend_type', // provider_reported, manual_estimate
        'cpl',
        'roas',
        'campaign_notes',
        'started_at',
        'completed_at',
        'assigned_owner_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'variable_mappings' => 'array',
        'scheduled_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'approved_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'budget' => 'float',
        'attributed_revenue' => 'float',
        'spend_amount' => 'float',
        'cost_per_lead' => 'float',
        'cost_per_acquisition' => 'float',
        'business_hours_only' => 'boolean',
        'ai_drafted' => 'boolean',
    ];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'segment_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class, 'campaign_id');
    }

    public function whatsappTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }

    public function smsConfig(): BelongsTo
    {
        return $this->belongsTo(SmsConfiguration::class, 'sms_config_id');
    }

    public function assignedOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_owner_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CampaignApproval::class, 'campaign_id');
    }
}
