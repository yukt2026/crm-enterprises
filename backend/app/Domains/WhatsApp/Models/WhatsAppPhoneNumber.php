<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\CRM\Models\Branch;
use App\Domains\HR\Models\Department;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppPhoneNumber extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'whatsapp_phone_numbers';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'phone_number_id',
        'display_phone_number',
        'normalized_number',
        'verified_name',
        'quality_rating', // GREEN, YELLOW, RED, UNKNOWN
        'messaging_limit_tier', // TIER_250, TIER_1K, TIER_10K, TIER_100K, TIER_UNLIMITED
        'status', // CONNECTED, PENDING, DISCONNECTED, FLAGGED
        'channel_status', // connected, flagged, restricted, disconnected
        'default_assigned_user_id',
        'branch_id',
        'department_id',
        'working_hours',
        'default_reply_rules',
        'assigned_team_ids',
        'messages_sent_today',
        'conversations_today',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'working_hours' => 'array',
        'default_reply_rules' => 'array',
        'assigned_team_ids' => 'array',
        'messages_sent_today' => 'integer',
        'conversations_today' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assigned_user_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class, 'whatsapp_phone_number_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(WhatsAppCampaign::class, 'whatsapp_phone_number_id');
    }

    public function automations(): HasMany
    {
        return $this->hasMany(WhatsAppAutomation::class, 'whatsapp_phone_number_id');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(WhatsAppUsageRecord::class, 'whatsapp_phone_number_id');
    }

    public function aiAgent()
    {
        return $this->hasOne(WhatsAppAiAgent::class, 'whatsapp_phone_number_id')->where('status', 'active');
    }
}
