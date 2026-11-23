<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardWidgetLayout extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'dashboard_widget_layouts';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'name',
        'is_default',
        'widgets',
        'filters',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'widgets' => 'array',
        'filters' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
