<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeeDocument;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * List documents for an employee.
     */
    public function getDocuments(int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);

        $documents = EmployeeDocument::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->with('verifiedByUser:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $documents,
        ]);
    }

    /**
     * Upload / Register an employee document.
     */
    public function storeDocument(Request $request, int $employeeId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);

        if (!$request->has('file_name')) {
            $filename = basename($request->input('file_url') ?: $request->input('file_path') ?: ($request->input('title', 'document') . '.pdf'));
            $request->merge(['file_name' => $filename]);
        }
        if (!$request->has('file_path') && $request->has('file_url')) {
            $request->merge(['file_path' => $request->input('file_url')]);
        }

        $validated = $request->validate([
            'document_type' => 'required|string|in:contract,appointment_letter,id_proof,pan_card,aadhaar,qualification,certificate,policy_acknowledgement,other',
            'title' => 'required|string|max:150',
            'document_number' => 'nullable|string|max:100',
            'file_name' => 'nullable|string|max:255',
            'file_path' => 'nullable|string|max:500',
            'file_size' => 'nullable|integer',
            'mime_type' => 'nullable|string|max:100',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['employee_id'] = $employee->id;
        $validated['file_path'] = $validated['file_path'] ?? ("/uploads/employees/{$employee->id}/" . $validated['file_name']);

        $document = EmployeeDocument::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Document registered successfully.',
            'data' => $document,
        ], 201);
    }

    /**
     * Verify employee document.
     */
    public function verifyDocument(Request $request, int $employeeId, int $docId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $document = EmployeeDocument::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->findOrFail($docId);

        $document->update([
            'is_verified' => true,
            'verified_by_user_id' => $request->user()?->id ?? 1,
            'verified_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document verified.',
            'data' => $document->fresh('verifiedByUser:id,name'),
        ]);
    }

    /**
     * Delete document.
     */
    public function deleteDocument(int $employeeId, int $docId): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $document = EmployeeDocument::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->findOrFail($docId);

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Document removed successfully.',
        ]);
    }
}
