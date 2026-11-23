<?php

namespace App\Domains\Marketing\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MarketingConsent extends Model
{
    use BelongsToTenant;

    protected $table = 'marketing_consents';

    protected $fillable = [
        'tenant_id',
        'entity_type', // lead, contact, customer
        'entity_id',
        'channel', // whatsapp, email, sms, voice, all
        'consent_status', // opted_in, opted_out, pending
        'source', // website_form, manual, import, stop_reply
        'reason',
        'opted_out_at',
    ];

    protected $casts = [
        'opted_out_at' => 'datetime',
    ];
}
