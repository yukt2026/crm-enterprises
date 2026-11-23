<?php

namespace App\Domains\LandingPages\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomDomain extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'domain',
        'verification_token',
        'verification_type',
        'verification_status', // pending, verified, failed
        'ssl_status', // pending, active, failed
        'verified_at',
        'last_verification_error',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class, 'custom_domain_id');
    }
}
