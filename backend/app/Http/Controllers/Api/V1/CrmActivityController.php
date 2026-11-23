<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmActivityController extends Controller
{
    /**
     * List chronological activities with filters
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getTenantId() ?? 1;

        $query = CrmActivity::where('tenant_id', $tenantId)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }
        if ($request->filled('contact_id')) {
            $query->where('contact_id', $request->contact_id);
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('deal_id')) {
            $query->where('deal_id', $request->deal_id);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('activity_type')) {
            $query->where('activity_type', $request->activity_type);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $activities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Activities retrieved.',
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    /**
     * Log a new activity on timeline (note, call, meeting, etc.)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'activity_type' => 'required|string|in:note,call,email,sms,whatsapp,meeting,task,status_change,assignment,conversion,audit',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'metadata' => 'nullable|array',
            'lead_id' => 'nullable|exists:leads,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'company_id' => 'nullable|exists:companies,id',
            'deal_id' => 'nullable|exists:deals,id',
            'customer_id' => 'nullable|exists:customers,id',
            'scheduled_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ]);

        $tenantId = TenantContext::getTenantId() ?? 1;
        $validated['tenant_id'] = $tenantId;
        $validated['user_id'] = Auth::id();

        $activity = CrmActivity::create($validated);

        return response()->json([
            'success' => true,
            'status_code' => 201,
            'message' => 'Activity logged successfully.',
            'data' => $activity->load('user:id,name,email'),
        ], 201);
    }

    /**
     * Delete an activity log entry
     */
    public function destroy(int $id): JsonResponse
    {
        $tenantId = TenantContext::getTenantId() ?? 1;
        $activity = CrmActivity::where('tenant_id', $tenantId)->findOrFail($id);
        $activity->delete();

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Activity deleted successfully.',
            'data' => null,
        ]);
    }
}
