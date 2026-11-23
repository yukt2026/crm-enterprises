<?php

namespace App\Domains\Analytics\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TrackingSetting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'tracking_settings';

    protected $fillable = [
        'tenant_id',
        'tracking_key',
        'is_enabled',
        'require_consent',
        'allowed_domains',
        'data_retention_days',
        'enabled_events',
        'anonymize_ip',
        'bot_filtering',
        'ga4_property_id',
        'ga4_measurement_id',
        'ga4_api_secret',
        'ga4_oauth_credentials',
        'ga4_connected_at',
        'ga4_cached_properties',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'require_consent' => 'boolean',
        'allowed_domains' => 'array',
        'data_retention_days' => 'integer',
        'enabled_events' => 'array',
        'anonymize_ip' => 'boolean',
        'bot_filtering' => 'boolean',
        'ga4_api_secret' => 'encrypted',
        'ga4_oauth_credentials' => 'encrypted:array',
        'ga4_connected_at' => 'datetime',
        'ga4_cached_properties' => 'array',
    ];

    /**
     * Default list of supported events
     */
    public const DEFAULT_EVENTS = [
        'page_view',
        'landing_page_view',
        'form_view',
        'form_submission',
        'cta_click',
        'campaign_parameters',
        'referrer',
        'conversion_event',
    ];

    /**
     * Find or create default settings for tenant
     */
    public static function forTenant(int $tenantId): self
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'tracking_key' => 'trk_live_' . Str::random(24),
                'is_enabled' => true,
                'require_consent' => false,
                'allowed_domains' => ['*'],
                'data_retention_days' => 90,
                'enabled_events' => self::DEFAULT_EVENTS,
                'anonymize_ip' => true,
                'bot_filtering' => true,
            ]
        );
    }
}
