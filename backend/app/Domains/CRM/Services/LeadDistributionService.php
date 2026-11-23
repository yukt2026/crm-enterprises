<?php

namespace App\Domains\CRM\Services;

use App\Domains\Communications\Services\NotificationService;
use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\LeadAssignment;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadDistributionService
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Manually assign or reassign a lead to a specific user
     */
    public function assignLead(
        Lead $lead,
        int $assignToUserId,
        ?int $assignedByUserId = null,
        ?string $reason = 'Manual Assignment',
        string $assignmentType = 'manual'
    ): Lead {
        $previousUserId = $lead->assigned_user_id;
        $isReassignment = $previousUserId && $previousUserId !== $assignToUserId;

        $targetUser = User::findOrFail($assignToUserId);
        $tenantId = $lead->tenant_id ?? TenantContext::getTenantId() ?? 1;

        $lead->assigned_user_id = $assignToUserId;
        $lead->save();

        // 1. Record Assignment History
        LeadAssignment::create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead->id,
            'assigned_by_user_id' => $assignedByUserId,
            'assigned_to_user_id' => $assignToUserId,
            'team_id' => $lead->team_id,
            'assignment_type' => $assignmentType,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        // 2. Log Chronological Activity on Lead
        CrmActivity::create([
            'tenant_id' => $tenantId,
            'user_id' => $assignedByUserId,
            'lead_id' => $lead->id,
            'activity_type' => 'assignment',
            'title' => $isReassignment ? "Lead Reassigned to {$targetUser->name}" : "Lead Assigned to {$targetUser->name}",
            'description' => "Reason: {$reason} • Assignment mode: {$assignmentType}",
            'metadata' => [
                'previous_user_id' => $previousUserId,
                'new_user_id' => $assignToUserId,
                'assignment_type' => $assignmentType,
            ],
        ]);

        // 3. Dispatch In-App & Email Notification
        $eventType = $isReassignment ? 'lead.reassigned' : 'lead.assigned';
        $title = $isReassignment ? "Lead Reassigned: {$lead->name}" : "New Lead Assigned: {$lead->name}";
        $message = "You have been assigned lead {$lead->name} (" . ($lead->company_name ?: 'Direct Lead') . ").";

        $this->notificationService->notify(
            recipient: $targetUser,
            type: $eventType,
            title: $title,
            message: $message,
            actionUrl: "/leads/{$lead->id}",
            data: [
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'assigned_by' => $assignedByUserId,
            ]
        );

        return $lead;
    }

    /**
     * Round-robin load-balanced distribution of unassigned leads
     */
    public function autoDistributeRoundRobin(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getTenantId() ?? 1;

        // 1. Get eligible active sales representatives in tenant workspace
        $eligibleUsers = User::whereHas('workspaces', function ($q) use ($tenantId) {
            $q->where('tenants.id', $tenantId);
        })->get();

        if ($eligibleUsers->isEmpty()) {
            $eligibleUsers = User::limit(5)->get();
        }

        if ($eligibleUsers->isEmpty()) {
            return ['distributed_count' => 0, 'message' => 'No active sales reps available for distribution.'];
        }

        // 2. Find unassigned active leads in this tenant
        $unassignedLeads = Lead::where('tenant_id', $tenantId)
            ->whereNull('assigned_user_id')
            ->where('is_archived', false)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($unassignedLeads->isEmpty()) {
            return ['distributed_count' => 0, 'message' => 'No unassigned leads in queue.'];
        }

        // 3. Track current workload (active leads per user)
        $userWorkloads = Lead::where('tenant_id', $tenantId)
            ->whereNotNull('assigned_user_id')
            ->where('is_archived', false)
            ->groupBy('assigned_user_id')
            ->select('assigned_user_id', DB::raw('count(*) as active_leads'))
            ->pluck('active_leads', 'assigned_user_id')
            ->toArray();

        $assignedResults = [];

        foreach ($unassignedLeads as $lead) {
            // Pick user with lowest active count
            $selectedUser = $eligibleUsers->sortBy(function ($u) use ($userWorkloads) {
                return $userWorkloads[$u->id] ?? 0;
            })->first();

            $this->assignLead(
                lead: $lead,
                assignToUserId: $selectedUser->id,
                assignedByUserId: null,
                reason: 'Automated Round-Robin Workload Balancer',
                assignmentType: 'round_robin'
            );

            $userWorkloads[$selectedUser->id] = ($userWorkloads[$selectedUser->id] ?? 0) + 1;
            $assignedResults[] = [
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'assigned_to' => $selectedUser->name,
                'assigned_to_id' => $selectedUser->id,
            ];
        }

        return [
            'distributed_count' => count($assignedResults),
            'assignments' => $assignedResults,
        ];
    }
}
