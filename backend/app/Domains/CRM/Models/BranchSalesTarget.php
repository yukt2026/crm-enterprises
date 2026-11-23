<?php

namespace App\Domains\CRM\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSalesTarget extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'branch_sales_targets';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'period_type',
        'period_year',
        'period_number',
        'target_amount',
        'achieved_amount',
        'target_leads',
        'achieved_leads',
        'target_deals',
        'achieved_deals',
        'notes',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_number' => 'integer',
        'target_amount' => 'float',
        'achieved_amount' => 'float',
        'target_leads' => 'integer',
        'achieved_leads' => 'integer',
        'target_deals' => 'integer',
        'achieved_deals' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
