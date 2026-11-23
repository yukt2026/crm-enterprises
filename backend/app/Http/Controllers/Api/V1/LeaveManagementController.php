<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Leave;
use App\Domains\HR\Models\LeaveBalance;
use App\Domains\HR\Models\LeaveType;
use App\Domains\HR\Services\LeaveService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveManagementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LeaveService $leaveService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $leaves = Leave::where('tenant_id', $tenantId)
            ->with(['employee:id,employee_id,name,department', 'leaveType:id,name,code', 'approver:id,name'])
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->query('employee_id'), fn($q, $e) => $q->where('employee_id', $e))
            ->when($request->query('year'), function ($q, $year) {
                $q->whereYear('start_date', $year);
            })
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->successResponse($leaves, 'Leave requests retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'leave_type_id' => 'nullable|exists:leave_types,id',
            'type' => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'half_day_session' => 'nullable|in:morning,afternoon',
            'reason' => 'nullable|string|max:1000',
            'attachment_url' => 'nullable|string|max:255',
        ]);

        $leave = $this->leaveService->requestLeave($request->user(), $validated);

        return $this->successResponse($leave, 'Leave application submitted successfully', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $leave = Leave::where('tenant_id', $tenantId)
            ->with(['employee:id,employee_id,name,department', 'leaveType', 'approver:id,name'])
            ->findOrFail($id);

        return $this->successResponse($leave, 'Leave application retrieved successfully');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'manager_notes' => 'nullable|string',
        ]);

        $leave = $this->leaveService->approveLeave($id, $request->user()->id, $validated['manager_notes'] ?? null);

        return $this->successResponse($leave, 'Leave approved successfully');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'manager_notes' => 'nullable|string',
        ]);

        $leave = $this->leaveService->rejectLeave($id, $request->user()->id, $validated['manager_notes'] ?? null);

        return $this->successResponse($leave, 'Leave rejected successfully');
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $leave = $this->leaveService->cancelLeave($id, $request->user()->id);

        return $this->successResponse($leave, 'Leave cancelled successfully');
    }

    public function types(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $types = LeaveType::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return $this->successResponse($types, 'Leave types retrieved successfully');
    }

    public function storeType(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'days_allowed_per_year' => 'nullable|numeric|min:0',
            'is_paid' => 'nullable|boolean',
            'requires_attachment' => 'nullable|boolean',
            'carry_forward_days' => 'nullable|integer|min:0',
        ]);

        $type = LeaveType::create(array_merge($validated, [
            'tenant_id' => $tenantId,
        ]));

        return $this->successResponse($type, 'Leave type created successfully', 201);
    }

    public function balances(Request $request, ?int $employeeId = null): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $year = (int) ($request->query('year') ?? now()->year);

        if (!$employeeId) {
            $employee = Employee::where('tenant_id', $tenantId)
                ->where('user_id', $request->user()->id)
                ->first();
            $employeeId = $employee?->id;
        }

        if (!$employeeId) {
            return $this->successResponse([], 'No employee profile associated');
        }

        $balances = LeaveBalance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->with('leaveType')
            ->get();

        return $this->successResponse($balances, 'Leave balances retrieved successfully');
    }

    public function adjustBalance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'adjustment_days' => 'required|numeric',
            'reason' => 'required|string|max:255',
        ]);

        $balance = $this->leaveService->adjustBalance(
            $validated['employee_id'],
            $validated['leave_type_id'],
            (float) $validated['adjustment_days'],
            $validated['reason'],
            $request->user()->id
        );

        return $this->successResponse($balance, 'Leave balance adjusted successfully');
    }
}
