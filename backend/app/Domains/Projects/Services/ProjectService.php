<?php

namespace App\Domains\Projects\Services;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectComment;
use App\Domains\Projects\Models\ProjectMilestone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectService
{
    /**
     * Create a new project
     */
    public function createProject(array $data, int $tenantId, ?int $ownerId = null): Project
    {
        $project = Project::create([
            'tenant_id' => $tenantId,
            'customer_id' => $data['customer_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? $ownerId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'not_started',
            'priority' => $data['priority'] ?? 'medium',
            'budget' => $data['budget'] ?? 0,
            'estimated_hours' => $data['estimated_hours'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'progress' => $data['progress'] ?? 0,
            'is_archived' => false,
        ]);

        if (!empty($data['team_members'])) {
            foreach ($data['team_members'] as $member) {
                if (is_array($member)) {
                    $project->teamMembers()->syncWithoutDetaching([
                        $member['user_id'] => ['role' => $member['role'] ?? 'member']
                    ]);
                } else {
                    $project->teamMembers()->syncWithoutDetaching([
                        $member => ['role' => 'member']
                    ]);
                }
            }
        }

        if (!empty($data['milestones'])) {
            foreach ($data['milestones'] as $milestoneData) {
                $this->addMilestone($project, $milestoneData);
            }
        }

        return $project->fresh(['customer', 'company', 'owner', 'teamMembers', 'milestones']);
    }

    /**
     * Update an existing project
     */
    public function updateProject(Project $project, array $data): Project
    {
        if (isset($data['status']) && in_array($data['status'], ['completed', 'done']) && $project->status !== 'completed') {
            $data['completed_at'] = Carbon::now();
            $data['progress'] = 100;
        }

        $project->update($data);

        if (isset($data['team_members'])) {
            $syncData = [];
            foreach ($data['team_members'] as $member) {
                if (is_array($member)) {
                    $syncData[$member['user_id']] = ['role' => $member['role'] ?? 'member'];
                } else {
                    $syncData[$member] = ['role' => 'member'];
                }
            }
            $project->teamMembers()->sync($syncData);
        }

        return $project->fresh(['customer', 'company', 'owner', 'teamMembers', 'milestones']);
    }

    /**
     * Archive project
     */
    public function archiveProject(Project $project): Project
    {
        $project->update([
            'is_archived' => true,
            'archived_at' => Carbon::now(),
        ]);

        return $project;
    }

    /**
     * Restore archived project
     */
    public function restoreProject(Project $project): Project
    {
        $project->update([
            'is_archived' => false,
            'archived_at' => null,
        ]);

        return $project;
    }

    /**
     * Add a milestone to the project
     */
    public function addMilestone(Project $project, array $data): ProjectMilestone
    {
        $milestone = ProjectMilestone::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'progress' => $data['progress'] ?? 0,
            'completed_at' => ($data['status'] ?? '') === 'completed' ? Carbon::now() : null,
        ]);

        $project->recalculateProgress();

        return $milestone;
    }

    /**
     * Update a milestone
     */
    public function updateMilestone(ProjectMilestone $milestone, array $data): ProjectMilestone
    {
        if (isset($data['status']) && $data['status'] === 'completed' && $milestone->status !== 'completed') {
            $data['completed_at'] = Carbon::now();
            $data['progress'] = 100;
        }

        $milestone->update($data);

        $milestone->project->recalculateProgress();

        return $milestone;
    }

    /**
     * Delete milestone
     */
    public function deleteMilestone(ProjectMilestone $milestone): bool
    {
        $project = $milestone->project;
        $deleted = $milestone->delete();
        if ($project) {
            $project->recalculateProgress();
        }
        return (bool) $deleted;
    }

    /**
     * Add team member
     */
    public function addMember(Project $project, int $userId, string $role = 'member'): void
    {
        $project->teamMembers()->syncWithoutDetaching([
            $userId => ['role' => $role]
        ]);
    }

    /**
     * Remove team member
     */
    public function removeMember(Project $project, int $userId): void
    {
        $project->teamMembers()->detach($userId);
    }

    /**
     * Add a project comment / activity note
     */
    public function addComment(Project $project, int $userId, string $comment, ?array $attachments = null): ProjectComment
    {
        return ProjectComment::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'comment' => $comment,
            'attachments' => $attachments,
        ]);
    }

    /**
     * Get overall project statistics
     */
    public function getProjectMetrics(int $tenantId): array
    {
        $base = Project::where('tenant_id', $tenantId);

        $total = (clone $base)->count();
        $active = (clone $base)->where('is_archived', false)->whereNotIn('status', ['completed', 'cancelled'])->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $onHold = (clone $base)->where('status', 'on_hold')->count();
        $totalBudget = (clone $base)->sum('budget');
        $avgProgress = (clone $base)->where('is_archived', false)->avg('progress') ?? 0;

        return [
            'total_projects' => $total,
            'active_projects' => $active,
            'completed_projects' => $completed,
            'on_hold_projects' => $onHold,
            'total_budget' => round((float) $totalBudget, 2),
            'average_progress' => round((float) $avgProgress, 1),
        ];
    }
}
