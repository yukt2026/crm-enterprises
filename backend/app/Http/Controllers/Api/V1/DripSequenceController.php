<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Marketing\Models\DripEnrollment;
use App\Domains\Marketing\Models\DripSequence;
use App\Domains\Marketing\Models\DripStep;
use App\Domains\Marketing\Services\DripSequenceEngine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DripSequenceController extends Controller
{
    public function __construct(
        protected DripSequenceEngine $dripEngine
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $sequences = DripSequence::where('tenant_id', $tenantId)
            ->withCount(['steps', 'enrollments'])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sequences,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'nullable|string',
            'status' => 'nullable|in:draft,active,paused',
            'exit_on_conversion' => 'nullable|boolean',
            'exit_on_reply' => 'nullable|boolean',
            'exit_on_unsubscribe' => 'nullable|boolean',
            'respect_quiet_hours' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|string',
            'quiet_hours_end' => 'nullable|string',
            'max_messages_per_week' => 'nullable|integer',
            'steps' => 'nullable|array',
        ]);

        $tenantId = $request->user()->current_tenant_id;

        $sequence = DripSequence::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'trigger_event' => $validated['trigger_event'] ?? 'lead.created',
            'status' => $validated['status'] ?? 'draft',
            'exit_on_conversion' => $validated['exit_on_conversion'] ?? true,
            'exit_on_reply' => $validated['exit_on_reply'] ?? true,
            'exit_on_unsubscribe' => $validated['exit_on_unsubscribe'] ?? true,
            'respect_quiet_hours' => $validated['respect_quiet_hours'] ?? true,
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? '21:00',
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? '09:00',
            'max_messages_per_week' => $validated['max_messages_per_week'] ?? 3,
            'created_by_user_id' => $request->user()->id,
        ]);

        // Add steps
        $steps = $validated['steps'] ?? [];
        foreach ($steps as $idx => $step) {
            DripStep::create([
                'tenant_id' => $tenantId,
                'drip_sequence_id' => $sequence->id,
                'step_order' => $step['step_order'] ?? ($idx + 1),
                'channel' => $step['channel'] ?? 'whatsapp',
                'delay_interval' => $step['delay_interval'] ?? 0,
                'delay_unit' => $step['delay_unit'] ?? 'hours',
                'template_id' => $step['template_id'] ?? null,
                'subject' => $step['subject'] ?? null,
                'content' => $step['content'] ?? null,
                'variable_mappings' => $step['variable_mappings'] ?? [],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Drip sequence created successfully.',
            'data' => $sequence->load('steps'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $sequence = DripSequence::where('tenant_id', $tenantId)
            ->with(['steps', 'createdByUser:id,name'])
            ->withCount(['enrollments'])
            ->findOrFail($id);

        $recentEnrollments = DripEnrollment::where('tenant_id', $tenantId)
            ->where('drip_sequence_id', $sequence->id)
            ->with('currentStep')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'sequence' => $sequence,
                'recent_enrollments' => $recentEnrollments,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $sequence = DripSequence::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'trigger_event' => 'nullable|string',
            'status' => 'sometimes|in:draft,active,paused',
            'exit_on_conversion' => 'nullable|boolean',
            'exit_on_reply' => 'nullable|boolean',
            'exit_on_unsubscribe' => 'nullable|boolean',
            'respect_quiet_hours' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|string',
            'quiet_hours_end' => 'nullable|string',
            'max_messages_per_week' => 'nullable|integer',
            'steps' => 'nullable|array',
        ]);

        $sequence->update($validated);

        if (isset($validated['steps']) && is_array($validated['steps'])) {
            DripStep::where('drip_sequence_id', $sequence->id)->delete();
            foreach ($validated['steps'] as $idx => $step) {
                DripStep::create([
                    'tenant_id' => $tenantId,
                    'drip_sequence_id' => $sequence->id,
                    'step_order' => $step['step_order'] ?? ($idx + 1),
                    'channel' => $step['channel'] ?? 'whatsapp',
                    'delay_interval' => $step['delay_interval'] ?? 0,
                    'delay_unit' => $step['delay_unit'] ?? 'hours',
                    'template_id' => $step['template_id'] ?? null,
                    'subject' => $step['subject'] ?? null,
                    'content' => $step['content'] ?? null,
                    'variable_mappings' => $step['variable_mappings'] ?? [],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Drip sequence updated.',
            'data' => $sequence->fresh('steps'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $sequence = DripSequence::where('tenant_id', $tenantId)->findOrFail($id);
        $sequence->delete();

        return response()->json([
            'success' => true,
            'message' => 'Drip sequence deleted.',
        ]);
    }

    public function enroll(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $sequence = DripSequence::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'entity_type' => 'required|in:lead,contact,customer',
            'entity_id' => 'required|integer',
            'context' => 'nullable|array',
        ]);

        $enrollment = $this->dripEngine->enroll(
            $sequence,
            $validated['entity_type'],
            $validated['entity_id'],
            $validated['context'] ?? []
        );

        return response()->json([
            'success' => $enrollment !== null,
            'message' => $enrollment !== null ? 'Entity enrolled into sequence.' : 'Could not enroll entity.',
            'data' => $enrollment,
        ]);
    }

    public function processDue(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $result = $this->dripEngine->processDueEnrollments($tenantId);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
