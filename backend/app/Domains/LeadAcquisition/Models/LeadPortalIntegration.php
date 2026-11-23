<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadPortalIntegration extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'portal',
        'name',
        'api_key',
        'webhook_secret',
        'is_active',
        'auto_assign',
        'auto_score',
        'default_campaign',
        'total_captured_count',
        'last_sync_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_assign' => 'boolean',
        'auto_score' => 'boolean',
        'total_captured_count' => 'integer',
        'last_sync_at' => 'datetime',
    ];
}
