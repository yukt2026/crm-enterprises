<?php

namespace App\Domains\CustomerPortal\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortalSetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'portal_settings';

    protected $fillable = [
        'tenant_id',
        'is_portal_enabled',
        'allow_self_registration',
        'allow_invoice_access',
        'allow_payments',
        'allow_project_tracking',
        'allow_ticket_creation',
        'allow_appointment_booking',
        'allow_document_downloads',
        'public_booking_page_enabled',
        'booking_slug',
        'company_logo_url',
        'brand_color',
        'booking_terms',
        'booking_rules',
    ];

    protected $casts = [
        'is_portal_enabled' => 'boolean',
        'allow_self_registration' => 'boolean',
        'allow_invoice_access' => 'boolean',
        'allow_payments' => 'boolean',
        'allow_project_tracking' => 'boolean',
        'allow_ticket_creation' => 'boolean',
        'allow_appointment_booking' => 'boolean',
        'allow_document_downloads' => 'boolean',
        'public_booking_page_enabled' => 'boolean',
        'booking_rules' => 'array',
    ];
}
