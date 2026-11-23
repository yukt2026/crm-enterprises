<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppAiAgentVersion extends Model
{
    protected $table = 'whatsapp_ai_agent_versions';

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'version',
        'system_prompt',
        'welcome_message',
        'fallback_message',
        'allowed_tools',
        'settings_snapshot',
        'changed_by_user_id',
        'change_summary',
    ];

    protected $casts = [
        'version' => 'integer',
        'allowed_tools' => 'array',
        'settings_snapshot' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAiAgent::class, 'agent_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
