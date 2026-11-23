<?php

namespace App\Domains\Support\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Invoice;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Project;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'ticket_number',
        'customer_id',
        'contact_id',
        'company_id',
        'source_channel', // email, whatsapp, web_chat, phone, portal
        'category_id',
        'subcategory',
        'subject',
        'description',
        'priority', // low, medium, high, urgent
        'status', // new, open, in_progress, waiting_customer, waiting_internal, resolved, closed
        'assigned_user_id',
        'assigned_team_id',
        'lead_id',
        'deal_id',
        'project_id',
        'branch_id',
        'invoice_id',
        'tags',
        'attachments',
        'sla_policy_id',
        'first_response_due_at',
        'resolution_due_at',
        'due_date',
        'first_responded_at',
        'sla_status', // within_sla, warning, breached
        'escalated_to_user_id',
        'escalated_at',
        'escalation_reason',
        'resolution_notes',
        'closure_reason',
        'resolved_at',
        'closed_at',
        'reopen_count',
        'reopened_at',
        'reopen_reason',
        'csat_rating',
        'csat_comment',
        'ai_reply_draft',
        'ai_summary',
        'ai_troubleshooting_steps',
        'ai_suggested_category',
        'ai_confidence_score',
        'ai_auto_reply_enabled',
    ];

    protected $casts = [
        'tags' => 'array',
        'attachments' => 'array',
        'first_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'due_date' => 'datetime',
        'first_responded_at' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'reopen_count' => 'integer',
        'csat_rating' => 'integer',
        'ai_confidence_score' => 'decimal:2',
        'ai_auto_reply_enabled' => 'boolean',
    ];

    // --- RELATIONSHIPS ---

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportTicketCategory::class, 'category_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SupportSlaPolicy::class, 'sla_policy_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(SupportTicketTransition::class, 'ticket_id');
    }

    public function feedback(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CustomerFeedback::class, 'ticket_id');
    }

    // --- SCOPES ---

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopeBreached(Builder $query): Builder
    {
        return $query->where('sla_status', 'breached');
    }

    public function scopeMyTickets(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    // --- HELPERS ---

    public function isBreached(): bool
    {
        if ($this->status === 'closed' || $this->status === 'resolved') {
            return false;
        }

        $now = Carbon::now();

        // Response breach
        if (!$this->first_responded_at && $this->first_response_due_at && $now->isAfter($this->first_response_due_at)) {
            return true;
        }

        // Resolution breach
        if ($this->resolution_due_at && $now->isAfter($this->resolution_due_at)) {
            return true;
        }

        return false;
    }
}
