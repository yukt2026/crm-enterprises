<?php

namespace App\Domains\AI\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProposedAction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'session_id',
        'action_type',
        'target_entity_type',
        'target_entity_id',
        'summary',
        'payload',
        'requires_confirmation',
        'status',
        'confirmed_by_user_id',
        'confirmed_at',
        'execution_result',
        'rejection_reason',
    ];

    protected $casts = [
        'payload' => 'array',
        'execution_result' => 'array',
        'requires_confirmation' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
