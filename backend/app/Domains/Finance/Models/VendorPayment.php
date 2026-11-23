<?php

namespace App\Domains\Finance\Models;

use App\Domains\Inventory\Models\Supplier;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'vendor_payments';

    protected $fillable = [
        'tenant_id',
        'payment_number',
        'supplier_id',
        'vendor_bill_id',
        'amount',
        'currency',
        'payment_method',
        'payment_date',
        'reference_number',
        'status', // pending_approval, approved, disbursed, failed, cancelled
        'approved_by_user_id',
        'disbursed_by_user_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function disbursedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by_user_id');
    }
}
