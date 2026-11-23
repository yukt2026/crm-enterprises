<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\CompanyHoliday;
use App\Domains\HR\Models\Shift;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $shifts = Shift::where('tenant_id', $tenantId)
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return $this->successResponse($shifts, 'Shifts retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0',
            'half_day_minutes' => 'nullable|integer|min:0',
            'full_day_minutes' => 'nullable|integer|min:0',
            'break_duration_minutes' => 'nullable|integer|min:0',
            'working_days' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (!empty($validated['is_default'])) {
            Shift::where('tenant_id', $tenantId)->update(['is_default' => false]);
        }

        $shift = Shift::create(array_merge($validated, [
            'tenant_id' => $tenantId,
        ]));

        return $this->successResponse($shift, 'Shift created successfully', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $shift = Shift::where('tenant_id', $tenantId)
            ->with(['employees:id,employee_id,name,department,designation'])
            ->findOrFail($id);

        return $this->successResponse($shift, 'Shift retrieved successfully');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $shift = Shift::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => 'nullable|string|max:50',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0',
            'half_day_minutes' => 'nullable|integer|min:0',
            'full_day_minutes' => 'nullable|integer|min:0',
            'break_duration_minutes' => 'nullable|integer|min:0',
            'working_days' => 'nullable|array',
            'is_default' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
        ]);

        if (!empty($validated['is_default'])) {
            Shift::where('tenant_id', $tenantId)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $shift->update($validated);

        return $this->successResponse($shift, 'Shift updated successfully');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $shift = Shift::where('tenant_id', $tenantId)->findOrFail($id);
        $shift->delete();

        return $this->successResponse(null, 'Shift deleted successfully');
    }

    public function holidays(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $year = $request->query('year');

        $holidays = CompanyHoliday::where('tenant_id', $tenantId)
            ->when($year, function ($q, $y) {
                $q->whereYear('date', $y);
            })
            ->orderBy('date', 'asc')
            ->get();

        return $this->successResponse($holidays, 'Holidays retrieved successfully');
    }

    public function storeHoliday(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'is_recurring' => 'nullable|boolean',
        ]);

        $holiday = CompanyHoliday::create(array_merge($validated, [
            'tenant_id' => $tenantId,
        ]));

        return $this->successResponse($holiday, 'Company holiday created successfully', 201);
    }

    public function destroyHoliday(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $holiday = CompanyHoliday::where('tenant_id', $tenantId)->findOrFail($id);
        $holiday->delete();

        return $this->successResponse(null, 'Holiday deleted successfully');
    }
}
