<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DripEnrollment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'drip_sequence_id',
        'entity_type', // lead, contact, customer
        'entity_id',
        'status', // active, paused, completed, exited_conversion, exited_reply, exited_unsubscribed
        'current_step_id',
        'next_execution_at',
        'last_message_sent_at',
        'exit_reason',
        'context_data',
    ];

    protected $casts = [
        'next_execution_at' => 'datetime',
        'last_message_sent_at' => 'datetime',
        'context_data' => 'array',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DripSequence::class, 'drip_sequence_id');
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(DripStep::class, 'current_step_id');
    }
}
