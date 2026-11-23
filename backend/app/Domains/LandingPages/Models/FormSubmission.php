<?php

namespace App\Domains\LandingPages\Models;

use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'form_id',
        'landing_page_id',
        'lead_id',
        'data',
        'utm_data',
        'referrer',
        'consent_given',
        'consent_text',
        'ip_address',
        'user_agent',
        'spam_score',
    ];

    protected $casts = [
        'data' => 'array',
        'utm_data' => 'array',
        'consent_given' => 'boolean',
        'spam_score' => 'float',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
