<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppAiExecutionLog extends Model
{
    protected $table = 'whatsapp_ai_execution_logs';

    protected $fillable = [
        'tenant_id',
        'agent_id',
        'conversation_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'latency_ms',
        'tool_calls',
        'knowledge_citations',
        'decision_trace',
        'user_query',
        'ai_response',
        'status',
        'error_message',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost_usd' => 'decimal:6',
        'latency_ms' => 'integer',
        'tool_calls' => 'array',
        'knowledge_citations' => 'array',
        'decision_trace' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAiAgent::class, 'agent_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }
}
