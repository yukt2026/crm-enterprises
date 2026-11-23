<?php

namespace App\Domains\CRM\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesQuota extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'sales_team_id',
        'user_id',
        'period',
        'start_date',
        'end_date',
        'target_revenue',
        'target_deals_count',
        'target_leads_count',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'target_revenue' => 'decimal:2',
        'target_deals_count' => 'integer',
        'target_leads_count' => 'integer',
    ];

    public function salesTeam(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
