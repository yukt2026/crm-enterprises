<?php

namespace App\Domains\Finance\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentLink extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'payment_links';

    protected $fillable = [
        'tenant_id',
        'link_token',
        'title',
        'description',
        'payable_type',
        'payable_id',
        'customer_id',
        'amount_type',
        'amount',
        'min_partial_amount',
        'currency',
        'expires_at',
        'gateway_provider',
        'gateway_link_id',
        'gateway_link_url',
        'upi_qr_payload',
        'status',
        'paid_amount',
        'payment_id',
        'customer_notified_at',
        'created_by_user_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'min_partial_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'customer_notified_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
