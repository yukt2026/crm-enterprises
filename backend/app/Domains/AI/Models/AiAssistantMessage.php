<?php

namespace App\Domains\AI\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAssistantMessage extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'session_id',
        'role',
        'content',
        'suggested_actions',
        'executed_actions',
        'citations_or_records',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost',
    ];

    protected $casts = [
        'suggested_actions' => 'array',
        'executed_actions' => 'array',
        'citations_or_records' => 'array',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'estimated_cost' => 'float',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AiAssistantSession::class, 'session_id');
    }
}
