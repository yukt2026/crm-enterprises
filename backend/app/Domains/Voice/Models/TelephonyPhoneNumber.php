<?php

namespace App\Domains\Voice\Models;

use App\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelephonyPhoneNumber extends Model
{
    protected $table = 'telephony_phone_numbers';

    protected $fillable = [
        'tenant_id',
        'telephony_provider_id',
        'phone_number',
        'display_name',
        'country_code',
        'is_default',
        'routing_type',
        'default_agent_id',
        'default_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TelephonyProvider::class, 'telephony_provider_id');
    }

    public function defaultAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAiAgent::class, 'default_agent_id');
    }

    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(VoiceCall::class, 'from_number', 'phone_number');
    }
}
