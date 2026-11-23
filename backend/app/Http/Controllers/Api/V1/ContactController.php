<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\CRM\Models\Contact;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Contact::query()->with('company');

        if ($request->filled('q')) {
            $search = '%' . $request->q . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('phone', 'like', $search);
            });
        }

        $perPage = (int) $request->get('limit', $request->get('per_page', 10));
        return $this->paginatedResponse($query->latest()->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:100',
            'is_primary' => 'boolean',
        ]);

        $contact = Contact::create($validated);
        return $this->successResponse($contact, 'Contact created successfully.', 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        return $this->successResponse($contact->load('company'));
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'job_title' => 'nullable|string|max:100',
            'is_primary' => 'boolean',
        ]);

        $contact->update($validated);
        return $this->successResponse($contact, 'Contact updated successfully.');
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();
        return $this->successResponse(null, 'Contact deleted successfully.');
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);
        Contact::whereIn('id', $request->ids)->delete();
        return $this->successResponse(null, 'Selected contacts deleted successfully.');
    }
}
