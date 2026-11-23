<?php

namespace App\Domains\Workflow\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'workflow_runs';

    protected $fillable = [
        'tenant_id',
        'workflow_id',
        'workflow_version_id',
        'trigger_event',
        'idempotency_key',
        'entity_type',
        'entity_id',
        'status', // pending, running, waiting, completed, failed, cancelled
        'current_node_id',
        'resume_at',
        'wait_for_event',
        'wait_timeout_at',
        'trigger_payload',
        'context_data',
        'retry_count',
        'max_retries',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'context_data' => 'array',
        'resume_at' => 'datetime',
        'wait_timeout_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function stepLogs(): HasMany
    {
        return $this->hasMany(WorkflowStepLog::class, 'workflow_run_id')->orderBy('id', 'asc');
    }
}
