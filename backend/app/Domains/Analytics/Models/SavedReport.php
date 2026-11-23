<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedReport extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'description',
        'report_type', // executive, leads, sales, marketing, attribution, operations, communications
        'module',
        'group_by',
        'sort_by',
        'sort_direction',
        'filters',
        'selected_columns',
        'aggregations',
        'chart_type',
        'is_scheduled',
        'schedule_frequency',
        'schedule_recipients',
        'last_run_at',
        'exported_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'selected_columns' => 'array',
        'aggregations' => 'array',
        'schedule_recipients' => 'array',
        'is_scheduled' => 'boolean',
        'last_run_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
