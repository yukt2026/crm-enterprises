<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCommunicationConsent extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_communication_consents';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'lead_id',
        'contact_id',
        'channel',
        'destination',
        'consent_status',
        'consent_source',
        'consent_timestamp',
        'opt_out_timestamp',
        'is_marketing_allowed',
        'is_transactional_allowed',
        'metadata',
    ];

    protected $casts = [
        'consent_timestamp' => 'datetime',
        'opt_out_timestamp' => 'datetime',
        'is_marketing_allowed' => 'boolean',
        'is_transactional_allowed' => 'boolean',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Check if communication is allowed on this channel for destination
     */
    public static function isAllowed(int $tenantId, string $channel, string $destination, string $type = 'transactional'): bool
    {
        $record = self::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('destination', $destination)
            ->where(function ($q) use ($channel) {
                $q->where('channel', $channel)->orWhere('channel', 'all');
            })
            ->first();

        if (!$record) {
            return true; // Default allowed unless opted out
        }

        if ($record->consent_status === 'opted_out' || $record->consent_status === 'suppressed') {
            return false;
        }

        if ($type === 'marketing' && !$record->is_marketing_allowed) {
            return false;
        }

        return true;
    }
}
