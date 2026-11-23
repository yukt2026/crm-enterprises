<?php

namespace App\Domains\Tenancy\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_name',
        'company_email',
        'company_phone',
        'tax_rate',
        'currency',
        'theme',
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2',
    ];
}
