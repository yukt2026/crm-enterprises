<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunicationSuppression extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'communication_suppressions';

    protected $fillable = [
        'tenant_id',
        'channel', // email, sms, whatsapp, all
        'identifier', // email or phone
        'reason', // unsubscribe, bounce, spam_complaint, dnd
        'suppressed_at',
    ];

    protected $casts = [
        'suppressed_at' => 'datetime',
    ];
}
