<?php

namespace App\Domains\Finance\Models;

use App\Domains\Inventory\Models\PurchaseOrder;
use App\Domains\Inventory\Models\Supplier;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorBill extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'vendor_bills';

    protected $fillable = [
        'tenant_id',
        'bill_number',
        'supplier_id',
        'purchase_order_id',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'currency',
        'status', // draft, pending_approval, approved, partially_paid, paid, overdue, disputed, cancelled
        'approved_by_user_id',
        'approved_at',
        'notes',
        'file_attachment',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'bill_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function vendorPayments(): HasMany
    {
        return $this->hasMany(VendorPayment::class, 'vendor_bill_id');
    }
}
