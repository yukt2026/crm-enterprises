<?php

namespace App\Domains\Projects\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'title',
        'description',
        'due_date',
        'status', // pending, in_progress, completed
        'progress',
        'completed_at',
        'amount',
        'billing_percentage',
        'deliverables_summary',
        'approval_status', // pending_review, internal_approved, sent_to_client, client_approved, revision_requested, rejected
        'client_review_token',
        'client_feedback',
        'internal_reviewed_by',
        'internal_reviewed_at',
        'client_approved_at',
        'order_index',
    ];

    protected $casts = [
        'due_date' => 'date',
        'progress' => 'integer',
        'completed_at' => 'datetime',
        'amount' => 'decimal:2',
        'billing_percentage' => 'decimal:2',
        'internal_reviewed_at' => 'datetime',
        'client_approved_at' => 'datetime',
        'order_index' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function internalReviewer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'internal_reviewed_by');
    }

    public function approvalLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MilestoneApprovalLog::class, 'milestone_id');
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class, 'milestone_id');
    }
}
