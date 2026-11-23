<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Lead;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppAutomationLog extends Model
{
    use HasFactory, BelongsToTenant;

    public $timestamps = false;

    protected $table = 'whatsapp_automation_logs';

    protected $fillable = [
        'tenant_id',
        'automation_id',
        'contact_id',
        'lead_id',
        'invoice_id',
        'phone_number',
        'trigger_context',
        'status', // triggered, delayed, sent, stopped, failed
        'stopped_reason',
        'executed_at',
        'created_at',
    ];

    protected $casts = [
        'trigger_context' => 'array',
        'executed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAutomation::class, 'automation_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
