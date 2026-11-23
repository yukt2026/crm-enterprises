<?php

namespace App\Domains\Identity\Models;

use App\Domains\Tenancy\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_roles';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'is_system',
        'is_active',
        'color',
        'parent_role_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Tenant relationship. Null for global system templates.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Role permissions relationship.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(CrmRolePermission::class, 'role_id');
    }

    /**
     * Users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_user_roles', 'role_id', 'user_id')
            ->withPivot(['tenant_id', 'is_primary', 'assigned_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Parent template role.
     */
    public function parentRole(): BelongsTo
    {
        return $this->belongsTo(CrmRole::class, 'parent_role_id');
    }

    /**
     * Child roles cloned from this role.
     */
    public function childRoles(): HasMany
    {
        return $this->hasMany(CrmRole::class, 'parent_role_id');
    }

    /**
     * Creator user.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope for a specific tenant or global system templates.
     */
    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }

        return $query->where(function (Builder $sub) use ($tenantId) {
            $sub->where('tenant_id', $tenantId)
                ->orWhere(function (Builder $nested) {
                    $nested->whereNull('tenant_id')->where('is_system', true);
                });
        });
    }

    /**
     * Scope active roles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if role has permission for module and action.
     */
    public function hasPermission(string $module, string $action): bool
    {
        return $this->permissions->contains(function ($perm) use ($module, $action) {
            return $perm->module === $module && ($perm->action === $action || $perm->action === '*');
        });
    }

    /**
     * Get data scope for module and action (e.g. 'all', 'own', 'assigned', 'team', 'department', 'branch').
     */
    public function getScopeFor(string $module, string $action = 'view'): string
    {
        $perm = $this->permissions->first(function ($p) use ($module, $action) {
            return $p->module === $module && ($p->action === $action || $p->action === '*');
        });

        return $perm ? $perm->data_scope : 'none';
    }
}
