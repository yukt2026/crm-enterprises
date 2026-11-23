<?php

namespace App\Domains\CRM\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BranchTransfer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'branch_transfers';

    protected $fillable = [
        'tenant_id',
        'transferable_type',
        'transferable_id',
        'from_branch_id',
        'to_branch_id',
        'initiated_by_user_id',
        'approved_by_user_id',
        'status',
        'reason',
        'approval_notes',
        'metadata',
        'transferred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'transferred_at' => 'datetime',
    ];

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function transferable(): MorphTo
    {
        return $this->morphTo();
    }
}
