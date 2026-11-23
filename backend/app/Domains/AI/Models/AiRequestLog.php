<?php

namespace App\Domains\AI\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'provider',
        'model',
        'feature_code',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost_incurred',
        'latency_ms',
        'status',
        'error_message',
        'recorded_date',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost_incurred' => 'float',
        'latency_ms' => 'integer',
        'recorded_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
