<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\LeadAssignment;
use App\Domains\CustomFields\Traits\HasCustomFields;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasCustomFields;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'company_name',
        'name',
        'email',
        'phone',
        'alternate_phone',
        'designation',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'requirement',
        'product_interest',
        'estimated_value',
        'expected_purchase_date',
        'status',
        'lifecycle_stage',
        'priority',
        'qualification_status',
        'score',
        'score_breakdown',
        'assigned_user_id',
        'team_id',
        'source',
        'source_account',
        'campaign',
        'ad_details',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'first_touch_attribution',
        'last_touch_attribution',
        'landing_page_url',
        'tags',
        'notes',
        'ai_analysis',
        'next_followup_at',
        'reminder_at',
        'lost_reason',
        'disqualification_reason',
        'is_archived',
        'branch_id',
        'original_source',
        'original_campaign',
        'external_lead_id',
        'custom_fields_payload',
        'last_contacted_at',
        'sla_policy_id',
        'sla_first_response_due_at',
        'sla_first_responded_at',
        'sla_status',
        'sla_escalated_at',
        'reassigned_count',
        'converted_at',
        'converted_contact_id',
        'converted_company_id',
        'converted_deal_id',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'score' => 'integer',
        'score_breakdown' => 'array',
        'tags' => 'array',
        'custom_fields_payload' => 'array',
        'first_touch_attribution' => 'array',
        'last_touch_attribution' => 'array',
        'is_archived' => 'boolean',
        'expected_purchase_date' => 'date',
        'next_followup_at' => 'datetime',
        'reminder_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'sla_first_response_due_at' => 'datetime',
        'sla_first_responded_at' => 'datetime',
        'sla_escalated_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    public function followups(): HasMany
    {
        return $this->hasMany(LeadFollowup::class, 'lead_id');
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(LeadStageHistory::class, 'lead_id')->orderBy('id', 'desc');
    }

    public function escalationLogs(): HasMany
    {
        return $this->hasMany(LeadEscalationLog::class, 'lead_id')->orderBy('id', 'desc');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(LeadSlaPolicy::class, 'sla_policy_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function ingestionEvents(): HasMany
    {
        return $this->hasMany(LeadIngestionEvent::class, 'lead_id');
    }

    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id');
    }

    public function convertedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'converted_company_id');
    }

    public function convertedDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'converted_deal_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->orderBy('created_at', 'desc');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->orderBy('created_at', 'desc');
    }

    public function mediaFiles(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'fileable');
    }

    // --- SCOPES ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_user_id');
    }

    public function scopeQualified(Builder $query): Builder
    {
        return $query->where('qualification_status', 'qualified');
    }
}
