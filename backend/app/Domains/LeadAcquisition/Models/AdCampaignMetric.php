<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdCampaignMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'lead_source_id',
        'provider',
        'campaign_id',
        'campaign_name',
        'ad_group_id',
        'ad_group_name',
        'ad_id',
        'ad_name',
        'date',
        'spend',
        'currency',
        'impressions',
        'clicks',
        'leads_count',
        'cost_per_lead',
    ];

    protected $casts = [
        'date' => 'date',
        'spend' => 'decimal:2',
        'cost_per_lead' => 'decimal:2',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'leads_count' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }
}
