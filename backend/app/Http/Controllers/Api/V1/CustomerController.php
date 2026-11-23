<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\Customer360Service;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected Customer360Service $customer360Service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getTenantId() ?? 1;
        $query = Customer::where('tenant_id', $tenantId)->with(['company', 'contact']);

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search);
            });
        }

        $perPage = (int) $request->get('limit', $request->get('per_page', 15));
        return $this->paginatedResponse($query->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getTenantId() ?? 1;
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive',
            'tier' => 'nullable|string',
        ]);
        $validated['tenant_id'] = $tenantId;

        $customer = Customer::create($validated);
        return $this->successResponse($customer, 'Customer created successfully.', 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return $this->successResponse($customer->load(['company', 'contact']));
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'status' => 'sometimes|in:active,inactive',
            'tier' => 'nullable|string',
        ]);

        $customer->update($validated);
        return $this->successResponse($customer, 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();
        return $this->successResponse(null, 'Customer deleted successfully.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);
        Customer::whereIn('id', $request->ids)->delete();
        return $this->successResponse(null, 'Selected customers deleted successfully.');
    }

    /**
     * Customer 360-Degree Unified Profile View
     */
    public function profile360(int $id): JsonResponse
    {
        $profile = $this->customer360Service->getProfile($id);
        return $this->successResponse($profile, 'Customer 360-degree profile retrieved.');
    }
}
