<?php

namespace App\Domains\Voice\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallIvrFlow extends Model
{
    protected $table = 'call_ivr_flows';

    protected $fillable = [
        'tenant_id',
        'phone_number_id',
        'name',
        'description',
        'welcome_audio_text',
        'language',
        'business_hours',
        'holiday_rules',
        'menu_options',
        'unanswered_action',
        'unanswered_target',
        'voicemail_box',
        'is_active',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'holiday_rules' => 'array',
        'menu_options' => 'array',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(TelephonyPhoneNumber::class, 'phone_number_id');
    }
}
