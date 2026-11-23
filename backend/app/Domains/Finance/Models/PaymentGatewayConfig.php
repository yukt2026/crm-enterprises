<?php

namespace App\Domains\Finance\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'payment_gateways_config';

    protected $fillable = [
        'tenant_id',
        'provider', // razorpay, cashfree, upi_qr, bank_transfer
        'display_name',
        'is_active',
        'is_test_mode',
        'credentials',
        'upi_vpa',
        'merchant_name',
        'bank_account_details',
        'webhook_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
        'credentials' => 'array',
        'bank_account_details' => 'array',
    ];
}
