<?php

namespace App\Domains\Inventory\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'contact_name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'tax_id',
        'pan_number',
        'gstin',
        'bank_name',
        'bank_account_number',
        'bank_ifsc',
        'bank_branch',
        'products_supplied',
        'outstanding_balance',
        'vendor_rating',
        'documents',
        'payment_terms',
        'currency',
        'status',
        'notes',
    ];

    protected $casts = [
        'products_supplied' => 'array',
        'documents' => 'array',
        'outstanding_balance' => 'decimal:2',
        'vendor_rating' => 'decimal:2',
    ];

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(\App\Domains\Finance\Models\VendorBill::class, 'supplier_id');
    }

    public function getMaskedBankAccountAttribute(): ?string
    {
        if (empty($this->bank_account_number)) {
            return null;
        }
        $len = strlen($this->bank_account_number);
        if ($len <= 4) {
            return '****' . $this->bank_account_number;
        }
        return str_repeat('X', max(0, $len - 4)) . substr($this->bank_account_number, -4);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
