<?php

namespace App\Domains\Omnichannel\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsConfiguration extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'sms_configurations';

    protected $fillable = [
        'tenant_id',
        'name',
        'provider', // twilio, exotel, fast2sms, custom_http
        'account_sid',
        'auth_token',
        'api_key',
        'sender_id', // 6-character DLT Header
        'dlt_entity_id',
        'is_active',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'auth_token' => 'encrypted',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
    ];
}
