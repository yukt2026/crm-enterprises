<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DripStep extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'drip_sequence_id',
        'step_order',
        'step_type', // send_message, wait_delay, condition_check, assign_rep, schedule_followup
        'channel', // whatsapp, email, sms
        'delay_interval',
        'delay_unit', // minutes, hours, days
        'template_id',
        'subject',
        'content',
        'variable_mappings',
        'skip_conditions',
        'condition_rules',
    ];

    protected $casts = [
        'variable_mappings' => 'array',
        'skip_conditions' => 'array',
        'condition_rules' => 'array',
        'delay_interval' => 'integer',
        'step_order' => 'integer',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DripSequence::class, 'drip_sequence_id');
    }
}
