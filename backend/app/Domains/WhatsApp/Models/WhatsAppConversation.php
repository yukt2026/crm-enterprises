<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\SalesTeam;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppConversation extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'tenant_id',
        'channel', // whatsapp, email, sms, call, webchat
        'whatsapp_phone_number_id',
        'customer_id',
        'contact_id',
        'lead_id',
        'phone_number',
        'customer_email',
        'customer_name',
        'subject',
        'channel_account_id',
        'status', // open, pending, resolved
        'is_ai_handled',
        'assigned_user_id',
        'assigned_team_id',
        'priority', // low, medium, high, urgent
        'unread_count',
        'window_expires_at',
        'last_message_at',
        'last_message_preview',
        'last_message_direction',
        'first_response_at',
        'first_response_time_seconds',
        'resolved_at',
        'resolution_time_seconds',
        'last_actor_type',
        'tags',
        'metadata',
        'locked_by_user_id',
        'locked_at',
        'sla_policy_id',
        'sla_status',
        'first_response_due_at',
        'resolution_due_at',
        'is_archived',
        'branch_id',
        'department_id',
        'language',
    ];

    protected $casts = [
        'is_ai_handled' => 'boolean',
        'is_archived' => 'boolean',
        'window_expires_at' => 'datetime',
        'last_message_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'locked_at' => 'datetime',
        'first_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'first_response_time_seconds' => 'integer',
        'resolution_time_seconds' => 'integer',
        'tags' => 'array',
        'metadata' => 'array',
        'unread_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (WhatsAppConversation $conv) {
            if (empty($conv->phone_number)) {
                $conv->phone_number = $conv->customer_email
                    ?? ($conv->channel . '_' . ($conv->customer_id ?? $conv->lead_id ?? $conv->contact_id ?? uniqid()));
            }
        });
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'assigned_team_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->orderBy('id', 'asc');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsAppConversationNote::class, 'conversation_id')->orderBy('id', 'desc');
    }

    public function assignmentLogs(): HasMany
    {
        return $this->hasMany(\App\Domains\Omnichannel\Models\ConversationAssignmentLog::class, 'conversation_id')->orderBy('id', 'desc');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Omnichannel\Models\OmnichannelSlaPolicy::class, 'sla_policy_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\HR\Models\Department::class, 'department_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(\App\Domains\Omnichannel\Models\OmnichannelAgentPresence::class, 'conversation_id');
    }

    /**
     * Check if 24-hour service window is still active
     */
    public function isWindowActive(): bool
    {
        return $this->window_expires_at && $this->window_expires_at->isFuture();
    }
}
