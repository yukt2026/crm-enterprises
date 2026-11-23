<?php

namespace App\Domains\Workflow\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStepLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'workflow_step_logs';

    protected $fillable = [
        'tenant_id',
        'workflow_run_id',
        'node_id',
        'node_type', // trigger, condition, action, delay, wait, stop
        'node_name',
        'status', // running, completed, skipped, failed, waiting
        'input_state',
        'output_state',
        'branch_taken',
        'error_message',
        'execution_duration_ms',
    ];

    protected $casts = [
        'input_state' => 'array',
        'output_state' => 'array',
        'execution_duration_ms' => 'integer',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }
}
