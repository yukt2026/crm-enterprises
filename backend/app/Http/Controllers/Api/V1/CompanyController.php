<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Company::query();

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('industry', 'like', $search)
                  ->orWhere('website', 'like', $search);
            });
        }

        $perPage = (int) $request->get('limit', $request->get('per_page', 10));
        return $this->paginatedResponse($query->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:50',
            'annual_revenue' => 'nullable|numeric|min:0',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
        ]);

        $company = Company::create($validated);
        return $this->successResponse($company, 'Company created successfully.', 201);
    }

    public function show(Company $company): JsonResponse
    {
        return $this->successResponse($company->load(['contacts', 'customers', 'deals']));
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:50',
            'annual_revenue' => 'nullable|numeric|min:0',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
        ]);

        $company->update($validated);
        return $this->successResponse($company, 'Company updated successfully.');
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();
        return $this->successResponse(null, 'Company deleted successfully.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);
        Company::whereIn('id', $request->ids)->delete();
        return $this->successResponse(null, 'Selected companies deleted successfully.');
    }
}
