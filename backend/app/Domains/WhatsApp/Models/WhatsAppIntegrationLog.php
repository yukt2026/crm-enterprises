<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppIntegrationLog extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false;

    protected $table = 'whatsapp_integration_logs';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'whatsapp_phone_number_id',
        'event_type',
        'status',
        'payload',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }
}
