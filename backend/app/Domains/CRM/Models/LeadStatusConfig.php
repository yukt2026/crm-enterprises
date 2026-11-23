<?php

namespace App\Domains\CRM\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadStatusConfig extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'status_key',
        'label',
        'color',
        'display_order',
        'is_converted',
        'is_lost',
        'allowed_transitions',
    ];

    protected $casts = [
        'is_converted' => 'boolean',
        'is_lost' => 'boolean',
        'allowed_transitions' => 'array',
        'display_order' => 'integer',
    ];
}
