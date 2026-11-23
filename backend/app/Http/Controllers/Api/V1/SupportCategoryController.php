<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Support\Models\SupportTicketCategory;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportCategoryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $categories = SupportTicketCategory::where('tenant_id', $tenantId)
            ->whereNull('parent_id')
            ->with('subcategories')
            ->withCount('tickets')
            ->get();

        return $this->successResponse($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:support_ticket_categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        $category = SupportTicketCategory::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->successResponse($category, 'Category created successfully.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $category = SupportTicketCategory::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:support_ticket_categories,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $category->update($validated);

        return $this->successResponse($category, 'Category updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $category = SupportTicketCategory::where('tenant_id', $tenantId)->findOrFail($id);
        $category->delete();

        return $this->successResponse(null, 'Category deleted successfully.');
    }
}
