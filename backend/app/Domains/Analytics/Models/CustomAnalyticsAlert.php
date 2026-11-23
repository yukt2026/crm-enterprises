<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomAnalyticsAlert extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'custom_analytics_alerts';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'metric_type',
        'condition_operator',
        'threshold_value',
        'notification_channels',
        'branch_id',
        'is_active',
        'last_triggered_at',
        'last_payload',
    ];

    protected $casts = [
        'threshold_value' => 'float',
        'notification_channels' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'last_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
