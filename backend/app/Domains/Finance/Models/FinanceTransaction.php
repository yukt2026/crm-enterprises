<?php

namespace App\Domains\Finance\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'finance_transactions';

    protected $fillable = [
        'tenant_id',
        'transaction_number',
        'internal_transaction_id',
        'provider_transaction_id',
        'payment_id',
        'invoice_id',
        'customer_id',
        'gateway',
        'payment_method',
        'amount',
        'currency',
        'fee_amount',
        'tax_on_fee',
        'net_amount',
        'transaction_date',
        'provider_status',
        'internal_status',
        'reconciliation_status',
        'reconciled_at',
        'reconciled_by_user_id',
        'refund_reference',
        'webhook_event_id',
        'metadata',
        'is_posted',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'tax_on_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'transaction_date' => 'date',
        'reconciled_at' => 'datetime',
        'is_posted' => 'boolean',
        'metadata' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }
}
