<?php

namespace App\Domains\LandingPages\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageAnalytics extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'landing_page_analytics';

    protected $fillable = [
        'tenant_id',
        'landing_page_id',
        'event_type', // page_view, cta_click, form_view
        'device_type', // desktop, mobile, tablet
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }
}
