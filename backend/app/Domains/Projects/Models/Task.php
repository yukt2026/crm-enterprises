<?php

namespace App\Domains\Projects\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
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

class Task extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'parent_task_id',
        'milestone_id',
        'created_by',
        'lead_id',
        'contact_id',
        'company_id',
        'deal_id',
        'ticket_id',
        'title',
        'description',
        'priority', // low, medium, high, urgent
        'status', // todo, in_progress, in_review, completed, done, cancelled
        'assigned_user_id',
        'due_date',
        'start_date',
        'estimated_hours',
        'reminder_at',
        'is_recurring',
        'recurring_rule', // daily, weekly, monthly
        'checklist_items', // array of {id, text, completed}
        'labels', // array of tags/labels
        'is_blocked',
        'block_reason',
        'order_index',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'start_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'reminder_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'is_recurring' => 'boolean',
        'checklist_items' => 'array',
        'labels' => 'array',
        'is_blocked' => 'boolean',
        'order_index' => 'integer',
    ];

    // --- RELATIONSHIPS ---

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id')->orderBy('order_index');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees', 'task_id', 'user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->assignedUser();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function taskDependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    public function dependsOnTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'depends_on_task_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function blockingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'depends_on_task_id', 'task_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    // --- SCOPES ---

    public function scopeMyTasks(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeTeamTasks(Builder $query, array $userIds): Builder
    {
        return $query->whereIn('assigned_user_id', $userIds);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['done', 'completed', 'cancelled']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', ['done', 'completed']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['done', 'completed', 'cancelled'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today()->toDateString());
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('due_date', Carbon::today()->toDateString());
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query->whereBetween('due_date', [
            Carbon::today()->toDateString(),
            Carbon::today()->addDays($days)->toDateString(),
        ]);
    }

    // --- HELPERS ---

    public function canComplete(&$uncompletedTasks = []): bool
    {
        $uncompleted = $this->dependsOnTasks()
            ->whereNotIn('status', ['done', 'completed'])
            ->get();

        if ($uncompleted->isNotEmpty()) {
            $uncompletedTasks = $uncompleted;
            return false;
        }

        return true;
    }
}
