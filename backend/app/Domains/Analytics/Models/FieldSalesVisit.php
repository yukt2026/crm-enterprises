<?php

namespace App\Domains\Analytics\Models;

use App\Domains\CRM\Models\Branch;
use App\Domains\CRM\Models\Customer;
use App\Domains\HR\Models\Employee;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Task;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldSalesVisit extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'field_sales_visits';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'customer_id',
        'lead_id',
        'branch_id',
        'title',
        'status', // scheduled, checked_in, completed, cancelled, missed
        'scheduled_at',
        'check_in_at',
        'check_in_lat',
        'check_in_lng',
        'check_in_address',
        'check_out_at',
        'check_out_lat',
        'check_out_lng',
        'check_out_address',
        'distance_travelled_km',
        'visit_notes',
        'outcome', // deal_closed, followup_required, not_interested, product_demo, payment_collected, rescheduled
        'follow_up_date',
        'follow_up_task_id',
        'travel_expense_amount',
        'travel_expense_id',
        'route_waypoints',
        'is_offline_synced',
        'offline_client_uuid',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_lat' => 'float',
        'check_in_lng' => 'float',
        'check_out_lat' => 'float',
        'check_out_lng' => 'float',
        'distance_travelled_km' => 'float',
        'travel_expense_amount' => 'float',
        'follow_up_date' => 'date',
        'route_waypoints' => 'array',
        'is_offline_synced' => 'boolean',
    ];

    protected $appends = [
        'distance_km',
    ];

    public function getDistanceKmAttribute(): ?float
    {
        return $this->distance_travelled_km ? (float) $this->distance_travelled_km : 0.0;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function followUpTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'follow_up_task_id');
    }
}
