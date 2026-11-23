<?php

namespace App\Domains\Finance\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GstRegistration extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'business_legal_name',
        'trade_name',
        'gstin',
        'pan',
        'registered_address',
        'city',
        'state',
        'state_code',
        'pincode',
        'business_type',
        'gst_registration_status',
        'tax_calculation_preference',
        'default_place_of_supply',
        'invoice_prefix',
        'financial_year',
        'invoice_numbering_series',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'gst_registration_id');
    }
}
