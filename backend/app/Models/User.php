<?php

namespace App\Models;

use App\Domains\AuditSecurity\Models\LoginHistory;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Models\TenantUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'current_tenant_id',
        'branch_id',
        'department_id',
        'legacy_id',
        'name',
        'email',
        'password',
        'role',
        'is_platform_admin',
        'platform_role',
        'platform_mfa_verified_at',
        'avatar',
        'status',
        'department',
        'two_factor_enabled',
        'two_factor_secret',
        'last_active_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'is_platform_admin' => 'boolean',
            'platform_mfa_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getTenantIdAttribute(): ?int
    {
        return $this->current_tenant_id;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot(['role', 'status', 'is_owner', 'joined_at'])
            ->withTimestamps();
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function belongsToWorkspace(int|string $tenantId): bool
    {
        return $this->workspaceMemberships()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists();
    }

    public function workspaceRole(int|string $tenantId): ?string
    {
        return $this->workspaceMemberships()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->value('role');
    }

    public function crmRoles(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Identity\Models\CrmRole::class, 'crm_user_roles', 'user_id', 'role_id')
            ->withPivot(['tenant_id', 'is_primary', 'assigned_by_user_id'])
            ->withTimestamps();
    }

    public function assignedConversations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domains\WhatsApp\Models\WhatsAppConversation::class, 'assigned_user_id');
    }

    public function salesTeams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\CRM\Models\SalesTeam::class, 'sales_team_members', 'user_id', 'team_id');
    }
}
