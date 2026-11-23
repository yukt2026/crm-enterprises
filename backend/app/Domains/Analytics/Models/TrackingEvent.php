<?php

namespace App\Domains\Analytics\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingEvent extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'tracking_events';

    protected $fillable = [
        'tenant_id',
        'tracking_key',
        'session_id',
        'event_name',
        'event_id',
        'page_url',
        'page_title',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'consent_given',
        'lead_id',
        'customer_id',
        'metadata',
        'ip_anonymized',
        'user_agent',
        'is_bot',
    ];

    protected $casts = [
        'consent_given' => 'boolean',
        'is_bot' => 'boolean',
        'metadata' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
