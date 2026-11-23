<?php

namespace App\Domains\LeadAcquisition\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\DuplicateMergeLog;
use App\Domains\CRM\Models\LeadAssignment;
use App\Domains\CRM\Services\LeadScoringService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\LeadAcquisition\Models\LeadFollowup;
use App\Domains\LeadAcquisition\Models\LeadIngestionEvent;
use App\Domains\LeadAcquisition\Models\LeadMergeRequest;
use App\Domains\LeadAcquisition\Models\LeadStageHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadDuplicateMergeService
{
    public function __construct(
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Submit a formal merge request
     */
    public function requestMerge(
        Lead $master,
        Lead $duplicate,
        array $fieldOverrides = [],
        array $similarityReasons = [],
        int $requestedByUserId = 1
    ): LeadMergeRequest {
        return LeadMergeRequest::create([
            'tenant_id' => $master->tenant_id,
            'master_lead_id' => $master->id,
            'duplicate_lead_id' => $duplicate->id,
            'status' => 'pending',
            'requested_by_user_id' => $requestedByUserId,
            'field_overrides' => $fieldOverrides,
            'similarity_reasons' => $similarityReasons,
            'created_at' => now(),
        ]);
    }

    /**
     * Approve and execute merge
     */
    public function approveMerge(LeadMergeRequest $request, int $approverUserId): Lead
    {
        return DB::transaction(function () use ($request, $approverUserId) {
            $master = $request->masterLead;
            $duplicate = $request->duplicateLead;

            if (!$master || !$duplicate) {
                throw new \InvalidArgumentException("Master or duplicate lead record not found.");
            }

            // 1. Capture snapshot of duplicate lead before merge
            $duplicateSnapshot = $duplicate->toArray();

            // 2. Apply field overrides onto master
            $overrides = $request->field_overrides ?? [];
            foreach ($overrides as $field => $val) {
                if (in_array($field, ['name', 'phone', 'alternate_phone', 'email', 'company_name', 'requirement', 'product_interest', 'estimated_value', 'city', 'state', 'country', 'notes'])) {
                    $master->{$field} = $val;
                }
            }

            // Merge tags
            $masterTags = is_array($master->tags) ? $master->tags : [];
            $dupTags = is_array($duplicate->tags) ? $duplicate->tags : [];
            $master->tags = array_values(array_unique(array_merge($masterTags, $dupTags, ['merged_duplicate'])));

            $master->save();

            // 3. Re-link child records from duplicate to master
            CrmActivity::where('lead_id', $duplicate->id)->update(['lead_id' => $master->id]);
            LeadFollowup::where('lead_id', $duplicate->id)->update(['lead_id' => $master->id]);
            LeadAssignment::where('lead_id', $duplicate->id)->update(['lead_id' => $master->id]);
            LeadStageHistory::where('lead_id', $duplicate->id)->update(['lead_id' => $master->id]);
            LeadIngestionEvent::where('lead_id', $duplicate->id)->update(['lead_id' => $master->id]);

            // 4. Save duplicate merge audit log
            DuplicateMergeLog::create([
                'tenant_id' => $master->tenant_id,
                'user_id' => $approverUserId,
                'entity_type' => 'lead',
                'master_record_id' => $master->id,
                'merged_record_id' => $duplicate->id,
                'preserved_fields' => array_keys($overrides),
                'merged_data' => $duplicateSnapshot,
                'created_at' => now(),
            ]);

            // 5. Update request status
            $request->update([
                'status' => 'approved',
                'approved_by_user_id' => $approverUserId,
                'merged_at' => now(),
            ]);

            // 6. Soft delete duplicate lead
            $duplicate->is_archived = true;
            $duplicate->notes = ($duplicate->notes ? $duplicate->notes . "\n" : "") . "[Merged into Lead #{$master->id} on " . now()->toIso8601String() . "]";
            $duplicate->save();
            $duplicate->delete(); // Soft delete

            // 7. Recalculate master score
            $this->scoringService->updateLeadScore($master);

            // 8. Record merge activity on master
            $approverName = User::find($approverUserId)?->name ?? "User #{$approverUserId}";
            CrmActivity::create([
                'tenant_id' => $master->tenant_id,
                'user_id' => $approverUserId,
                'lead_id' => $master->id,
                'activity_type' => 'status_change',
                'title' => "Duplicate Lead #{$duplicate->id} Merged",
                'description' => "Duplicate lead '{$duplicate->name}' successfully merged into this master record by {$approverName}.",
                'metadata' => [
                    'duplicate_lead_id' => $duplicate->id,
                    'overridden_fields' => array_keys($overrides),
                ],
            ]);

            return $master->fresh(['assignedUser', 'company', 'followups', 'activities']);
        });
    }

    /**
     * Reject a merge request
     */
    public function rejectMerge(LeadMergeRequest $request, string $reason, int $userId): LeadMergeRequest
    {
        $request->update([
            'status' => 'rejected',
            'approved_by_user_id' => $userId,
            'rejection_reason' => $reason,
        ]);

        return $request;
    }
}
