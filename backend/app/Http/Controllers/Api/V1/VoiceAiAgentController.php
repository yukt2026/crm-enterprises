<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Voice\Models\VoiceAiAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceAiAgentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $agents = VoiceAiAgent::where('tenant_id', $tenantId)
            ->with(['phoneNumber:id,phone_number,display_name'])
            ->withCount(['calls', 'campaigns'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $agents,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'business_purpose' => 'nullable|string|in:lead_qualification,customer_follow_up,appointment_confirmation,renewal_reminder,service_enquiry,payment_reminder,feedback_collection,incoming_enquiry',
            'telephony_phone_number_id' => 'nullable|integer',
            'status' => 'required|in:active,paused,draft',
            'language' => 'required|string|max:32',
            'regional_dialects' => 'nullable|array',
            'voice_engine' => 'required|string|max:64',
            'voice_id' => 'required|string|max:128',
            'tone' => 'required|in:consultative,professional,friendly,persuasive',
            'system_instructions' => 'required|string',
            'call_opening' => 'required|string',
            'knowledge_base' => 'nullable|string',
            'qualification_script' => 'nullable|array',
            'confidence_threshold' => 'nullable|numeric|min:0.1|max:1.0',
            'max_call_duration_seconds' => 'nullable|integer|min:30|max:3600',
            'human_transfer_number' => 'nullable|string|max:32',
            'allowed_crm_actions' => 'nullable|array',
            'escalation_rules' => 'nullable|array',
            'business_hours' => 'nullable|array',
            'monthly_call_minutes_budget' => 'nullable|integer|min:10',
        ]);

        $agent = VoiceAiAgent::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'version' => 1,
        ]));

        return response()->json([
            'success' => true,
            'data' => $agent->load('phoneNumber'),
            'message' => 'Voice AI Agent created successfully.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $agent = VoiceAiAgent::where('tenant_id', $tenantId)
            ->with(['phoneNumber'])
            ->withCount(['calls', 'campaigns'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $agent = VoiceAiAgent::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'description' => 'nullable|string',
            'business_purpose' => 'nullable|string|in:lead_qualification,customer_follow_up,appointment_confirmation,renewal_reminder,service_enquiry,payment_reminder,feedback_collection,incoming_enquiry',
            'telephony_phone_number_id' => 'nullable|integer',
            'status' => 'sometimes|in:active,paused,draft',
            'language' => 'sometimes|string|max:32',
            'regional_dialects' => 'nullable|array',
            'voice_engine' => 'sometimes|string|max:64',
            'voice_id' => 'sometimes|string|max:128',
            'tone' => 'sometimes|in:consultative,professional,friendly,persuasive',
            'system_instructions' => 'sometimes|string',
            'call_opening' => 'sometimes|string',
            'knowledge_base' => 'nullable|string',
            'qualification_script' => 'nullable|array',
            'confidence_threshold' => 'nullable|numeric|min:0.1|max:1.0',
            'max_call_duration_seconds' => 'nullable|integer|min:30|max:3600',
            'human_transfer_number' => 'nullable|string|max:32',
            'allowed_crm_actions' => 'nullable|array',
            'escalation_rules' => 'nullable|array',
            'business_hours' => 'nullable|array',
            'monthly_call_minutes_budget' => 'nullable|integer|min:10',
        ]);

        $agent->update(array_merge($validated, [
            'version' => $agent->version + 1,
        ]));

        return response()->json([
            'success' => true,
            'data' => $agent->load('phoneNumber'),
            'message' => 'Voice AI Agent updated successfully.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $agent = VoiceAiAgent::where('tenant_id', $tenantId)->findOrFail($id);

        $agent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Voice AI Agent archived successfully.',
        ]);
    }
}
