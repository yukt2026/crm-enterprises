<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\Department;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $departments = Department::where('tenant_id', $tenantId)
            ->with(['head:id,name,designation,email', 'parentDepartment:id,name'])
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return $this->successResponse($departments, 'Departments retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'department_head_id' => 'nullable|exists:employees,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'status' => 'nullable|in:active,archived',
        ]);

        $department = Department::create(array_merge($validated, [
            'tenant_id' => $tenantId,
        ]));

        return $this->successResponse($department, 'Department created successfully', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $department = Department::where('tenant_id', $tenantId)
            ->with([
                'head:id,name,designation,email',
                'parentDepartment:id,name',
                'subDepartments:id,name,parent_department_id',
                'employees:id,employee_id,name,designation,email,status',
                'designations',
            ])
            ->findOrFail($id);

        return $this->successResponse($department, 'Department retrieved successfully');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $department = Department::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'department_head_id' => 'nullable|exists:employees,id',
            'parent_department_id' => 'nullable|exists:departments,id',
            'status' => 'nullable|in:active,archived',
        ]);

        $department->update($validated);

        return $this->successResponse($department, 'Department updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $department = Department::where('tenant_id', $tenantId)->findOrFail($id);
        $department->delete();

        return $this->successResponse(null, 'Department deleted successfully');
    }
}
