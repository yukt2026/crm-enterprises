<?php

namespace App\Domains\Workflow\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'workflows';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'status', // draft, published, paused
        'trigger_type',
        'current_version',
        'is_active',
        'total_runs_count',
        'last_run_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'current_version' => 'integer',
        'total_runs_count' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class, 'workflow_id')->orderBy('version', 'desc');
    }

    public function currentVersionModel(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class, 'workflow_id')->where('version', $this->current_version);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class, 'workflow_id')->orderBy('id', 'desc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
