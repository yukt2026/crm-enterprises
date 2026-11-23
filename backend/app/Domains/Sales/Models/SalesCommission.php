<?php

namespace App\Domains\Sales\Models;

use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Quotation;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCommission extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'sales_commissions';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'commission_plan_id',
        'deal_id',
        'quotation_id',
        'invoice_id',
        'payment_id',
        'base_amount',
        'commission_rate',
        'commission_amount',
        'calculation_basis', // deal_won, payment_collected
        'status', // pending_approval, approved, paid, reversed
        'approved_by_user_id',
        'approved_at',
        'payout_date',
        'reversal_reason',
        'reversed_at',
        'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
        'payout_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commissionPlan(): BelongsTo
    {
        return $this->belongsTo(CommissionPlan::class, 'commission_plan_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
