<?php

namespace App\Domains\Workflow\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowApproval extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'workflow_approvals';

    protected $fillable = [
        'tenant_id',
        'workflow_run_id',
        'workflow_id',
        'node_id',
        'title',
        'category',
        'status',
        'approver_role',
        'approver_user_id',
        'requested_by_user_id',
        'action_type',
        'action_params',
        'context_summary',
        'comments',
        'rejection_reason',
        'decision_by_user_id',
        'decision_at',
        'expires_at',
    ];

    protected $casts = [
        'action_params' => 'array',
        'context_summary' => 'array',
        'decision_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by_user_id');
    }
}
