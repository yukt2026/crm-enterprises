<?php

namespace App\Domains\CustomerPortal\Models;

use App\Domains\CRM\Models\Customer;
use App\Domains\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class CustomerAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'customer_accounts';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'email',
        'phone',
        'password',
        'is_active',
        'email_verified_at',
        'two_factor_enabled',
        'two_factor_secret',
        'last_login_at',
        'last_login_ip',
        'invite_token',
        'invite_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'invite_expires_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(CustomerLoginHistory::class, 'customer_account_id');
    }
}
