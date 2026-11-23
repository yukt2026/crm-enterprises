<?php

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\Leave;
use App\Domains\HR\Models\LeaveBalance;
use App\Domains\HR\Models\LeaveBalanceAdjustment;
use App\Domains\HR\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /**
     * Initialize leave balances for an employee for a specific year
     */
    public function initializeYearlyBalances(int $employeeId, int $year): void
    {
        $employee = Employee::findOrFail($employeeId);
        $leaveTypes = LeaveType::where('tenant_id', $employee->tenant_id)
            ->where('status', 'active')
            ->get();

        foreach ($leaveTypes as $type) {
            LeaveBalance::firstOrCreate(
                [
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'year' => $year,
                ],
                [
                    'total_entitled' => $type->days_allowed_per_year,
                    'taken_days' => 0,
                    'pending_days' => 0,
                    'balance_days' => $type->days_allowed_per_year,
                ]
            );
        }
    }

    /**
     * Submit leave request
     */
    public function requestLeave($user, array $data): Leave
    {
        $tenantId = $user->tenant_id;
        $employeeId = $data['employee_id'] ?? null;

        if ($employeeId) {
            $employee = Employee::where('tenant_id', $tenantId)->findOrFail($employeeId);
        } else {
            $employee = Employee::where('tenant_id', $tenantId)->where('user_id', $user->id)->firstOrFail();
        }

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        if ($endDate->lessThan($startDate)) {
            throw ValidationException::withMessages(['end_date' => 'End date cannot be earlier than start date.']);
        }

        // Holiday conflict validation
        $holidaysCount = \App\Domains\HR\Models\CompanyHoliday::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('date', '>=', $startDate->toDateString())
            ->whereDate('date', '<=', $endDate->toDateString())
            ->count();

        $isHalfDay = (bool) ($data['is_half_day'] ?? false);
        if (!$isHalfDay && $startDate->equalTo($endDate) && $holidaysCount > 0) {
            throw ValidationException::withMessages([
                'start_date' => 'Selected date is an official company holiday. No leave application required.',
            ]);
        }

        $totalDays = $isHalfDay ? 0.5 : ($startDate->diffInDays($endDate) + 1);

        $leaveTypeId = $data['leave_type_id'] ?? null;
        $leaveType = null;
        if ($leaveTypeId) {
            $leaveType = LeaveType::where('tenant_id', $tenantId)->findOrFail($leaveTypeId);
        }

        $year = $startDate->year;

        // Ensure balance exists
        $balance = null;
        if ($leaveType) {
            $balance = LeaveBalance::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->first();

            if (!$balance) {
                $this->initializeYearlyBalances($employee->id, $year);
                $balance = LeaveBalance::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $year)
                    ->first();
            }

            if ($balance && ($balance->balance_days - $balance->pending_days) < $totalDays) {
                throw ValidationException::withMessages([
                    'balance' => "Insufficient leave balance for {$leaveType->name}. Available: " . ($balance->balance_days - $balance->pending_days) . " days."
                ]);
            }
        }

        return DB::transaction(function () use ($tenantId, $employee, $leaveType, $startDate, $endDate, $isHalfDay, $totalDays, $data, $balance) {
            $leave = Leave::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType?->id,
                'type' => $leaveType?->name ?? ($data['type'] ?? 'Casual'),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'is_half_day' => $isHalfDay,
                'half_day_session' => $isHalfDay ? ($data['half_day_session'] ?? 'morning') : null,
                'total_days' => $totalDays,
                'reason' => $data['reason'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'status' => 'pending',
            ]);

            if ($balance) {
                $balance->increment('pending_days', $totalDays);
            }

            return $leave;
        });
    }

    /**
     * Approve leave request
     */
    public function approveLeave(int $leaveId, int $approverUserId, ?string $managerNotes = null): Leave
    {
        $leave = Leave::findOrFail($leaveId);

        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending leave requests can be approved.']);
        }

        return DB::transaction(function () use ($leave, $approverUserId, $managerNotes) {
            $leave->status = 'approved';
            $leave->approved_by_user_id = $approverUserId;
            $leave->manager_notes = $managerNotes;
            $leave->approved_at = now();
            $leave->save();

            // Update balance
            if ($leave->leave_type_id) {
                $year = $leave->start_date->year;
                $balance = LeaveBalance::where('tenant_id', $leave->tenant_id)
                    ->where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    $balance->pending_days = max(0, $balance->pending_days - $leave->total_days);
                    $balance->taken_days += $leave->total_days;
                    $balance->balance_days = max(0, $balance->total_entitled - $balance->taken_days);
                    $balance->save();
                }
            }

            return $leave;
        });
    }

    /**
     * Reject leave request
     */
    public function rejectLeave(int $leaveId, int $approverUserId, ?string $managerNotes = null): Leave
    {
        $leave = Leave::findOrFail($leaveId);

        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending leave requests can be rejected.']);
        }

        return DB::transaction(function () use ($leave, $approverUserId, $managerNotes) {
            $leave->status = 'rejected';
            $leave->approved_by_user_id = $approverUserId;
            $leave->manager_notes = $managerNotes;
            $leave->save();

            // Release pending days
            if ($leave->leave_type_id) {
                $year = $leave->start_date->year;
                $balance = LeaveBalance::where('tenant_id', $leave->tenant_id)
                    ->where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    $balance->pending_days = max(0, $balance->pending_days - $leave->total_days);
                    $balance->save();
                }
            }

            return $leave;
        });
    }

    /**
     * Cancel or revoke leave request
     */
    public function cancelLeave(int $leaveId, int $userId): Leave
    {
        $leave = Leave::findOrFail($leaveId);

        if ($leave->status === 'cancelled') {
            return $leave;
        }

        return DB::transaction(function () use ($leave) {
            $previousStatus = $leave->status;
            $leave->status = 'cancelled';
            $leave->cancelled_at = now();
            $leave->save();

            if ($leave->leave_type_id) {
                $year = $leave->start_date->year;
                $balance = LeaveBalance::where('tenant_id', $leave->tenant_id)
                    ->where('employee_id', $leave->employee_id)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    if ($previousStatus === 'pending') {
                        $balance->pending_days = max(0, $balance->pending_days - $leave->total_days);
                    } elseif ($previousStatus === 'approved') {
                        $balance->taken_days = max(0, $balance->taken_days - $leave->total_days);
                        $balance->balance_days += $leave->total_days;
                    }
                    $balance->save();
                }
            }

            return $leave;
        });
    }

    /**
     * Manual adjustment of leave balance with audit trail
     */
    public function adjustBalance(int $employeeId, int $leaveTypeId, float $adjustmentDays, string $reason, int $adjustedByUserId): LeaveBalance
    {
        $employee = Employee::findOrFail($employeeId);
        $year = now()->year;

        $balance = LeaveBalance::firstOrCreate(
            [
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveTypeId,
                'year' => $year,
            ],
            [
                'total_entitled' => 0,
                'taken_days' => 0,
                'pending_days' => 0,
                'balance_days' => 0,
            ]
        );

        $previousBalance = $balance->balance_days;
        $newBalance = $previousBalance + $adjustmentDays;

        $balance->balance_days = $newBalance;
        $balance->total_entitled += $adjustmentDays;
        $balance->save();

        LeaveBalanceAdjustment::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveTypeId,
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'adjustment_days' => $adjustmentDays,
            'reason' => $reason,
            'adjusted_by_user_id' => $adjustedByUserId,
        ]);

        return $balance;
    }
}
