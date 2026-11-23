<?php

namespace App\Domains\CRM\Models;

use App\Domains\CustomFields\Traits\HasCustomFields;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Projects\Models\Task;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant, HasCustomFields;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'company_id',
        'contact_id',
        'lead_id',
        'pipeline_id',
        'pipeline_stage_id',
        'branch_id',
        'sales_team',
        'name',
        'value',
        'currency',
        'stage',
        'probability',
        'weighted_value',
        'stage_updated_at',
        'close_date',
        'next_follow_up_at',
        'assigned_user_id',
        'collaborator_ids',
        'lost_reason',
        'competitor',
        'notes',
        'source',
        'campaign',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'tags',
        'is_archived',
        'won_at',
        'lost_at',
        'reopened_at',
        'reopened_by_user_id',
        'project_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'weighted_value' => 'decimal:2',
        'probability' => 'integer',
        'pipeline_id' => 'integer',
        'pipeline_stage_id' => 'integer',
        'branch_id' => 'integer',
        'project_id' => 'integer',
        'close_date' => 'date',
        'stage_updated_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'reopened_at' => 'datetime',
        'tags' => 'array',
        'collaborator_ids' => 'array',
        'is_archived' => 'boolean',
    ];

    // --- RELATIONSHIPS ---

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function reopenedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(DealProduct::class);
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(DealStageHistory::class)->orderByDesc('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class)->orderByDesc('id');
    }

    public function mediaFiles(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'attachable');
    }

    public function getCollaborators()
    {
        if (empty($this->collaborator_ids)) {
            return collect();
        }
        return User::whereIn('id', $this->collaborator_ids)->get();
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

    public function scopeWon(Builder $query): Builder
    {
        return $query->where('stage', 'won');
    }

    public function scopeLost(Builder $query): Builder
    {
        return $query->where('stage', 'lost');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', ['won', 'lost'])->where('is_archived', false);
    }

    public function scopeStale(Builder $query, int $days = 14): Builder
    {
        return $query->open()
            ->where(function ($q) use ($days) {
                $q->whereNull('stage_updated_at')->where('created_at', '<', now()->subDays($days))
                  ->orWhere('stage_updated_at', '<', now()->subDays($days));
            });
    }
}
