<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignApproval extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'campaign_approvals';

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'approver_user_id',
        'role_level', // marketing_manager, org_admin
        'action', // requested, approved, rejected
        'comments',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
