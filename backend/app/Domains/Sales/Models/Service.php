<?php

namespace App\Domains\Sales\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'billing_cycle',
        'price',
        'category',
        'status',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
