<?php

namespace App\Domains\Projects\Models;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'organizer_id',
        'lead_id',
        'contact_id',
        'company_id',
        'deal_id',
        'project_id',
        'branch_id',
        'customer_id',
        'service_id',
        'booking_reference',
        'payment_status',
        'booking_fee',
        'cancellation_reason',
        'cancelled_at',
        'rescheduled_at',
        'is_customer_booked',
        'title',
        'description',
        'agenda',
        'meeting_date',
        'start_time',
        'end_time',
        'timezone',
        'location_type', // online, physical
        'location_address',
        'meet_link',
        'notes',
        'outcome',
        'status', // scheduled, in_progress, completed, cancelled, rescheduled
        'rescheduled_from_meeting_id',
    ];

    protected $casts = [
        'meeting_date' => 'date',
        'is_customer_booked' => 'boolean',
        'booking_fee' => 'float',
        'cancelled_at' => 'datetime',
        'rescheduled_at' => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\Customer::class, 'customer_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CustomerPortal\Models\BookableService::class, 'service_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\Branch::class, 'branch_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'rescheduled_from_meeting_id');
    }

    // --- SCOPES ---

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('meeting_date', '>=', Carbon::today()->toDateString())
            ->where('status', 'scheduled')
            ->orderBy('meeting_date', 'asc')
            ->orderBy('start_time', 'asc');
    }

    public function scopeForDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('meeting_date', [$startDate, $endDate]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('organizer_id', $userId)
              ->orWhereHas('attendees', function ($sub) use ($userId) {
                  $sub->where('user_id', $userId);
              });
        });
    }
}
