<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadRoutingRule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'priority',
        'is_active',
        'conditions',
        'assignment_method',
        'target_user_ids',
        'target_team_id',
        'target_branch_id',
        'fallback_user_id',
        'last_assigned_index',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
        'conditions' => 'array',
        'target_user_ids' => 'array',
        'last_assigned_index' => 'integer',
    ];

    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }
}
