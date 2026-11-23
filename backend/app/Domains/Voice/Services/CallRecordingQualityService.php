<?php

namespace App\Domains\Voice\Services;

use App\Models\User;
use App\Domains\Identity\Services\RbacService;
use App\Domains\Voice\Models\CallQualityReview;
use App\Domains\Voice\Models\CallRecordingAccessLog;
use App\Domains\Voice\Models\VoiceCall;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CallRecordingQualityService
{
    public function __construct(
        protected RbacService $rbacService
    ) {}

    /**
     * Verify if a user is permitted to listen to a specific call recording.
     */
    public function canAccessRecording(User $user, VoiceCall $call): bool
    {
        // Business admin / workspace owner always has access
        $role = $user->role ?? '';
        if (in_array($role, ['Business Admin', 'Business Owner', 'Organization Owner', 'Super Admin']) ||
            $this->rbacService->isOrganizationOwner($user, $call->tenant_id)) {
            return true;
        }

        // Check if user has permission to view all recordings
        if ($this->rbacService->can($user, 'voice', 'view_all_recordings')) {
            return true;
        }

        // Check if user has permission to view their own recordings
        if ($this->rbacService->can($user, 'voice', 'view_own_recordings')) {
            return $call->assigned_user_id === $user->id || $call->transfer_to_user_id === $user->id;
        }

        // Fallback standard view permission
        return $this->rbacService->can($user, 'voice', 'view', $call);
    }

    /**
     * Verify if a user is permitted to download a call recording.
     */
    public function canDownloadRecording(User $user, VoiceCall $call): bool
    {
        $role = $user->role ?? '';
        if (in_array($role, ['Business Admin', 'Business Owner', 'Organization Owner', 'Super Admin']) ||
            $this->rbacService->isOrganizationOwner($user, $call->tenant_id)) {
            return true;
        }

        return $this->rbacService->can($user, 'voice', 'download_recordings')
            || $this->rbacService->can($user, 'voice', 'download');
    }

    /**
     * Log a recording playback or download event for compliance auditing.
     */
    public function logRecordingAccess(
        User $user,
        VoiceCall $call,
        string $action = 'listen',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): CallRecordingAccessLog {
        return CallRecordingAccessLog::create([
            'tenant_id' => $call->tenant_id,
            'voice_call_id' => $call->id,
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 250) : null,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Submit a manager quality review for a call.
     */
    public function submitQualityReview(
        User $manager,
        VoiceCall $call,
        int $score,
        ?array $criteriaScores = null,
        ?string $feedback = null,
        ?array $tags = null
    ): CallQualityReview {
        if ($score < 1 || $score > 5) {
            throw ValidationException::withMessages(['score' => 'Quality score must be between 1 and 5.']);
        }

        $review = CallQualityReview::create([
            'tenant_id' => $call->tenant_id,
            'voice_call_id' => $call->id,
            'reviewer_user_id' => $manager->id,
            'score' => $score,
            'criteria_scores' => $criteriaScores ?: [
                'greeting' => $score,
                'compliance' => 5,
                'product_knowledge' => $score,
                'resolution' => $score,
            ],
            'feedback' => $feedback,
            'tags' => $tags ?: ['audited'],
        ]);

        // Update the primary call record summary
        $call->quality_score = $score;
        $call->manager_feedback = $feedback;
        $call->save();

        return $review;
    }
}
