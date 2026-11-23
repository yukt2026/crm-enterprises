<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\CRM\Models\Branch;
use App\Domains\HR\Models\Department;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnichannelChannelSetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'omnichannel_channel_settings';

    protected $fillable = [
        'tenant_id',
        'channel',
        'channel_account_id',
        'name',
        'branch_id',
        'department_id',
        'business_hours',
        'is_active',
        'health_status',
        'last_health_check_at',
        'last_error',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'business_hours' => 'array',
        'last_error' => 'array',
        'metadata' => 'array',
        'last_health_check_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }
}
