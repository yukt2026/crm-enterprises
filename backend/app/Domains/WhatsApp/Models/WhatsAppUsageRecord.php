<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppUsageRecord extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'whatsapp_usage_records';

    protected $fillable = [
        'tenant_id',
        'whatsapp_phone_number_id',
        'campaign_id',
        'conversation_id',
        'conversation_category', // marketing, utility, authentication, service
        'provider_reported_charge',
        'rate_currency',
        'billable_conversations_count',
        'recorded_date',
    ];

    protected $casts = [
        'provider_reported_charge' => 'decimal:4',
        'billable_conversations_count' => 'integer',
        'recorded_date' => 'date',
    ];

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'campaign_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }
}
