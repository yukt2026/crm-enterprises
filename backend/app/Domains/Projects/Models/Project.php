<?php

namespace App\Domains\Projects\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'customer_id',
        'company_id',
        'deal_id',
        'quotation_id',
        'branch_id',
        'owner_id',
        'manager_user_id',
        'name',
        'description',
        'notes',
        'status', // draft, planned, active, on_hold, at_risk, completed, cancelled, archived
        'priority', // low, medium, high, urgent
        'budget',
        'labor_cost_rate',
        'billing_rate',
        'estimated_hours',
        'start_date',
        'end_date',
        'progress',
        'client_portal_enabled',
        'is_archived',
        'archived_at',
        'completed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'integer',
        'budget' => 'decimal:2',
        'labor_cost_rate' => 'decimal:2',
        'billing_rate' => 'decimal:2',
        'estimated_hours' => 'integer',
        'client_portal_enabled' => 'boolean',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProjectStatusHistory::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // --- SCOPES ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('is_archived', true);
    }

    /**
     * Recalculate project progress based on tasks or milestones
     */
    public function recalculateProgress(): int
    {
        $taskCount = $this->tasks()->count();
        if ($taskCount > 0) {
            $completedCount = $this->tasks()->whereIn('status', ['done', 'completed'])->count();
            $calculatedProgress = (int) round(($completedCount / $taskCount) * 100);
            $this->update(['progress' => $calculatedProgress]);
            return $calculatedProgress;
        }

        $milestoneCount = $this->milestones()->count();
        if ($milestoneCount > 0) {
            $avgProgress = (int) round($this->milestones()->avg('progress') ?? 0);
            $this->update(['progress' => $avgProgress]);
            return $avgProgress;
        }

        return $this->progress ?? 0;
    }

    /**
     * Transition project lifecycle status with audit history
     */
    public function transitionStatus(string $newStatus, ?int $userId = null, ?string $reason = null): self
    {
        $oldStatus = $this->status;
        if ($oldStatus === $newStatus) {
            return $this;
        }

        $updates = ['status' => $newStatus];
        if (in_array($newStatus, ['completed', 'done']) && !$this->completed_at) {
            $updates['completed_at'] = Carbon::now();
            $updates['progress'] = 100;
        } elseif ($newStatus === 'archived') {
            $updates['is_archived'] = true;
            $updates['archived_at'] = Carbon::now();
        }

        $this->update($updates);

        ProjectStatusHistory::create([
            'tenant_id' => $this->tenant_id,
            'project_id' => $this->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by_user_id' => $userId,
            'reason' => $reason,
            'created_at' => Carbon::now(),
        ]);

        return $this;
    }

    /**
     * Compute comprehensive financial metrics & profitability
     */
    public function calculateFinancials(): array
    {
        $totalBudget = (float) $this->budget;

        // Invoiced Revenue (from linked invoices)
        $invoices = Invoice::where('tenant_id', $this->tenant_id)
            ->where('project_id', $this->id)
            ->get();
        $invoicedRevenue = (float) $invoices->sum('total_amount');

        // Collected Revenue (from linked invoice payments)
        $collectedRevenue = 0.0;
        foreach ($invoices as $inv) {
            $collectedRevenue += (float) $inv->payments()->where('status', 'successful')->sum('amount');
        }
        $outstandingRevenue = max(0.0, round($invoicedRevenue - $collectedRevenue, 2));

        // Actual Approved Project Expenses
        $expenses = Expense::where('tenant_id', $this->tenant_id)
            ->where('project_id', $this->id)
            ->where('status', 'approved')
            ->get();
        $actualExpenses = (float) $expenses->sum('amount');

        // Labor Costs from Approved Task Time Logs
        $timeLogs = TaskTimeLog::where('tenant_id', $this->tenant_id)
            ->where(function ($q) {
                $q->where('project_id', $this->id)
                  ->orWhereHas('task', fn($t) => $t->where('project_id', $this->id));
            })
            ->where('status', 'approved')
            ->get();

        $totalBillableMinutes = 0;
        $totalNonBillableMinutes = 0;
        $laborCostRate = (float) $this->labor_cost_rate > 0 ? (float) $this->labor_cost_rate : 500.0; // Default ₹500/hr labor cost
        $billingRate = (float) $this->billing_rate > 0 ? (float) $this->billing_rate : 1200.0; // Default ₹1200/hr billing rate
        $billableRevenue = 0.0;
        $laborCosts = 0.0;

        foreach ($timeLogs as $log) {
            $hours = $log->duration_minutes / 60.0;
            $laborCosts += ($hours * $laborCostRate);

            if ($log->is_billable) {
                $totalBillableMinutes += $log->duration_minutes;
                $billableRevenue += ($hours * ($log->hourly_rate > 0 ? $log->hourly_rate : $billingRate));
            } else {
                $totalNonBillableMinutes += $log->duration_minutes;
            }
        }

        $laborCosts = round($laborCosts, 2);
        $totalCosts = round($actualExpenses + $laborCosts, 2);
        $effectiveRevenue = $collectedRevenue > 0 ? $collectedRevenue : $invoicedRevenue;
        $grossProfit = round($effectiveRevenue - $totalCosts, 2);
        $profitMarginPct = $effectiveRevenue > 0 ? round(($grossProfit / $effectiveRevenue) * 100, 1) : 0.0;

        return [
            'project_id' => $this->id,
            'budget' => $totalBudget,
            'invoiced_revenue' => $invoicedRevenue,
            'collected_revenue' => $collectedRevenue,
            'outstanding_revenue' => $outstandingRevenue,
            'actual_expenses' => $actualExpenses,
            'labor_costs' => $laborCosts,
            'total_costs' => $totalCosts,
            'billable_hours' => round($totalBillableMinutes / 60, 2),
            'non_billable_hours' => round($totalNonBillableMinutes / 60, 2),
            'billable_revenue_potential' => round($billableRevenue, 2),
            'gross_profit' => $grossProfit,
            'profit_margin_percentage' => $profitMarginPct,
            'cost_variance' => round($totalBudget - $totalCosts, 2),
        ];
    }
}
