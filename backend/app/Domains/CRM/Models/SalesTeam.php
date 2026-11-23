<?php

namespace App\Domains\CRM\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesTeam extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'leader_id',
        'member_ids',
        'description',
    ];

    protected $casts = [
        'member_ids' => 'array',
    ];

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function quotas(): HasMany
    {
        return $this->hasMany(SalesQuota::class);
    }

    public function getMembers()
    {
        if (empty($this->member_ids)) {
            return collect();
        }
        return User::whereIn('id', $this->member_ids)->get();
    }
}
