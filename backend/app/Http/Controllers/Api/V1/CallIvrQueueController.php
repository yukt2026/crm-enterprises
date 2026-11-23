<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Voice\Models\CallIvrFlow;
use App\Domains\Voice\Models\CallQueue;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallIvrQueueController extends Controller
{
    // ==================== IVR FLOWS ====================

    public function getIvrFlows(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $flows = CallIvrFlow::where('tenant_id', $tenantId)
            ->with(['phoneNumber:id,phone_number,display_name'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $flows,
        ]);
    }

    public function storeIvrFlow(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'description' => 'nullable|string',
            'phone_number_id' => 'nullable|integer',
            'welcome_audio_text' => 'required|string',
            'language' => 'required|string|max:32',
            'business_hours' => 'nullable|array',
            'holiday_rules' => 'nullable|array',
            'menu_options' => 'nullable|array',
            'unanswered_action' => 'nullable|in:voicemail,callback,transfer_phone,hangup',
            'unanswered_target' => 'nullable|string',
            'voicemail_box' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $flow = CallIvrFlow::create(array_merge($validated, [
            'tenant_id' => $tenantId,
        ]));

        return response()->json([
            'success' => true,
            'data' => $flow->load('phoneNumber'),
            'message' => 'IVR flow created successfully.',
        ], 201);
    }

    public function updateIvrFlow(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $flow = CallIvrFlow::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:128',
            'description' => 'nullable|string',
            'phone_number_id' => 'nullable|integer',
            'welcome_audio_text' => 'sometimes|string',
            'language' => 'sometimes|string|max:32',
            'business_hours' => 'nullable|array',
            'holiday_rules' => 'nullable|array',
            'menu_options' => 'nullable|array',
            'unanswered_action' => 'nullable|in:voicemail,callback,transfer_phone,hangup',
            'unanswered_target' => 'nullable|string',
            'voicemail_box' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $flow->update($validated);

        return response()->json([
            'success' => true,
            'data' => $flow->load('phoneNumber'),
            'message' => 'IVR flow updated successfully.',
        ]);
    }

    public function deleteIvrFlow(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $flow = CallIvrFlow::where('tenant_id', $tenantId)->findOrFail($id);
        $flow->delete();

        return response()->json([
            'success' => true,
            'message' => 'IVR flow deleted successfully.',
        ]);
    }

    // ==================== CALL QUEUES ====================

    public function getQueues(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $queues = CallQueue::where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $queues,
        ]);
    }

    public function storeQueue(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:128',
            'department' => 'nullable|string|max:64',
            'branch_id' => 'nullable|integer',
            'strategy' => 'required|in:round_robin,simultaneous_ring,least_recent,linear',
            'max_wait_time_seconds' => 'nullable|integer|min:15|max:600',
            'music_on_hold' => 'nullable|string',
            'assigned_agent_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $queue = CallQueue::create(array_merge($validated, [
            'tenant_id' => $tenantId,
        ]));

        return response()->json([
            'success' => true,
            'data' => $queue,
            'message' => 'Call queue created successfully.',
        ], 201);
    }

    public function updateQueue(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $queue = CallQueue::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:128',
            'department' => 'nullable|string|max:64',
            'branch_id' => 'nullable|integer',
            'strategy' => 'sometimes|in:round_robin,simultaneous_ring,least_recent,linear',
            'max_wait_time_seconds' => 'nullable|integer|min:15|max:600',
            'music_on_hold' => 'nullable|string',
            'assigned_agent_ids' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $queue->update($validated);

        return response()->json([
            'success' => true,
            'data' => $queue,
            'message' => 'Call queue updated successfully.',
        ]);
    }

    public function deleteQueue(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $queue = CallQueue::where('tenant_id', $tenantId)->findOrFail($id);
        $queue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Call queue deleted successfully.',
        ]);
    }
}
