<?php

namespace App\Domains\Integrations\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSyncLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'integration_id',
        'job_id',
        'sync_type',
        'status',
        'items_processed',
        'items_failed',
        'error_details',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'items_processed' => 'integer',
        'items_failed' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(TenantIntegration::class, 'integration_id');
    }
}
