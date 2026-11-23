<?php

namespace App\Domains\Inventory\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Finance\Models\CreditNote;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Sales\Models\Product;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerReturn extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'return_number',
        'customer_id',
        'invoice_id',
        'order_reference',
        'warehouse_id',
        'status', // requested, inspecting, restock_approved, damaged_quarantined, refund_approved, completed, rejected
        'return_reason',
        'product_condition', // unopened, opened_good, defective, damaged_in_transit
        'restock_action', // restock_available, quarantine_damage, scrap, return_to_vendor
        'refund_type', // credit_note, bank_refund, replacement
        'credit_note_id',
        'replacement_product_id',
        'total_refund_amount',
        'inspected_by',
        'approved_by',
        'inspection_notes',
    ];

    protected $casts = [
        'total_refund_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function replacementProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'replacement_product_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerReturnItem::class);
    }
}
