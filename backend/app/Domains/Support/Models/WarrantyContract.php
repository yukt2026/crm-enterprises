<?php

namespace App\Domains\Support\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyContract extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'contract_type', // warranty, amc, service_agreement
        'contract_number',
        'product_id',
        'product_name',
        'serial_number',
        'start_date',
        'end_date',
        'coverage_details',
        'renewal_date',
        'renewal_price',
        'status', // active, expired, pending_renewal, cancelled
        'service_team_id',
        'assigned_engineer_id',
        'preventive_maintenance_interval_days',
        'last_service_date',
        'next_service_date',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'renewal_date' => 'date:Y-m-d',
        'last_service_date' => 'date:Y-m-d',
        'next_service_date' => 'date:Y-m-d',
        'renewal_price' => 'decimal:2',
        'preventive_maintenance_interval_days' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function assignedEngineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_engineer_id');
    }
}
