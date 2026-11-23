<?php

namespace App\Domains\Tenancy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'domain',
        'subscription_tier',
        'status',
        'settings',
        'timezone',
        'branding',
        'onboarding_status',
        'onboarding_step',
        'industry_template_code',
        'suspended_at',
        'suspension_reason',
        'gstin',
        'pan',
        'billing_address',
        'billing_state_code',
    ];

    protected $casts = [
        'settings' => 'array',
        'branding' => 'array',
        'suspended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name) . '-' . Str::random(4);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot(['role', 'status', 'is_owner', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function systemSetting(): HasOne
    {
        return $this->hasOne(SystemSetting::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(\App\Domains\Billing\Models\TenantSubscription::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(\App\Domains\Billing\Models\TenantCreditWallet::class);
    }

    public function costProtectionRule(): HasOne
    {
        return $this->hasOne(\App\Domains\Billing\Models\TenantCostProtectionRule::class);
    }

    public function appliedTemplates(): HasMany
    {
        return $this->hasMany(\App\Domains\PlatformAdmin\Models\TenantAppliedTemplate::class);
    }
}
