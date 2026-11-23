<?php

namespace App\Domains\HR\Services;

use App\Domains\HR\Models\AttendanceCorrection;
use App\Domains\HR\Models\Employee;
use App\Domains\HR\Models\EmployeeDocument;
use App\Domains\HR\Models\Leave;
use Carbon\Carbon;

class HrNotificationService
{
    /**
     * Gather active HR reminders and alerts for tenant
     */
    public function getActiveReminders(int $tenantId): array
    {
        $today = now()->toDateString();
        $in30Days = now()->addDays(30)->toDateString();

        // 1. Probation Completions (probation_end_date coming up within 30 days)
        $probations = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('probation_end_date')
            ->whereBetween('probation_end_date', [$today, $in30Days])
            ->get(['id', 'employee_id', 'name', 'department', 'designation', 'probation_end_date'])
            ->map(function ($emp) {
                return [
                    'type' => 'probation_completion',
                    'title' => "Probation Ending: {$emp->name}",
                    'description' => "Probation period concludes on {$emp->probation_end_date->format('d M Y')}.",
                    'due_date' => $emp->probation_end_date->toDateString(),
                    'employee_id' => $emp->id,
                    'severity' => 'info',
                ];
            });

        // 2. Contract Expiries
        $contracts = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$today, $in30Days])
            ->get(['id', 'employee_id', 'name', 'contract_end_date'])
            ->map(function ($emp) {
                return [
                    'type' => 'contract_expiry',
                    'title' => "Contract Expiring: {$emp->name}",
                    'description' => "Employment contract expires on {$emp->contract_end_date->format('d M Y')}.",
                    'due_date' => $emp->contract_end_date->toDateString(),
                    'employee_id' => $emp->id,
                    'severity' => 'warning',
                ];
            });

        // 3. Document Expiries (Passports, Visas, Certifications)
        $documents = EmployeeDocument::where('tenant_id', $tenantId)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $in30Days])
            ->with('employee:id,name,department')
            ->get()
            ->map(function ($doc) {
                return [
                    'type' => 'document_expiry',
                    'title' => "Document Expiring: {$doc->title} ({$doc->employee?->name})",
                    'description' => "{$doc->document_type} requires renewal by {$doc->expiry_date->format('d M Y')}.",
                    'due_date' => $doc->expiry_date->toDateString(),
                    'employee_id' => $doc->employee_id,
                    'document_id' => $doc->id,
                    'severity' => 'warning',
                ];
            });

        // 4. Work Anniversaries
        $allActive = Employee::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('date_of_joining')
            ->get(['id', 'name', 'department', 'date_of_joining']);

        $anniversaries = [];
        foreach ($allActive as $emp) {
            $join = Carbon::parse($emp->date_of_joining);
            if ($join->year < now()->year && $join->month === now()->month) {
                $years = now()->year - $join->year;
                $anniversaries[] = [
                    'type' => 'work_anniversary',
                    'title' => "Work Anniversary: {$emp->name} ({$years} Years)",
                    'description' => "Joined on {$join->format('d M Y')}. Completes {$years} year(s) of service this month.",
                    'due_date' => Carbon::create(now()->year, $join->month, $join->day)->toDateString(),
                    'employee_id' => $emp->id,
                    'severity' => 'celebration',
                ];
            }
        }

        // 5. Pending Approvals Queue
        $pendingLeaves = Leave::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $pendingCorrections = AttendanceCorrection::where('tenant_id', $tenantId)->where('status', 'pending')->count();

        return [
            'summary' => [
                'upcoming_probations' => $probations->count(),
                'upcoming_contract_expiries' => $contracts->count(),
                'upcoming_document_expiries' => $documents->count(),
                'anniversaries_this_month' => count($anniversaries),
                'pending_leave_approvals' => $pendingLeaves,
                'pending_attendance_corrections' => $pendingCorrections,
            ],
            'reminders' => array_merge(
                $probations->toArray(),
                $contracts->toArray(),
                $documents->toArray(),
                $anniversaries
            ),
        ];
    }
}
