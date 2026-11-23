<?php

namespace App\Domains\Finance\Models;

use App\Domains\CRM\Models\Deal;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountApproval extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'discount_approvals';

    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'deal_id',
        'requested_by_user_id',
        'discount_rate',
        'discount_amount',
        'reason',
        'status', // pending, approved, rejected
        'approver_user_id',
        'approver_comments',
        'approved_at',
    ];

    protected $casts = [
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
