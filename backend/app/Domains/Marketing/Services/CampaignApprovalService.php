<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\CampaignApproval;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CampaignApprovalService
{
    /**
     * Submit a campaign draft for review/approval.
     */
    public function submitForApproval(MarketingCampaign $campaign, User $user, ?string $comments = null): MarketingCampaign
    {
        if (!in_array($campaign->status, ['draft', 'rejected'])) {
            throw new InvalidArgumentException("Only draft or rejected campaigns can be submitted for approval.");
        }

        return DB::transaction(function () use ($campaign, $user, $comments) {
            $campaign->update([
                'approval_status' => 'pending_approval',
                'status' => 'in_review',
                'rejection_reason' => null,
            ]);

            CampaignApproval::create([
                'tenant_id' => $campaign->tenant_id,
                'campaign_id' => $campaign->id,
                'approver_user_id' => $user->id,
                'role_level' => $user->role ?? 'marketing_executive',
                'action' => 'requested',
                'comments' => $comments ?: 'Campaign submitted for manager/admin approval.',
            ]);

            return $campaign->fresh();
        });
    }

    /**
     * Approve a campaign.
     * Checks if high-budget or high-audience campaigns require Admin approval.
     */
    public function approve(MarketingCampaign $campaign, User $user, ?string $comments = null): MarketingCampaign
    {
        if ($campaign->approval_status !== 'pending_approval') {
            throw new InvalidArgumentException("Campaign is not currently pending approval.");
        }

        $userRole = strtolower($user->role ?? '');
        $isAdmin = in_array($userRole, ['admin', 'super admin', 'business admin', 'business owner', 'organization owner']);

        // Check high threshold: budget > 50,000 INR or recipients > 10,000
        $isHighValue = ((float) $campaign->budget > 50000) || ($campaign->total_recipients > 10000);
        if ($isHighValue && !$isAdmin) {
            // If submitted to manager but exceeds high-value threshold, flag requirement
            throw new InvalidArgumentException("High-value campaigns (budget > ₹50,000 or audience > 10,000) require Organization Admin approval.");
        }

        return DB::transaction(function () use ($campaign, $user, $comments, $isAdmin) {
            $nextStatus = $campaign->scheduled_at && $campaign->scheduled_at->isFuture() ? 'scheduled' : 'ready';

            $campaign->update([
                'approval_status' => 'approved',
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'status' => $nextStatus,
                'rejection_reason' => null,
            ]);

            CampaignApproval::create([
                'tenant_id' => $campaign->tenant_id,
                'campaign_id' => $campaign->id,
                'approver_user_id' => $user->id,
                'role_level' => $isAdmin ? 'org_admin' : 'marketing_manager',
                'action' => 'approved',
                'comments' => $comments ?: 'Campaign approved for launch/schedule.',
            ]);

            return $campaign->fresh();
        });
    }

    /**
     * Reject a campaign with a reason.
     */
    public function reject(MarketingCampaign $campaign, User $user, string $reason): MarketingCampaign
    {
        if ($campaign->approval_status !== 'pending_approval') {
            throw new InvalidArgumentException("Only campaigns pending approval can be rejected.");
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException("A rejection reason must be provided.");
        }

        return DB::transaction(function () use ($campaign, $user, $reason) {
            $campaign->update([
                'approval_status' => 'rejected',
                'status' => 'draft',
                'rejection_reason' => $reason,
                'approved_by_user_id' => null,
                'approved_at' => null,
            ]);

            CampaignApproval::create([
                'tenant_id' => $campaign->tenant_id,
                'campaign_id' => $campaign->id,
                'approver_user_id' => $user->id,
                'role_level' => $user->role ?? 'marketing_manager',
                'action' => 'rejected',
                'comments' => $reason,
            ]);

            return $campaign->fresh();
        });
    }
}
