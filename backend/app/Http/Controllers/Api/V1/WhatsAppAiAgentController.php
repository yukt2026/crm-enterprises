<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\WhatsApp\AI\WhatsAppAiAgentService;
use App\Domains\WhatsApp\Models\WhatsAppAiAgent;
use App\Domains\WhatsApp\Models\WhatsAppAiAgentVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppAiAgentController extends Controller
{
    protected WhatsAppAiAgentService $agentService;

    public function __construct(WhatsAppAiAgentService $agentService)
    {
        $this->agentService = $agentService;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $agents = WhatsAppAiAgent::where('tenant_id', $tenantId)
            ->with(['phoneNumber:id,display_phone_number,verified_name'])
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
            'whatsapp_phone_number_id' => 'nullable|integer',
            'status' => 'required|in:active,paused,draft',
            'primary_language' => 'required|string|max:32',
            'supported_languages' => 'nullable|array',
            'tone' => 'required|in:professional,friendly,consultative,assertive',
            'system_prompt' => 'required|string',
            'welcome_message' => 'nullable|string',
            'fallback_message' => 'nullable|string',
            'allowed_tools' => 'nullable|array',
            'human_handover_conditions' => 'nullable|array',
            'conversation_rules' => 'nullable|array',
            'model_provider' => 'nullable|string',
            'model_name' => 'nullable|string',
            'temperature' => 'nullable|numeric|between:0,1',
            'max_tokens' => 'nullable|integer|min:64|max:4096',
            'monthly_token_budget' => 'nullable|integer|min:1000',
        ]);

        $agent = DB::transaction(function () use ($tenantId, $validated, $request) {
            $agent = WhatsAppAiAgent::create(array_merge($validated, [
                'tenant_id' => $tenantId,
                'version' => 1,
            ]));

            // Record initial version
            WhatsAppAiAgentVersion::create([
                'tenant_id' => $tenantId,
                'agent_id' => $agent->id,
                'version' => 1,
                'system_prompt' => $agent->system_prompt,
                'welcome_message' => $agent->welcome_message,
                'fallback_message' => $agent->fallback_message,
                'allowed_tools' => $agent->allowed_tools,
                'settings_snapshot' => $agent->toArray(),
                'changed_by_user_id' => $request->user()->id,
                'change_summary' => 'Initial agent creation',
            ]);

            return $agent;
        });

        return response()->json([
            'success' => true,
            'data' => $agent->load('phoneNumber'),
            'message' => 'AI Agent created successfully',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $agent = WhatsAppAiAgent::where('tenant_id', $tenantId)
            ->with(['phoneNumber', 'versions.changedByUser:id,name'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $agent,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $agent = WhatsAppAiAgent::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:191',
            'description' => 'nullable|string',
            'whatsapp_phone_number_id' => 'nullable|integer',
            'status' => 'sometimes|in:active,paused,draft',
            'primary_language' => 'sometimes|string|max:32',
            'supported_languages' => 'nullable|array',
            'tone' => 'sometimes|in:professional,friendly,consultative,assertive',
            'system_prompt' => 'sometimes|string',
            'welcome_message' => 'nullable|string',
            'fallback_message' => 'nullable|string',
            'allowed_tools' => 'nullable|array',
            'human_handover_conditions' => 'nullable|array',
            'conversation_rules' => 'nullable|array',
            'model_provider' => 'nullable|string',
            'model_name' => 'nullable|string',
            'temperature' => 'nullable|numeric|between:0,1',
            'max_tokens' => 'nullable|integer|min:64|max:4096',
            'monthly_token_budget' => 'nullable|integer|min:1000',
            'change_summary' => 'nullable|string|max:255',
        ]);

        $updated = DB::transaction(function () use ($agent, $validated, $request, $tenantId) {
            $newVersion = $agent->version + 1;

            $agent->update(array_merge($validated, [
                'version' => $newVersion,
            ]));

            // Record version history
            WhatsAppAiAgentVersion::create([
                'tenant_id' => $tenantId,
                'agent_id' => $agent->id,
                'version' => $newVersion,
                'system_prompt' => $agent->system_prompt,
                'welcome_message' => $agent->welcome_message,
                'fallback_message' => $agent->fallback_message,
                'allowed_tools' => $agent->allowed_tools,
                'settings_snapshot' => $agent->toArray(),
                'changed_by_user_id' => $request->user()->id,
                'change_summary' => $validated['change_summary'] ?? "Updated agent to version {$newVersion}",
            ]);

            return $agent;
        });

        return response()->json([
            'success' => true,
            'data' => $updated->load('phoneNumber'),
            'message' => 'AI Agent updated successfully',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $agent = WhatsAppAiAgent::where('tenant_id', $tenantId)->findOrFail($id);
        $agent->delete();

        return response()->json([
            'success' => true,
            'message' => 'AI Agent deleted successfully',
        ]);
    }

    /**
     * Interactive Playground Query.
     */
    public function testPlayground(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $agent = WhatsAppAiAgent::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'message' => 'required|string',
            'history' => 'nullable|array',
        ]);

        $query = $request->input('message');
        $history = $request->input('history', []);

        $simulation = $this->agentService->simulatePlayground($agent, $query, $history);

        return response()->json([
            'success' => true,
            'data' => $simulation,
        ]);
    }
}
