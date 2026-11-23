<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\AttendanceCorrection;
use App\Domains\HR\Services\AttendanceService;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    public function checkIn(Request $request): JsonResponse
    {
        $attendance = $this->attendanceService->checkIn($request->user(), [
            'date' => $request->input('date'),
            'check_in' => $request->input('check_in'),
            'ip_address' => $request->ip(),
            'location' => $request->input('location', 'Office'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy_meters' => $request->input('accuracy_meters'),
            'geofence_latitude' => $request->input('geofence_latitude'),
            'geofence_longitude' => $request->input('geofence_longitude'),
            'geofence_radius_meters' => $request->input('geofence_radius_meters'),
            'is_within_geofence' => $request->input('is_within_geofence'),
            'selfie_url' => $request->input('selfie_url'),
            'verification_method' => $request->input('verification_method'),
            'is_field_checkin' => $request->input('is_field_checkin'),
            'branch_id' => $request->input('branch_id'),
            'notes' => $request->input('notes'),
        ]);

        return $this->successResponse([
            'id' => $attendance->id,
            'checkIn' => $attendance->check_in,
            'status' => $attendance->status,
            'isLate' => $attendance->is_late,
            'lateMinutes' => $attendance->late_minutes,
            'date' => $attendance->date->toDateString(),
            'latitude' => $attendance->latitude,
            'longitude' => $attendance->longitude,
            'isWithinGeofence' => $attendance->is_within_geofence,
            'verificationMethod' => $attendance->verification_method,
            'isFieldCheckin' => $attendance->is_field_checkin,
        ], "Clocked in at {$attendance->check_in}");
    }

    public function checkOut(Request $request): JsonResponse
    {
        $attendance = $this->attendanceService->checkOut($request->user(), [
            'date' => $request->input('date'),
            'check_out' => $request->input('check_out'),
            'ip_address' => $request->ip(),
        ]);

        return $this->successResponse([
            'id' => $attendance->id,
            'checkOut' => $attendance->check_out,
            'totalWorkMinutes' => $attendance->total_work_minutes,
            'isHalfDay' => $attendance->is_half_day,
            'isEarlyDeparture' => $attendance->is_early_departure,
            'earlyMinutes' => $attendance->early_minutes,
            'overtimeMinutes' => $attendance->overtime_minutes,
            'status' => $attendance->status,
            'date' => $attendance->date->toDateString(),
        ], "Clocked out at {$attendance->check_out}");
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $attendances = Attendance::where('tenant_id', $tenantId)
            ->with(['employee:id,employee_id,name,department,designation', 'shift:id,name'])
            ->when($request->query('date'), fn($q, $d) => $q->where('date', $d))
            ->when($request->query('start_date') && $request->query('end_date'), function ($q) use ($request) {
                $q->whereBetween('date', [$request->query('start_date'), $request->query('end_date')]);
            })
            ->when($request->query('employee_id'), fn($q, $e) => $q->where('employee_id', $e))
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->orderBy('date', 'desc')
            ->limit(100)
            ->get();

        return $this->successResponse($attendances, 'Attendance records retrieved successfully');
    }

    public function calendar(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $year = (int) ($request->query('year') ?? now()->year);
        $month = (int) ($request->query('month') ?? now()->month);
        $employeeId = $request->query('employee_id') ? (int) $request->query('employee_id') : null;
        $departmentId = $request->query('department_id') ? (int) $request->query('department_id') : null;

        $data = $this->attendanceService->getMonthlyCalendar($tenantId, $year, $month, $employeeId, $departmentId);

        return $this->successResponse($data, 'Monthly attendance calendar retrieved successfully');
    }

    public function requestCorrection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'requested_check_in' => 'nullable|date_format:H:i',
            'requested_check_out' => 'nullable|date_format:H:i',
            'reason' => 'required|string|max:1000',
        ]);

        $correction = $this->attendanceService->requestCorrection($request->user(), $validated);

        return $this->successResponse($correction, 'Attendance correction requested successfully', 201);
    }

    public function corrections(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $corrections = AttendanceCorrection::where('tenant_id', $tenantId)
            ->with(['employee:id,name,department,employee_id', 'attendance'])
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($corrections, 'Attendance corrections retrieved successfully');
    }

    public function reviewCorrection(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'reviewer_notes' => 'nullable|string',
        ]);

        $correction = $this->attendanceService->reviewCorrection(
            $id,
            $validated['status'],
            $validated['reviewer_notes'] ?? null,
            $request->user()->id
        );

        return $this->successResponse($correction, "Attendance correction {$validated['status']} successfully");
    }
}
