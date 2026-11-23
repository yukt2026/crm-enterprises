<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\BranchSalesTarget;
use App\Domains\CRM\Models\BranchTransfer;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\HR\Models\Employee;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Projects\Models\Project;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MultiBranchService
{
    /**
     * Create a new branch.
     */
    public function createBranch(array $data, int $tenantId): Branch
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $code = $data['code'] ?? null;
            if (!$code) {
                $code = $this->generateBranchCode($data['city'] ?? 'BR', $tenantId);
            }

            $branch = Branch::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => strtoupper($code),
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? 'IN',
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'is_active' => $data['is_active'] ?? true,
                'manager_user_id' => $data['manager_user_id'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'business_hours' => $data['business_hours'] ?? $this->defaultBusinessHours(),
                'working_days' => $data['working_days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                'connected_channels' => $data['connected_channels'] ?? [],
                'settings' => $data['settings'] ?? $this->defaultBranchSettings(),
            ]);

            if (!empty($data['department_ids']) && is_array($data['department_ids'])) {
                $branch->departments()->sync($data['department_ids']);
            }

            if (!empty($data['manager_user_id'])) {
                User::where('id', $data['manager_user_id'])
                    ->where('tenant_id', $tenantId)
                    ->update(['branch_id' => $branch->id]);
            }

            return $branch->load(['manager', 'departments']);
        });
    }

    /**
     * Update existing branch details.
     */
    public function updateBranch(Branch $branch, array $data): Branch
    {
        return DB::transaction(function () use ($branch, $data) {
            $branch->fill(array_filter([
                'name' => $data['name'] ?? null,
                'code' => isset($data['code']) ? strtoupper($data['code']) : null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? null,
                'timezone' => $data['timezone'] ?? null,
                'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : null,
                'manager_user_id' => $data['manager_user_id'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'pincode' => $data['pincode'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'business_hours' => $data['business_hours'] ?? null,
                'working_days' => $data['working_days'] ?? null,
                'connected_channels' => $data['connected_channels'] ?? null,
                'settings' => $data['settings'] ?? null,
            ], fn($val) => !is_null($val)));

            $branch->save();

            if (isset($data['department_ids']) && is_array($data['department_ids'])) {
                $branch->departments()->sync($data['department_ids']);
            }

            if (!empty($data['manager_user_id'])) {
                User::where('id', $data['manager_user_id'])
                    ->where('tenant_id', $branch->tenant_id)
                    ->update(['branch_id' => $branch->id]);
            }

            return $branch->fresh(['manager', 'departments']);
        });
    }

    /**
     * Activate or deactivate a branch.
     */
    public function setBranchStatus(Branch $branch, bool $isActive): Branch
    {
        $branch->update(['is_active' => $isActive]);
        return $branch;
    }

    /**
     * Bulk or individual employee assignment to branch.
     */
    public function assignEmployeesToBranch(Branch $branch, array $userIds, ?int $departmentId = null): array
    {
        return DB::transaction(function () use ($branch, $userIds, $departmentId) {
            $updatedUsers = User::where('tenant_id', $branch->tenant_id)
                ->whereIn('id', $userIds)
                ->update(array_filter([
                    'branch_id' => $branch->id,
                    'department_id' => $departmentId,
                ])) ;

            Employee::where('tenant_id', $branch->tenant_id)
                ->whereIn('user_id', $userIds)
                ->update(array_filter([
                    'branch_id' => $branch->id,
                    'department_id' => $departmentId,
                ]));

            return [
                'branch_id' => $branch->id,
                'assigned_count' => $updatedUsers,
                'user_ids' => $userIds,
            ];
        });
    }

    /**
     * Transfer a record between branches with full audit logging.
     * Supported types: lead, employee, project, ticket, meeting, inventory
     */
    public function transferRecord(array $params, User $initiator): BranchTransfer
    {
        return DB::transaction(function () use ($params, $initiator) {
            $tenantId = $initiator->tenant_id ?? $initiator->current_tenant_id;
            $type = strtolower($params['transferable_type']);
            $id = $params['transferable_id'];
            $toBranchId = $params['to_branch_id'];
            $reason = $params['reason'] ?? 'Standard branch transfer';
            $metadata = $params['metadata'] ?? [];

            $toBranch = Branch::where('tenant_id', $tenantId)->findOrFail($toBranchId);

            $fromBranchId = null;
            $record = null;

            switch ($type) {
                case 'lead':
                    $record = Lead::where('tenant_id', $tenantId)->findOrFail($id);
                    $fromBranchId = $record->branch_id;
                    $record->branch_id = $toBranch->id;
                    if (!empty($metadata['reassign_user_id'])) {
                        $record->assigned_user_id = $metadata['reassign_user_id'];
                    }
                    $record->save();
                    break;

                case 'employee':
                    $record = Employee::where('tenant_id', $tenantId)->findOrFail($id);
                    $fromBranchId = $record->branch_id;
                    $record->branch_id = $toBranch->id;
                    $record->save();
                    if ($record->user_id) {
                        User::where('tenant_id', $tenantId)
                            ->where('id', $record->user_id)
                            ->update(['branch_id' => $toBranch->id]);
                    }
                    break;

                case 'project':
                    $record = Project::where('tenant_id', $tenantId)->findOrFail($id);
                    $fromBranchId = $record->branch_id;
                    $record->branch_id = $toBranch->id;
                    if (!empty($metadata['reassign_manager_id'])) {
                        $record->manager_user_id = $metadata['reassign_manager_id'];
                    }
                    $record->save();
                    break;

                case 'ticket':
                    $record = SupportTicket::where('tenant_id', $tenantId)->findOrFail($id);
                    $fromBranchId = $record->branch_id;
                    $record->branch_id = $toBranch->id;
                    if (!empty($metadata['reassign_user_id'])) {
                        $record->assigned_user_id = $metadata['reassign_user_id'];
                    }
                    $record->save();
                    break;

                case 'meeting':
                case 'appointment':
                    $record = Meeting::where('tenant_id', $tenantId)->findOrFail($id);
                    $fromBranchId = $record->branch_id;
                    $record->branch_id = $toBranch->id;
                    if (!empty($metadata['reassign_organizer_id'])) {
                        $record->organizer_id = $metadata['reassign_organizer_id'];
                    }
                    $record->save();
                    break;

                case 'customer':
                    $record = Customer::where('tenant_id', $tenantId)->findOrFail($id);
                    $fromBranchId = $record->branch_id;
                    $record->branch_id = $toBranch->id;
                    $record->save();
                    break;

                default:
                    throw ValidationException::withMessages([
                        'transferable_type' => "Unsupported transferable type [{$type}].",
                    ]);
            }

            return BranchTransfer::create([
                'tenant_id' => $tenantId,
                'transferable_type' => $type,
                'transferable_id' => $id,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranch->id,
                'initiated_by_user_id' => $initiator->id,
                'approved_by_user_id' => $params['approved_by_user_id'] ?? $initiator->id,
                'status' => 'completed',
                'reason' => $reason,
                'approval_notes' => $params['approval_notes'] ?? 'Auto-approved by initiator with transfer privileges',
                'metadata' => $metadata,
                'transferred_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Compute comprehensive Branch Manager Dashboard metrics.
     */
    public function getBranchDashboardMetrics(Branch $branch): array
    {
        $tenantId = $branch->tenant_id;
        $branchId = $branch->id;

        // Leads Metrics
        $totalLeads = Lead::where('tenant_id', $tenantId)->where('branch_id', $branchId)->count();
        $convertedLeads = Lead::where('tenant_id', $tenantId)->where('branch_id', $branchId)->whereNotNull('converted_at')->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 2) : 0.0;

        // Customers Metrics
        $newCustomers = Customer::where('tenant_id', $tenantId)->where('branch_id', $branchId)->count();

        // Deals & Pipeline Metrics
        $dealsCount = Deal::where('tenant_id', $tenantId)->where('branch_id', $branchId)->count();
        $dealsWonCount = Deal::where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('stage', 'won')->count();
        $totalPipelineValue = (float)Deal::where('tenant_id', $tenantId)->where('branch_id', $branchId)->sum('value');
        $wonPipelineValue = (float)Deal::where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('stage', 'won')->sum('value');

        // Financials: Invoices, Collections, Pending & Expenses
        $totalInvoiced = (float)Invoice::where('tenant_id', $tenantId)->where('branch_id', $branchId)->sum('total_amount');
        $totalCollected = (float)Payment::where('tenant_id', $tenantId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                  ->orWhereHas('invoice', fn($inv) => $inv->where('branch_id', $branchId));
            })
            ->where('status', 'success')
            ->sum('amount');
        $totalPending = (float)Invoice::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->sum('outstanding_amount');
        $totalExpenses = (float)Expense::where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('status', 'approved')->sum('amount');
        $netProfit = $totalInvoiced - $totalExpenses;

        // Employees & Productivity
        $employeeCount = Employee::where('tenant_id', $tenantId)->where('branch_id', $branchId)->count();
        $activeEmployees = Employee::where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('status', 'active')->count();

        // Support Tickets
        $totalTickets = SupportTicket::where('tenant_id', $tenantId)->where('branch_id', $branchId)->count();
        $openTickets = SupportTicket::where('tenant_id', $tenantId)->where('branch_id', $branchId)->whereIn('status', ['new', 'open', 'in_progress', 'waiting_customer'])->count();
        $slaBreachedTickets = SupportTicket::where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('sla_status', 'breached')->count();

        // Appointments & Meetings
        $upcomingAppointments = Meeting::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('meeting_date', '>=', Carbon::today())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->count();

        // Projects
        $totalProjects = Project::where('tenant_id', $tenantId)->where('branch_id', $branchId)->count();
        $activeProjects = Project::where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('status', 'active')->count();
        $completedProjects = Project::where('tenant_id', $tenantId)->where('branch_id', $branchId)->where('status', 'completed')->count();

        // Current Period Sales Targets
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;
        $target = BranchSalesTarget::where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where('period_year', $currentYear)
            ->where(function ($q) use ($currentMonth) {
                $q->where('period_number', $currentMonth)
                  ->orWhere('period_type', 'annual');
            })
            ->first();

        $targetAmount = $target ? (float)$target->target_amount : 0.0;
        $targetAchieved = $target ? (float)$target->achieved_amount : $totalInvoiced;
        $targetProgress = $targetAmount > 0 ? round(($targetAchieved / $targetAmount) * 100, 2) : 100.0;

        return [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'city' => $branch->city,
                'state' => $branch->state,
                'manager' => $branch->manager ? ['id' => $branch->manager->id, 'name' => $branch->manager->name] : null,
            ],
            'leads' => [
                'total' => $totalLeads,
                'converted' => $convertedLeads,
                'conversion_rate_percentage' => $conversionRate,
            ],
            'customers' => [
                'new_count' => $newCustomers,
            ],
            'deals' => [
                'total_count' => $dealsCount,
                'won_count' => $dealsWonCount,
                'pipeline_value' => $totalPipelineValue,
                'won_value' => $wonPipelineValue,
            ],
            'sales' => [
                'invoiced_revenue' => $totalInvoiced,
                'collected_revenue' => $totalCollected,
                'pending_receivables' => $totalPending,
                'expenses' => $totalExpenses,
                'net_profit' => $netProfit,
            ],
            'employees' => [
                'total_headcount' => $employeeCount,
                'active_headcount' => $activeEmployees,
            ],
            'support' => [
                'total_tickets' => $totalTickets,
                'open_tickets' => $openTickets,
                'sla_breached' => $slaBreachedTickets,
            ],
            'appointments' => [
                'upcoming' => $upcomingAppointments,
            ],
            'projects' => [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'completed' => $completedProjects,
            ],
            'targets' => [
                'target_amount' => $targetAmount,
                'achieved_amount' => $targetAchieved,
                'progress_percentage' => $targetProgress,
            ],
        ];
    }

    /**
     * Compute Central Owner Consolidated Multi-Branch Analytics across the organization.
     */
    public function getConsolidatedAnalytics(int $tenantId, ?string $startDate = null, ?string $endDate = null, ?int $departmentId = null): array
    {
        $branches = Branch::where('tenant_id', $tenantId)
            ->with(['manager', 'departments'])
            ->get();

        $branchComparisons = [];
        $totalLeads = 0;
        $totalSales = 0.0;
        $totalCollections = 0.0;
        $totalExpenses = 0.0;
        $totalOutstanding = 0.0;
        $totalEmployees = 0;
        $totalOpenTickets = 0;

        foreach ($branches as $branch) {
            $metrics = $this->getBranchDashboardMetrics($branch);

            $branchData = [
                'branch_id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'city' => $branch->city,
                'state' => $branch->state,
                'is_active' => $branch->is_active,
                'manager_name' => $branch->manager?->name ?? 'Unassigned',
                'leads_count' => $metrics['leads']['total'],
                'conversion_rate' => $metrics['leads']['conversion_rate_percentage'],
                'deals_count' => $metrics['deals']['total_count'],
                'won_value' => $metrics['deals']['won_value'],
                'sales_invoiced' => $metrics['sales']['invoiced_revenue'],
                'collections' => $metrics['sales']['collected_revenue'],
                'expenses' => $metrics['sales']['expenses'],
                'net_profit' => $metrics['sales']['net_profit'],
                'outstanding' => $metrics['sales']['pending_receivables'],
                'employees_count' => $metrics['employees']['total_headcount'],
                'open_tickets' => $metrics['support']['open_tickets'],
                'target_achievement_percentage' => $metrics['targets']['progress_percentage'],
            ];

            $branchComparisons[] = $branchData;

            $totalLeads += $branchData['leads_count'];
            $totalSales += $branchData['sales_invoiced'];
            $totalCollections += $branchData['collections'];
            $totalExpenses += $branchData['expenses'];
            $totalOutstanding += $branchData['outstanding'];
            $totalEmployees += $branchData['employees_count'];
            $totalOpenTickets += $branchData['open_tickets'];
        }

        $overallNetProfit = $totalSales - $totalExpenses;
        $collectionEfficiency = $totalSales > 0 ? round(($totalCollections / $totalSales) * 100, 2) : 0.0;

        return [
            'summary' => [
                'total_branches' => $branches->count(),
                'active_branches' => $branches->where('is_active', true)->count(),
                'total_leads' => $totalLeads,
                'total_sales' => $totalSales,
                'total_collections' => $totalCollections,
                'total_expenses' => $totalExpenses,
                'net_profit' => $overallNetProfit,
                'total_outstanding' => $totalOutstanding,
                'total_employees' => $totalEmployees,
                'total_open_tickets' => $totalOpenTickets,
                'collection_efficiency_percentage' => $collectionEfficiency,
            ],
            'branch_comparisons' => $branchComparisons,
        ];
    }

    /**
     * Generate structured branch code (e.g. BR-DEL-01).
     */
    protected function generateBranchCode(?string $city, int $tenantId): string
    {
        $cityCode = $city ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $city), 0, 3)) : 'LOC';
        if (strlen($cityCode) < 3) {
            $cityCode = str_pad($cityCode, 3, 'X');
        }

        $count = Branch::where('tenant_id', $tenantId)->count() + 1;
        return sprintf('BR-%s-%02d', $cityCode, $count);
    }

    /**
     * Default standard business hours (09:00 - 18:00 IST).
     */
    protected function defaultBusinessHours(): array
    {
        return [
            'monday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00', 'lunch_break' => '13:00-14:00'],
            'tuesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00', 'lunch_break' => '13:00-14:00'],
            'wednesday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00', 'lunch_break' => '13:00-14:00'],
            'thursday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00', 'lunch_break' => '13:00-14:00'],
            'friday' => ['is_open' => true, 'open' => '09:00', 'close' => '18:00', 'lunch_break' => '13:00-14:00'],
            'saturday' => ['is_open' => true, 'open' => '09:30', 'close' => '15:30', 'lunch_break' => null],
            'sunday' => ['is_open' => false, 'open' => null, 'close' => null, 'lunch_break' => null],
        ];
    }

    /**
     * Default branch operational settings.
     */
    protected function defaultBranchSettings(): array
    {
        return [
            'lead_routing' => [
                'strategy' => 'round_robin',
                'auto_assign' => true,
                'fallback_to_manager' => true,
            ],
            'appointments' => [
                'allow_walkins' => true,
                'slot_duration_minutes' => 30,
                'max_daily_slots' => 16,
            ],
            'communication' => [
                'default_whatsapp_line_id' => null,
                'default_telephony_caller_id' => null,
            ],
            'notifications' => [
                'alert_manager_on_sla_breach' => true,
                'daily_digest' => true,
            ],
            'holidays' => [],
        ];
    }
}
