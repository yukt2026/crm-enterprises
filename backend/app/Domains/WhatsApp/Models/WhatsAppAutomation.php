<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppAutomation extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'whatsapp_automations';

    protected $fillable = [
        'tenant_id',
        'name',
        'journey_type', // new_enquiry_welcome, lead_qualification, appointment_confirmation, payment_reminder, invoice_reminder, renewal_reminder, customer_onboarding, service_update, sales_followup, abandoned_enquiry
        'trigger_event', // lead_created, deal_stage_changed, invoice_created, invoice_due, payment_received, appointment_booked, abandoned_cart, ticket_created
        'delay_minutes',
        'conditions',
        'whatsapp_phone_number_id',
        'whatsapp_template_id',
        'message_payload',
        'stop_condition', // customer_replied, payment_received, invoice_paid, status_changed, none
        'is_active',
        'execution_count',
    ];

    protected $casts = [
        'delay_minutes' => 'integer',
        'conditions' => 'array',
        'message_payload' => 'array',
        'is_active' => 'boolean',
        'execution_count' => 'integer',
    ];

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsAppAutomationLog::class, 'automation_id');
    }
}
