<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Voice\Models\VoiceCallingCampaign;
use App\Domains\Voice\Services\VoiceCampaignService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceCallingCampaignController extends Controller
{
    protected VoiceCampaignService $campaignService;

    public function __construct(VoiceCampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $campaigns = VoiceCallingCampaign::where('tenant_id', $tenantId)
            ->with(['agent:id,name,language,voice_engine'])
            ->withCount('calls')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $campaigns,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'voice_agent_id' => 'required|integer',
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'target_filters' => 'nullable|array',
            'max_retries' => 'nullable|integer|min:0|max:5',
            'retry_interval_hours' => 'nullable|integer|min:1|max:48',
            'calls_per_minute_limit' => 'nullable|integer|min:1|max:50',
            'scheduled_start_at' => 'nullable|date',
        ]);

        $campaign = VoiceCallingCampaign::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'status' => 'draft',
        ]));

        return response()->json([
            'success' => true,
            'data' => $campaign->load('agent'),
            'message' => 'Calling campaign created successfully.',
        ], 201);
    }

    public function launch(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = VoiceCallingCampaign::where('tenant_id', $tenantId)->findOrFail($id);

        $limit = (int) $request->input('batch_limit', 5);

        try {
            $batchResult = $this->campaignService->executeCampaignBatch($campaign, $limit);

            return response()->json([
                'success' => true,
                'data' => $batchResult,
                'message' => "Campaign launched: {$batchResult['dispatched_calls']} calls dispatched, {$batchResult['skipped_dnd']} DND skipped.",
            ]);
        } catch (\DomainException $de) {
            return response()->json(['success' => false, 'message' => $de->getMessage()], 422);
        }
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = VoiceCallingCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign->status = 'paused';
        $campaign->save();

        return response()->json([
            'success' => true,
            'data' => $campaign,
            'message' => 'Campaign paused.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $campaign = VoiceCallingCampaign::where('tenant_id', $tenantId)->findOrFail($id);
        $campaign->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaign deleted successfully.',
        ]);
    }
}
