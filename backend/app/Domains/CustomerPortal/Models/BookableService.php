<?php

namespace App\Domains\CustomerPortal\Models;

use App\Domains\CRM\Models\Branch;
use App\Domains\Projects\Models\Meeting;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookableService extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'bookable_services';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'description',
        'duration_minutes',
        'price',
        'currency',
        'requires_advance_payment',
        'advance_amount',
        'assigned_staff_ids',
        'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'float',
        'advance_amount' => 'float',
        'requires_advance_payment' => 'boolean',
        'assigned_staff_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Meeting::class, 'service_id');
    }
}
