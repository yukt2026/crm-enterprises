<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CustomerPortal\Models\BookableService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookableServiceController extends Controller
{
    protected function getTenantId(): int
    {
        return TenantContext::getTenantId() ?? 1;
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $query = BookableService::where('tenant_id', $tenantId)->with('branch:id,name');

        if ($request->has('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $services = $query->orderBy('name')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'branch_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'requires_advance_payment' => 'nullable|boolean',
            'advance_amount' => 'nullable|numeric|min:0',
            'assigned_staff_ids' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['code'] = $validated['code'] ?? strtoupper(Str::slug(substr($validated['name'], 0, 8)));
        $validated['is_active'] = $validated['is_active'] ?? true;

        $service = BookableService::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bookable service created successfully.',
            'data' => $service,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $service = BookableService::where('tenant_id', $tenantId)->with('branch:id,name')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $service = BookableService::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'branch_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'duration_minutes' => 'sometimes|integer|min:5|max:480',
            'price' => 'sometimes|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'requires_advance_payment' => 'nullable|boolean',
            'advance_amount' => 'nullable|numeric|min:0',
            'assigned_staff_ids' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Bookable service updated successfully.',
            'data' => $service->fresh(['branch:id,name']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $service = BookableService::where('tenant_id', $tenantId)->findOrFail($id);
        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bookable service deleted successfully.',
        ]);
    }
}
