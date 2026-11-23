<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\FieldSalesVisit;
use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\Expense;
use App\Domains\HR\Models\Employee;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FieldSalesService
{
    /**
     * Schedule a customer visit
     */
    public function scheduleVisit(int $tenantId, array $data, ?User $user = null): FieldSalesVisit
    {
        return FieldSalesVisit::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'lead_id' => $data['lead_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'title' => $data['title'],
            'status' => 'scheduled',
            'scheduled_at' => Carbon::parse($data['scheduled_at']),
            'route_waypoints' => $data['route_waypoints'] ?? null,
            'offline_client_uuid' => $data['offline_client_uuid'] ?? null,
            'is_offline_synced' => !empty($data['offline_client_uuid']),
        ]);
    }

    /**
     * Transparent, User-Initiated GPS Check-In
     */
    public function checkIn(FieldSalesVisit $visit, float $lat, float $lng, ?string $address = null): FieldSalesVisit
    {
        if ($visit->status === 'completed') {
            throw new \DomainException("Visit #{$visit->id} has already been completed.");
        }

        $visit->update([
            'status' => 'checked_in',
            'check_in_at' => Carbon::now(),
            'check_in_lat' => $lat,
            'check_in_lng' => $lng,
            'check_in_address' => $address ?? "GPS: {$lat}, {$lng}",
        ]);

        return $visit->fresh(['employee', 'customer', 'lead', 'branch']);
    }

    /**
     * Transparent, User-Initiated GPS Check-Out with Outcome, Notes, Follow-up & Expense Claim
     */
    public function checkOut(FieldSalesVisit $visit, array $data, ?User $user = null): FieldSalesVisit
    {
        $lat = $data['check_out_lat'] ?? $visit->check_in_lat;
        $lng = $data['check_out_lng'] ?? $visit->check_in_lng;
        $address = $data['check_out_address'] ?? ($lat ? "GPS: {$lat}, {$lng}" : null);

        // Calculate distance if both check-in and check-out coordinates exist
        $distanceKm = $data['distance_travelled_km'] ?? 0.0;
        if ($distanceKm <= 0 && $visit->check_in_lat && $lat) {
            $distanceKm = $this->calculateHaversineDistance(
                (float) $visit->check_in_lat,
                (float) $visit->check_in_lng,
                (float) $lat,
                (float) $lng
            );
        }

        $followUpTaskId = null;
        if (!empty($data['follow_up_date'])) {
            $task = Task::create([
                'tenant_id' => $visit->tenant_id,
                'title' => "Field Follow-up: {$visit->title}",
                'description' => "Follow-up required from field visit #{$visit->id}. Notes: " . ($data['visit_notes'] ?? 'None'),
                'status' => 'pending',
                'priority' => 'high',
                'assigned_user_id' => $user?->id ?? $visit->employee?->user_id,
                'due_date' => Carbon::parse($data['follow_up_date'])->endOfDay(),
                'lead_id' => $visit->lead_id,
            ]);
            $followUpTaskId = $task->id;
        }

        // Travel expense claim creation
        $travelExpenseId = null;
        $expenseAmount = (float) ($data['travel_expense_amount'] ?? 0);
        if ($expenseAmount > 0) {
            $expense = Expense::create([
                'tenant_id' => $visit->tenant_id,
                'expense_number' => 'EXP-FS-' . strtoupper(uniqid()),
                'employee_user_id' => $user?->id ?? $visit->employee?->user_id,
                'branch_id' => $visit->branch_id,
                'category' => 'travel',
                'title' => "Field Visit Travel Claim #{$visit->id} - {$visit->title}",
                'amount' => $expenseAmount,
                'expense_date' => Carbon::now()->toDateString(),
                'status' => 'pending_approval',
                'description' => "Automated travel expense claim for {$distanceKm} KM travelled on field visit #{$visit->id}.",
            ]);
            $travelExpenseId = $expense->id;
        }

        $visit->update([
            'status' => 'completed',
            'check_out_at' => Carbon::now(),
            'check_out_lat' => $lat,
            'check_out_lng' => $lng,
            'check_out_address' => $address,
            'distance_travelled_km' => round($distanceKm, 2),
            'visit_notes' => $data['visit_notes'] ?? null,
            'outcome' => $data['outcome'] ?? 'followup_required',
            'follow_up_date' => !empty($data['follow_up_date']) ? Carbon::parse($data['follow_up_date']) : null,
            'follow_up_task_id' => $followUpTaskId,
            'travel_expense_amount' => $expenseAmount,
            'travel_expense_id' => $travelExpenseId,
        ]);

        return $visit->fresh(['employee', 'customer', 'lead', 'branch', 'followUpTask']);
    }

    /**
     * Daily Visit Plan for an employee
     */
    public function getDailyPlan(int $tenantId, int $employeeId, ?string $date = null): array
    {
        $targetDate = $date ? Carbon::parse($date)->toDateString() : Carbon::now()->toDateString();

        $visits = FieldSalesVisit::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('scheduled_at', $targetDate)
            ->with(['customer', 'lead', 'branch'])
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $employee = Employee::find($employeeId);

        return [
            'date' => $targetDate,
            'date_formatted_ist' => IndiaFirstService::formatIstDateTime($targetDate, 'd M Y'),
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->full_name ?? $employee->name,
                'department' => $employee->department,
            ] : null,
            'total_visits_planned' => $visits->count(),
            'completed_count' => $visits->where('status', 'completed')->count(),
            'pending_count' => $visits->whereIn('status', ['scheduled', 'checked_in'])->count(),
            'total_distance_km' => round($visits->sum('distance_travelled_km'), 2),
            'visits' => $visits,
        ];
    }

    /**
     * Field Sales Analytics Breakdown
     */
    public function getFieldSalesAnalytics(int $tenantId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();

        $query = FieldSalesVisit::where('tenant_id', $tenantId)
            ->whereBetween('scheduled_at', [$fromDate, $toDate]);

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        $allVisits = $query->with(['employee', 'branch'])->get();

        $totalVisits = $allVisits->count();
        $completedVisits = $allVisits->where('status', 'completed')->count();
        $totalDistance = round($allVisits->sum('distance_travelled_km'), 2);
        $totalExpenseClaims = (float) $allVisits->sum('travel_expense_amount');

        // Outcome breakdown
        $outcomes = $allVisits->where('status', 'completed')->groupBy('outcome')->map->count();

        // Employee performance
        $byEmployee = $allVisits->groupBy('employee_id')->map(function ($items, $empId) {
            $emp = $items->first()->employee;
            return [
                'employee_id' => $empId,
                'name' => $emp?->full_name ?? $emp?->name ?? "Employee #{$empId}",
                'total_visits' => $items->count(),
                'completed_visits' => $items->where('status', 'completed')->count(),
                'total_distance_km' => round($items->sum('distance_travelled_km'), 2),
                'deals_closed_count' => $items->where('outcome', 'deal_closed')->count(),
                'expense_claims' => (float) $items->sum('travel_expense_amount'),
            ];
        })->values();

        // Branch performance
        $byBranch = $allVisits->groupBy('branch_id')->map(function ($items, $brId) {
            $branch = $items->first()->branch;
            return [
                'branch_id' => $brId,
                'name' => $branch?->name ?? 'Main Office',
                'total_visits' => $items->count(),
                'completed_visits' => $items->where('status', 'completed')->count(),
                'total_distance_km' => round($items->sum('distance_travelled_km'), 2),
                'deals_closed_count' => $items->where('outcome', 'deal_closed')->count(),
            ];
        })->values();

        return [
            'period' => [
                'from_date' => $fromDate->toDateString(),
                'to_date' => $toDate->toDateString(),
            ],
            'kpis' => [
                'total_visits' => $totalVisits,
                'completed_visits' => $completedVisits,
                'completion_rate' => $totalVisits > 0 ? round(($completedVisits / $totalVisits) * 100, 1) : 0,
                'total_distance_km' => $totalDistance,
                'total_expense_claims' => $totalExpenseClaims,
                'total_expense_claims_inr' => IndiaFirstService::formatInr($totalExpenseClaims),
            ],
            'outcomes' => $outcomes,
            'by_employee' => $byEmployee,
            'by_branch' => $byBranch,
        ];
    }

    /**
     * Safely Sync Offline Drafts with UUID Deduplication
     */
    public function syncOfflineDrafts(int $tenantId, array $drafts, ?User $user = null): array
    {
        $synced = [];
        $duplicates = 0;

        foreach ($drafts as $draft) {
            $uuid = $draft['offline_client_uuid'] ?? null;
            if (!$uuid) continue;

            $existing = FieldSalesVisit::where('tenant_id', $tenantId)
                ->where('offline_client_uuid', $uuid)
                ->first();

            if ($existing) {
                $duplicates++;
                continue;
            }

            $visit = $this->scheduleVisit($tenantId, $draft, $user);
            if (!empty($draft['check_in_lat'])) {
                $this->checkIn($visit, (float)$draft['check_in_lat'], (float)$draft['check_in_lng'], $draft['check_in_address'] ?? null);
            }
            if (($draft['status'] ?? null) === 'completed') {
                $this->checkOut($visit, $draft, $user);
            }

            $synced[] = $visit->id;
        }

        return [
            'synced_count' => count($synced),
            'duplicates_ignored' => $duplicates,
            'synced_visit_ids' => $synced,
            'disclaimer' => 'Offline drafts synchronized safely. Unsynchronized drafts are never treated as financial transactions.',
        ];
    }

    /**
     * Calculate Distance between coordinates using the Haversine formula
     */
    protected function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
