<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppAiAgent extends Model
{
    use SoftDeletes;

    protected $table = 'whatsapp_ai_agents';

    protected $fillable = [
        'tenant_id',
        'whatsapp_phone_number_id',
        'name',
        'description',
        'status',
        'primary_language',
        'supported_languages',
        'tone',
        'system_prompt',
        'business_hours',
        'welcome_message',
        'fallback_message',
        'human_handover_conditions',
        'conversation_rules',
        'allowed_tools',
        'model_provider',
        'model_name',
        'temperature',
        'max_tokens',
        'monthly_token_budget',
        'used_tokens_this_month',
        'version',
    ];

    protected $casts = [
        'supported_languages' => 'array',
        'business_hours' => 'array',
        'human_handover_conditions' => 'array',
        'conversation_rules' => 'array',
        'allowed_tools' => 'array',
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'monthly_token_budget' => 'integer',
        'used_tokens_this_month' => 'integer',
        'version' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WhatsAppAiAgentVersion::class, 'agent_id')->orderBy('version', 'desc');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(WhatsAppAiExecutionLog::class, 'agent_id')->orderBy('created_at', 'desc');
    }

    /**
     * Check if monthly token budget is exceeded.
     */
    public function hasBudgetRemaining(): bool
    {
        return $this->used_tokens_this_month < $this->monthly_token_budget;
    }
}
