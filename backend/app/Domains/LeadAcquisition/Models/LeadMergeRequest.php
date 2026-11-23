<?php

namespace App\Domains\LeadAcquisition\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadMergeRequest extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'master_lead_id',
        'duplicate_lead_id',
        'status',
        'requested_by_user_id',
        'approved_by_user_id',
        'field_overrides',
        'similarity_reasons',
        'rejection_reason',
        'merged_at',
    ];

    protected $casts = [
        'field_overrides' => 'array',
        'similarity_reasons' => 'array',
        'merged_at' => 'datetime',
    ];

    public function masterLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'master_lead_id');
    }

    public function duplicateLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'duplicate_lead_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
