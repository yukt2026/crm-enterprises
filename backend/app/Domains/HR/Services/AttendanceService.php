<?php

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Attendance;
use App\Domains\HR\Models\AttendanceCorrection;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Shift;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Compute distance in meters between two GPS coordinates using Haversine formula
     */
    public function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }

    /**
     * Record daily check-in for an employee (or linked authenticated user)
     */
    public function checkIn($user, array $params = []): Attendance
    {
        $tenantId = $user->tenant_id;
        $employee = Employee::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        if (!$employee) {
            $employee = Employee::firstOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $user->id],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'department' => $user->department ?? 'Operations',
                    'designation' => 'Team Member',
                    'status' => 'active',
                ]
            );
        }

        $today = $params['date'] ?? now()->toDateString();
        $timeStr = $params['check_in'] ?? now()->format('H:i:s');
        $ipAddress = $params['ip_address'] ?? null;
        $location = $params['location'] ?? 'Office';
        $notes = $params['notes'] ?? null;
        $lat = isset($params['latitude']) ? (float) $params['latitude'] : null;
        $lon = isset($params['longitude']) ? (float) $params['longitude'] : null;
        $accuracy = isset($params['accuracy_meters']) ? (float) $params['accuracy_meters'] : null;
        $selfieUrl = $params['selfie_url'] ?? null;
        $verificationMethod = $params['verification_method'] ?? ($lat !== null ? 'mobile_gps' : 'web');
        $isField = (bool) ($params['is_field_checkin'] ?? false);
        $branchId = $params['branch_id'] ?? $employee->branch_id;

        // Geofencing verification if reference coordinates provided
        $isWithinGeofence = null;
        if ($lat !== null && $lon !== null && isset($params['geofence_latitude']) && isset($params['geofence_longitude'])) {
            $distance = $this->calculateDistanceMeters(
                $lat,
                $lon,
                (float) $params['geofence_latitude'],
                (float) $params['geofence_longitude']
            );
            $radius = (float) ($params['geofence_radius_meters'] ?? 250);
            $isWithinGeofence = ($distance <= $radius);
        } elseif (isset($params['is_within_geofence'])) {
            $isWithinGeofence = (bool) $params['is_within_geofence'];
        }

        // Check if attendance already exists for today
        $attendance = Attendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($attendance && $attendance->check_in) {
            return $attendance;
        }

        // Determine shift
        $shift = $employee->shift ?? Shift::where('tenant_id', $tenantId)->where('is_default', true)->first();

        $isLate = false;
        $lateMinutes = 0;
        $status = 'present';

        if ($shift) {
            $shiftStartTime = Carbon::parse("{$today} {$shift->start_time}");
            $actualCheckInTime = Carbon::parse("{$today} {$timeStr}");
            $graceThreshold = $shiftStartTime->copy()->addMinutes($shift->grace_period_minutes);

            if ($actualCheckInTime->greaterThan($graceThreshold)) {
                $isLate = true;
                $lateMinutes = (int) abs($actualCheckInTime->diffInMinutes($shiftStartTime));
                $status = 'late';
            }
        }

        $dataToSave = [
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'date' => $today,
            'check_in' => $timeStr,
            'status' => $status,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
            'ip_address' => $ipAddress,
            'location' => $location,
            'latitude' => $lat,
            'longitude' => $lon,
            'accuracy_meters' => $accuracy,
            'is_within_geofence' => $isWithinGeofence,
            'selfie_url' => $selfieUrl,
            'verification_method' => $verificationMethod,
            'is_field_checkin' => $isField,
            'branch_id' => $branchId,
            'notes' => $notes,
        ];

        if (!$attendance) {
            $attendance = Attendance::create($dataToSave);
        } else {
            $attendance->update($dataToSave);
        }

        return $attendance;
    }

    /**
     * Record daily check-out
     */
    public function checkOut($user, array $params = []): Attendance
    {
        $tenantId = $user->tenant_id;
        $employee = Employee::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        if (!$employee) {
            throw ValidationException::withMessages(['employee' => 'No employee profile associated with user account.']);
        }

        $today = $params['date'] ?? now()->toDateString();
        $timeStr = $params['check_out'] ?? now()->format('H:i:s');

        $attendance = Attendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance) {
            // Checked out without checking in: create attendance with check_out
            $attendance = Attendance::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'date' => $today,
                'check_out' => $timeStr,
                'status' => 'present',
                'ip_address' => $params['ip_address'] ?? null,
            ]);
        } else {
            $attendance->check_out = $timeStr;

            if ($attendance->check_in) {
                $checkIn = Carbon::parse("{$today} {$attendance->check_in}");
                $checkOut = Carbon::parse("{$today} {$timeStr}");
                $durationMinutes = (int) abs($checkOut->diffInMinutes($checkIn));
                $attendance->total_work_minutes = $durationMinutes;

                // Shift rules
                $shift = $attendance->shift ?? $employee->shift ?? Shift::where('tenant_id', $tenantId)->where('is_default', true)->first();
                if ($shift) {
                    $shiftEndTime = Carbon::parse("{$today} {$shift->end_time}");
                    
                    // Early departure check
                    if ($checkOut->lessThan($shiftEndTime)) {
                        $diffMinutes = (int) abs($shiftEndTime->diffInMinutes($checkOut));
                        if ($diffMinutes > ($shift->grace_period_minutes ?? 15)) {
                            $attendance->is_early_departure = true;
                            $attendance->early_minutes = $diffMinutes;
                        }
                    }

                    // Overtime check
                    $standardWorkMinutes = $shift->full_day_minutes ?: 480;
                    if ($durationMinutes > $standardWorkMinutes) {
                        $attendance->overtime_minutes = $durationMinutes - $standardWorkMinutes;
                    }

                    // Half-day check
                    if ($durationMinutes < $standardWorkMinutes) {
                        if ($durationMinutes >= $shift->half_day_minutes) {
                            $attendance->is_half_day = true;
                            $attendance->status = 'half_day';
                        }
                    }
                }
            }

            $attendance->save();
        }

        return $attendance;
    }

    /**
     * Submit manual attendance correction request
     */
    public function requestCorrection($user, array $data): AttendanceCorrection
    {
        $tenantId = $user->tenant_id;
        $employee = Employee::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $date = $data['date'];
        $existingAttendance = Attendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        return AttendanceCorrection::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'attendance_id' => $existingAttendance?->id,
            'date' => $date,
            'requested_check_in' => $data['requested_check_in'] ?? null,
            'requested_check_out' => $data['requested_check_out'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);
    }

    /**
     * Review (Approve/Reject) correction request
     */
    public function reviewCorrection(int $correctionId, string $status, ?string $reviewerNotes, int $reviewerUserId): AttendanceCorrection
    {
        $correction = AttendanceCorrection::with('employee')->findOrFail($correctionId);

        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Correction request is already finalized.']);
        }

        $correction->status = $status;
        $correction->reviewer_notes = $reviewerNotes;
        $correction->reviewed_by_user_id = $reviewerUserId;
        $correction->reviewed_at = now();
        $correction->save();

        if ($status === 'approved') {
            $attendance = $correction->attendance;
            $employee = $correction->employee;
            $date = Carbon::parse($correction->date)->toDateString();

            if (!$attendance) {
                $attendance = Attendance::where('tenant_id', $correction->tenant_id)
                    ->where('employee_id', $correction->employee_id)
                    ->whereDate('date', $date)
                    ->first();

                if (!$attendance) {
                    $attendance = Attendance::create([
                        'tenant_id' => $correction->tenant_id,
                        'employee_id' => $correction->employee_id,
                        'date' => $date,
                    ]);
                }
            }

            if ($correction->requested_check_in) {
                $attendance->check_in = $correction->requested_check_in;
            }
            if ($correction->requested_check_out) {
                $attendance->check_out = $correction->requested_check_out;
            }

            // Calculate hours if both present
            if ($attendance->check_in && $attendance->check_out) {
                $ci = Carbon::parse("{$date} {$attendance->check_in}");
                $co = Carbon::parse("{$date} {$attendance->check_out}");
                $attendance->total_work_minutes = max(0, (int) $co->diffInMinutes($ci));
            }

            $attendance->status = 'present';
            $attendance->is_locked = true;
            $attendance->approved_by_user_id = $reviewerUserId;
            $attendance->save();

            $correction->attendance_id = $attendance->id;
            $correction->save();
        }

        return $correction;
    }

    /**
     * Monthly attendance calendar matrix
     */
    public function getMonthlyCalendar(int $tenantId, int $year, int $month, ?int $employeeId = null, ?int $departmentId = null): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $employeesQuery = Employee::where('tenant_id', $tenantId)->where('status', 'active');
        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }
        if ($departmentId) {
            $employeesQuery->where('department_id', $departmentId);
        }
        $employees = $employeesQuery->get(['id', 'employee_id', 'name', 'department', 'designation']);

        $attendances = Attendance::where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
            ->get();

        return [
            'year' => $year,
            'month' => $month,
            'days_in_month' => $startDate->daysInMonth,
            'employees' => $employees,
            'records' => $attendances,
        ];
    }
}
