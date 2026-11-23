<?php

namespace App\Domains\WhatsApp\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppTemplate extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'tenant_id',
        'whatsapp_account_id',
        'template_name',
        'category', // MARKETING, UTILITY, AUTHENTICATION
        'language',
        'status', // APPROVED, PENDING, REJECTED, PAUSED
        'header_type', // NONE, TEXT, IMAGE, DOCUMENT, VIDEO
        'header_content',
        'body_text',
        'footer_text',
        'buttons',
        'variables_schema',
        'meta_template_id',
        'rejected_reason',
        'sent_count',
        'last_used_at',
    ];

    protected $casts = [
        'buttons' => 'array',
        'variables_schema' => 'array',
        'sent_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(WhatsAppCampaign::class, 'whatsapp_template_id');
    }

    public function automations(): HasMany
    {
        return $this->hasMany(WhatsAppAutomation::class, 'whatsapp_template_id');
    }
}
