<?php

namespace App\Domains\Tenancy\Scopes;

use App\Domains\Tenancy\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = TenantContext::getTenantId();

        if ($tenantId) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
            return;
        }

        // Fallback to authenticated user tenant if context not explicitly set
        if (Auth::check()) {
            $user = Auth::user();
            if ($user && !$user->is_platform_admin && !$user->hasRole('Platform Super Admin')) {
                $targetId = $user->current_tenant_id ?? $user->tenant_id;
                if ($targetId) {
                    $builder->where($model->getTable() . '.tenant_id', $targetId);
                }
            }
        }
    }
}
