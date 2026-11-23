<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\CRM\Models\Branch;
use App\Domains\HR\Models\Department;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Domains\WhatsApp\Models\WhatsAppAiAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramAccount extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'telegram_accounts';

    protected $fillable = [
        'tenant_id',
        'bot_name',
        'bot_username',
        'bot_token',
        'is_connected',
        'webhook_url',
        'webhook_secret_token',
        'branch_id',
        'department_id',
        'business_hours',
        'ai_enabled',
        'ai_agent_id',
        'metadata',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'ai_enabled' => 'boolean',
        'bot_token' => 'encrypted',
        'business_hours' => 'array',
        'metadata' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAiAgent::class, 'ai_agent_id');
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('is_connected', true);
    }
}
