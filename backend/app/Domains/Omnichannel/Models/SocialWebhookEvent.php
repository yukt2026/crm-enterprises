<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialWebhookEvent extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'social_webhook_events';

    protected $fillable = [
        'tenant_id',
        'provider',
        'page_id',
        'event_id',
        'event_type',
        'payload',
        'status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
