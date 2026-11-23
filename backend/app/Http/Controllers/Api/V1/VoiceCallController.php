<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Services\RbacService;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Voice\Models\VoiceAiAgent;
use App\Domains\Voice\Models\VoiceCall;
use App\Domains\Voice\Providers\TelephonyProviderManager;
use App\Domains\Voice\Services\AdvancedVoiceAiService;
use App\Domains\Voice\Services\CallRecordingQualityService;
use App\Domains\Voice\Services\IncomingCallManagerService;
use App\Domains\Voice\Services\VoiceCrmAutomationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VoiceCallController extends Controller
{
    public function __construct(
        protected TelephonyProviderManager $providerManager,
        protected AdvancedVoiceAiService $aiService,
        protected IncomingCallManagerService $incomingService,
        protected CallRecordingQualityService $recordingService,
        protected VoiceCrmAutomationService $crmAutomation,
        protected RbacService $rbacService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->current_tenant_id;

        $query = VoiceCall::where('tenant_id', $tenantId)
            ->with([
                'agent:id,name,language,voice_engine',
                'lead:id,name,phone,status,company_name',
                'contact:id,name,phone,email',
                'deal:id,name,value,stage',
                'company:id,name',
                'assignedUser:id,name,email',
                'transferredToUser:id,name',
            ]);

        // Data scoping
        $role = $user->role ?? '';
        $isGlobal = in_array($role, ['Business Admin', 'Business Owner', 'Organization Owner', 'Super Admin']) ||
            $this->rbacService->isOrganizationOwner($user, $tenantId);

        if (!$isGlobal) {
            if (!$this->rbacService->can($user, 'voice', 'view_all_recordings') &&
                !$this->rbacService->can($user, 'voice', 'view')) {
                $query->where('assigned_user_id', $user->id);
            }
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('disposition')) {
            $query->where('disposition', $request->input('disposition'));
        }
        if ($request->filled('agent_id')) {
            $query->where('voice_agent_id', $request->input('agent_id'));
        }
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->input('lead_id'));
        }
        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->input('deal_id'));
        }
        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->input('contact_id'));
        }
        if ($request->filled('call_type')) {
            $query->where('call_type', $request->input('call_type'));
        }
        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('to_number', 'like', "%{$term}%")
                  ->orWhere('from_number', 'like', "%{$term}%")
                  ->orWhere('transcript', 'like', "%{$term}%")
                  ->orWhere('ai_summary', 'like', "%{$term}%");
            });
        }

        $perPage = min(50, max(1, (int) $request->input('per_page', 20)));
        $calls = $query->orderBy('id', 'desc')->paginate($perPage);

        // Aggregate statistics
        $stats = [
            'total_calls' => VoiceCall::where('tenant_id', $tenantId)->count(),
            'total_duration_minutes' => (int) ceil(VoiceCall::where('tenant_id', $tenantId)->sum('duration_seconds') / 60),
            'total_spend' => (float) VoiceCall::where('tenant_id', $tenantId)->sum('cost_amount'),
            'qualified_leads' => VoiceCall::where('tenant_id', $tenantId)->where('qualification_result', 'qualified')->count(),
            'avg_call_duration_seconds' => (int) VoiceCall::where('tenant_id', $tenantId)->where('status', 'completed')->avg('duration_seconds'),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'data' => $calls->items(),
            'meta' => [
                'current_page' => $calls->currentPage(),
                'last_page' => $calls->lastPage(),
                'per_page' => $calls->perPage(),
                'total' => $calls->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $call = VoiceCall::where('tenant_id', $tenantId)
            ->with([
                'agent',
                'lead',
                'contact',
                'deal',
                'company',
                'campaign',
                'assignedUser',
                'transferredToUser',
                'qualityReviews.reviewer:id,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $call,
        ]);
    }

    /**
     * Outbound Call Initiation (AI Voice Agent).
     */
    public function initiateCall(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->current_tenant_id;

        $validated = $request->validate([
            'to_number' => 'required|string|max:32',
            'voice_agent_id' => 'required|integer',
            'lead_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'provider' => 'nullable|in:exotel,twilio,sip',
        ]);

        $agent = VoiceAiAgent::where('tenant_id', $tenantId)->findOrFail($validated['voice_agent_id']);
        $callerId = $agent->phoneNumber?->phone_number ?: '+919876543210';

        $call = VoiceCall::create([
            'tenant_id' => $tenantId,
            'voice_agent_id' => $agent->id,
            'lead_id' => $validated['lead_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'deal_id' => $validated['deal_id'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'assigned_user_id' => $user->id,
            'direction' => 'outbound',
            'call_type' => 'ai_voice',
            'from_number' => $callerId,
            'to_number' => $validated['to_number'],
            'provider' => $validated['provider'] ?? ($agent->phoneNumber?->provider?->provider ?: 'exotel'),
            'status' => 'queued',
            'recording_notice_played' => true,
        ]);

        try {
            $dispatched = $this->providerManager->dispatchCall($call, $agent);

            return response()->json([
                'success' => true,
                'data' => $dispatched->load(['agent', 'lead', 'deal']),
                'message' => 'Outbound AI voice call initiated successfully.',
            ], 201);
        } catch (\DomainException $de) {
            $call->status = 'failed';
            $call->save();
            return response()->json(['success' => false, 'message' => $de->getMessage()], 422);
        }
    }

    /**
     * Click-to-Call (Direct Outbound Human Agent Call from CRM records).
     */
    public function clickToCall(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->current_tenant_id;

        $validated = $request->validate([
            'to_number' => 'required|string|max:32',
            'from_number' => 'nullable|string|max:32',
            'lead_id' => 'nullable|integer',
            'contact_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'provider' => 'nullable|in:exotel,twilio,sip',
        ]);

        $callerId = $validated['from_number'] ?? '+919876543210';

        $call = VoiceCall::create([
            'tenant_id' => $tenantId,
            'voice_agent_id' => null,
            'lead_id' => $validated['lead_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'deal_id' => $validated['deal_id'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'assigned_user_id' => $user->id,
            'direction' => 'outbound',
            'call_type' => 'human',
            'from_number' => $callerId,
            'to_number' => $validated['to_number'],
            'provider' => $validated['provider'] ?? 'exotel',
            'status' => 'ringing',
            'recording_notice_played' => true,
        ]);

        try {
            $dispatched = $this->providerManager->dispatchHumanCall($call, $user, $callerId);

            return response()->json([
                'success' => true,
                'data' => $dispatched->load(['lead', 'contact', 'deal', 'company', 'assignedUser']),
                'message' => 'Click-to-call connection established.',
            ], 201);
        } catch (\DomainException $de) {
            $call->status = 'failed';
            $call->save();
            return response()->json(['success' => false, 'message' => $de->getMessage()], 422);
        }
    }

    /**
     * Identify caller from CRM databases (populates Caller ID card in softphone).
     */
    public function lookupCaller(Request $request): JsonResponse
    {
        $request->validate(['phone_number' => 'required|string']);
        $tenantId = $request->user()->current_tenant_id;

        $profile = $this->incomingService->identifyCaller($tenantId, $request->input('phone_number'));

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Inbound Call Simulation / Webhook Handler with Deduplication & IVR.
     */
    public function handleInbound(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $validated = $request->validate([
            'from_number' => 'required|string|max:32',
            'to_number' => 'required|string|max:32',
            'provider' => 'nullable|in:exotel,twilio,sip',
            'provider_call_id' => 'nullable|string|max:191',
            'idempotency_key' => 'nullable|string|max:128',
        ]);

        $result = $this->incomingService->handleIncomingCall(
            $tenantId,
            $validated['from_number'],
            $validated['to_number'],
            $validated['provider'] ?? 'exotel',
            $validated['provider_call_id'] ?? null,
            $validated['idempotency_key'] ?? null
        );

        return response()->json([
            'success' => true,
            'is_duplicate' => $result['is_duplicate'],
            'data' => $result['call']->load(['lead', 'contact', 'deal', 'assignedUser']),
            'caller_profile' => $result['caller_profile'] ?? null,
            'routing' => $result['routing'] ?? null,
        ]);
    }

    /**
     * Interactive Playground Simulator Turn with Hindi/Hinglish & Regional Intelligence.
     */
    public function simulatePlaygroundTurn(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $call = VoiceCall::where('tenant_id', $tenantId)->findOrFail($id);
        $agent = $call->agent ?: VoiceAiAgent::where('tenant_id', $tenantId)->first();

        if (!$agent) {
            $agent = new VoiceAiAgent([
                'tenant_id' => $tenantId,
                'name' => 'Default Assistant',
                'language' => 'hi',
                'business_purpose' => 'lead_qualification',
            ]);
        }

        $request->validate([
            'user_speech_text' => 'required|string',
            'is_barge_in' => 'nullable|boolean',
        ]);

        $turn = $this->aiService->processVoiceTurn(
            $call,
            $agent,
            $request->input('user_speech_text'),
            (bool) $request->input('is_barge_in', false)
        );

        if ($turn['should_hangup']) {
            $call->status = 'completed';
            $call->duration_seconds = max(10, $call->duration_seconds + 35);
            $call->save();
            $this->crmAutomation->processPostCallAutomation($call);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($turn, [
                'call' => $call->fresh(['lead', 'contact', 'deal']),
            ]),
        ]);
    }

    /**
     * Update call disposition & post-call CRM logging.
     */
    public function updateDisposition(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $call = VoiceCall::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'nullable|in:ringing,in-progress,completed,busy,no-answer,failed,cancelled',
            'disposition' => 'required|in:interested,not_interested,callback_requested,wrong_number,converted,dnd_requested,unreached,completed',
            'qualification_result' => 'nullable|in:qualified,unqualified,follow_up_needed',
            'duration_seconds' => 'nullable|integer',
            'follow_up_required' => 'nullable|boolean',
            'next_follow_up_at' => 'nullable|date',
            'follow_up_notes' => 'nullable|string',
            'ai_summary' => 'nullable|string',
        ]);

        $call->fill($validated);
        if ($request->filled('duration_seconds')) {
            $call->duration_seconds = (int) $request->input('duration_seconds');
        }
        $call->save();

        // Run post-call CRM synchronization
        $this->crmAutomation->processPostCallAutomation($call);

        return response()->json([
            'success' => true,
            'data' => $call->fresh(['lead', 'contact', 'deal', 'qualityReviews']),
            'message' => 'Call disposition recorded and CRM synced.',
        ]);
    }

    /**
     * Listen to Call Recording with RBAC check and audit logging.
     */
    public function recordingPlayback(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->current_tenant_id;
        $call = VoiceCall::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$this->recordingService->canAccessRecording($user, $call)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not have permission to listen to this call recording.',
            ], 403);
        }

        // Log compliance audit entry
        $this->recordingService->logRecordingAccess(
            $user,
            $call,
            'listen',
            $request->ip(),
            $request->userAgent()
        );

        $recordingUrl = $call->recording_url ?: "https://api.exotel.com/v1/Accounts/mock/Recordings/call_{$call->id}.mp3";

        return response()->json([
            'success' => true,
            'data' => [
                'call_id' => $call->id,
                'recording_url' => $recordingUrl,
                'recording_duration_seconds' => $call->recording_duration_seconds ?: $call->duration_seconds,
                'notice_played' => $call->recording_notice_played,
            ],
        ]);
    }

    /**
     * Download Call Recording with RBAC check and audit logging.
     */
    public function recordingDownload(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->current_tenant_id;
        $call = VoiceCall::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$this->recordingService->canDownloadRecording($user, $call)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not have permission to download call recordings.',
            ], 403);
        }

        $this->recordingService->logRecordingAccess(
            $user,
            $call,
            'download',
            $request->ip(),
            $request->userAgent()
        );

        $downloadUrl = $call->recording_url ?: "https://api.exotel.com/v1/Accounts/mock/Recordings/call_{$call->id}.mp3?download=1";

        return response()->json([
            'success' => true,
            'data' => [
                'call_id' => $call->id,
                'download_url' => $downloadUrl,
                'file_name' => "call_recording_{$call->id}_{$call->to_number}.mp3",
            ],
        ]);
    }

    /**
     * Submit Call Quality Review & Manager Rating.
     */
    public function submitQualityReview(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->current_tenant_id;
        $call = VoiceCall::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'score' => 'required|integer|min:1|max:5',
            'criteria_scores' => 'nullable|array',
            'feedback' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $review = $this->recordingService->submitQualityReview(
            $user,
            $call,
            $validated['score'],
            $validated['criteria_scores'] ?? null,
            $validated['feedback'] ?? null,
            $validated['tags'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $review->load('reviewer:id,name'),
            'message' => 'Quality review submitted successfully.',
        ], 201);
    }

    /**
     * Transfer call to another agent or department queue.
     */
    public function transferCall(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $call = VoiceCall::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'transfer_target' => 'required|string',
            'transfer_to_user_id' => 'nullable|integer',
        ]);

        $call->human_transferred = true;
        $call->transfer_target = $validated['transfer_target'];
        $call->transfer_to_user_id = $validated['transfer_to_user_id'] ?? null;
        $call->hangup_cause = 'TRANSFERRED_TO_HUMAN';
        $call->save();

        return response()->json([
            'success' => true,
            'data' => $call->fresh(['transferredToUser']),
            'message' => "Call successfully transferred to {$call->transfer_target}.",
        ]);
    }
}
