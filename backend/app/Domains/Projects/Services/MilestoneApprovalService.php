<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\MilestoneApprovalLog;
use App\Domains\Projects\Models\ProjectMilestone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class MilestoneApprovalService
{
    /**
     * Submit milestone for internal management review
     */
    public function submitForInternalReview(ProjectMilestone $milestone, int $userId, ?string $summary = null): ProjectMilestone
    {
        if ($summary !== null) {
            $milestone->deliverables_summary = $summary;
        }
        $milestone->approval_status = 'pending_review';
        $milestone->save();

        $user = User::find($userId);

        MilestoneApprovalLog::create([
            'tenant_id' => $milestone->tenant_id,
            'milestone_id' => $milestone->id,
            'actor_type' => 'internal',
            'actor_id' => $userId,
            'actor_name' => $user?->name ?? 'Team Member',
            'action' => 'submitted',
            'notes' => $summary ?: 'Submitted milestone deliverables for internal QA review',
        ]);

        return $milestone->fresh(['approvalLogs', 'project']);
    }

    /**
     * Approve milestone internally (by Project Manager or Team Lead)
     */
    public function approveInternal(ProjectMilestone $milestone, int $userId, ?string $notes = null): ProjectMilestone
    {
        $milestone->approval_status = 'internal_approved';
        $milestone->internal_reviewed_by = $userId;
        $milestone->internal_reviewed_at = Carbon::now();
        $milestone->save();

        $user = User::find($userId);

        MilestoneApprovalLog::create([
            'tenant_id' => $milestone->tenant_id,
            'milestone_id' => $milestone->id,
            'actor_type' => 'internal',
            'actor_id' => $userId,
            'actor_name' => $user?->name ?? 'Project Manager',
            'action' => 'internal_approved',
            'notes' => $notes ?: 'Approved deliverables in internal QA review',
        ]);

        return $milestone->fresh(['approvalLogs', 'project', 'internalReviewer']);
    }

    /**
     * Generate client approval token and send to client
     */
    public function sendToClient(ProjectMilestone $milestone, int $userId, ?string $notes = null): ProjectMilestone
    {
        if (empty($milestone->client_review_token)) {
            $milestone->client_review_token = Str::random(40);
        }
        $milestone->approval_status = 'sent_to_client';
        $milestone->save();

        $user = User::find($userId);

        MilestoneApprovalLog::create([
            'tenant_id' => $milestone->tenant_id,
            'milestone_id' => $milestone->id,
            'actor_type' => 'internal',
            'actor_id' => $userId,
            'actor_name' => $user?->name ?? 'Project Manager',
            'action' => 'sent_to_client',
            'notes' => $notes ?: 'Dispatched milestone review package to client portal',
        ]);

        return $milestone->fresh(['approvalLogs', 'project']);
    }

    /**
     * Client approves milestone deliverables via portal/token
     */
    public function clientApprove(ProjectMilestone $milestone, string $clientName, ?string $feedback = null): ProjectMilestone
    {
        $milestone->approval_status = 'client_approved';
        $milestone->client_approved_at = Carbon::now();
        $milestone->client_feedback = $feedback;
        $milestone->status = 'completed';
        $milestone->progress = 100;
        $milestone->completed_at = Carbon::now();
        $milestone->save();

        MilestoneApprovalLog::create([
            'tenant_id' => $milestone->tenant_id,
            'milestone_id' => $milestone->id,
            'actor_type' => 'client',
            'actor_id' => null,
            'actor_name' => $clientName,
            'action' => 'client_approved',
            'notes' => $feedback ?: 'Milestone approved by client',
        ]);

        if ($milestone->project) {
            $milestone->project->recalculateProgress();
        }

        return $milestone->fresh(['approvalLogs', 'project']);
    }

    /**
     * Client requests revision on milestone deliverables
     */
    public function clientRequestRevision(ProjectMilestone $milestone, string $clientName, string $feedback): ProjectMilestone
    {
        $milestone->approval_status = 'revision_requested';
        $milestone->client_feedback = $feedback;
        $milestone->save();

        MilestoneApprovalLog::create([
            'tenant_id' => $milestone->tenant_id,
            'milestone_id' => $milestone->id,
            'actor_type' => 'client',
            'actor_id' => null,
            'actor_name' => $clientName,
            'action' => 'revision_requested',
            'notes' => $feedback,
        ]);

        return $milestone->fresh(['approvalLogs', 'project']);
    }
}
