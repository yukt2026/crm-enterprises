<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Marketing\Models\MarketingSegment;
use App\Domains\Marketing\Services\SegmentQueryService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingSegmentController extends Controller
{
    public function __construct(
        protected SegmentQueryService $segmentQueryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $segments = MarketingSegment::where('tenant_id', $tenantId)
            ->withCount('campaigns')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $segments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:dynamic,static',
            'target_entity' => 'nullable|in:lead,contact,customer',
            'rules' => 'nullable|array',
            'inclusion_ids' => 'nullable|array',
            'exclusion_ids' => 'nullable|array',
        ]);

        $tenantId = $request->user()->current_tenant_id;
        $targetEntity = $validated['target_entity'] ?? 'lead';
        $rules = $validated['rules'] ?? [];
        $inclusionIds = $validated['inclusion_ids'] ?? [];
        $exclusionIds = $validated['exclusion_ids'] ?? [];

        $cachedCount = $this->segmentQueryService->previewCount(
            $tenantId,
            $rules,
            $inclusionIds,
            $exclusionIds,
            $targetEntity
        );

        $segment = MarketingSegment::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'] ?? 'dynamic',
            'target_entity' => $targetEntity,
            'rules' => $rules,
            'inclusion_ids' => $inclusionIds,
            'exclusion_ids' => $exclusionIds,
            'cached_count' => $cachedCount,
            'last_calculated_at' => now(),
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Marketing segment created successfully.',
            'data' => $segment,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $segment = MarketingSegment::where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $segment,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $segment = MarketingSegment::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:dynamic,static',
            'target_entity' => 'sometimes|in:lead,contact,customer',
            'rules' => 'nullable|array',
            'inclusion_ids' => 'nullable|array',
            'exclusion_ids' => 'nullable|array',
        ]);

        $rules = $validated['rules'] ?? $segment->rules ?? [];
        $inclusionIds = $validated['inclusion_ids'] ?? $segment->inclusion_ids ?? [];
        $exclusionIds = $validated['exclusion_ids'] ?? $segment->exclusion_ids ?? [];
        $targetEntity = $validated['target_entity'] ?? $segment->target_entity ?? 'lead';

        $cachedCount = $this->segmentQueryService->previewCount(
            $tenantId,
            $rules,
            $inclusionIds,
            $exclusionIds,
            $targetEntity
        );

        $segment->update(array_merge($validated, [
            'cached_count' => $cachedCount,
            'last_calculated_at' => now(),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Marketing segment updated.',
            'data' => $segment->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $segment = MarketingSegment::where('tenant_id', $tenantId)->findOrFail($id);
        $segment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Marketing segment deleted.',
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $rules = (array) $request->input('rules', []);
        $inclusionIds = (array) $request->input('inclusion_ids', []);
        $exclusionIds = (array) $request->input('exclusion_ids', []);
        $targetEntity = $request->input('target_entity', 'lead');

        $count = $this->segmentQueryService->previewCount(
            $tenantId,
            $rules,
            $inclusionIds,
            $exclusionIds,
            $targetEntity
        );

        return response()->json([
            'success' => true,
            'data' => [
                'matching_count' => $count,
                'target_entity' => $targetEntity,
            ],
        ]);
    }

    public function members(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $segment = MarketingSegment::where('tenant_id', $tenantId)->findOrFail($id);

        $channel = $request->input('channel');
        $recipients = $this->segmentQueryService->resolveRecipients($segment, $channel);

        return response()->json([
            'success' => true,
            'data' => [
                'segment_id' => $segment->id,
                'name' => $segment->name,
                'total_members' => $recipients->count(),
                'sample_members' => $recipients->take(50)->values(),
            ],
        ]);
    }
}
