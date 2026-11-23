<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DripSequence extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'description',
        'journey_type', // lead_nurturing, welcome_sequence, customer_onboarding, abandoned_recovery, renewal_reminder, birthday_anniversary, payment_reminder, re_engagement, post_purchase
        'trigger_event', // lead.created, deal.won, manual, invoice.unpaid, birthday
        'target_segment_id',
        'status', // draft, active, paused
        'exit_on_conversion',
        'exit_on_reply',
        'exit_on_unsubscribe',
        'respect_quiet_hours',
        'quiet_hours_start',
        'quiet_hours_end',
        'max_messages_per_week',
        'active_enrollments_count',
        'completed_enrollments_count',
        'created_by_user_id',
    ];

    protected $casts = [
        'exit_on_conversion' => 'boolean',
        'exit_on_reply' => 'boolean',
        'exit_on_unsubscribe' => 'boolean',
        'respect_quiet_hours' => 'boolean',
        'max_messages_per_week' => 'integer',
        'active_enrollments_count' => 'integer',
        'completed_enrollments_count' => 'integer',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(DripStep::class, 'drip_sequence_id')->orderBy('step_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(DripEnrollment::class, 'drip_sequence_id');
    }

    public function targetSegment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'target_segment_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
