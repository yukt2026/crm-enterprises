<?php

namespace App\Domains\AuditSecurity\Services;

use App\Domains\AuditSecurity\Models\AuditLog;
use App\Domains\Tenancy\Services\TenantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Record an immutable audit log entry
     */
    public static function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null
    ): AuditLog {
        $user = $actor ?? Auth::user();
        $tenantId = TenantContext::getTenantId() ?? $user?->current_tenant_id ?? 1;

        $correlationId = request()->header('X-Correlation-Id') ?? (string) Str::uuid();

        return AuditLog::create([
            'uuid' => $correlationId,
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'role' => $user?->role ?? 'System Service',
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System API',
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'created_at' => now(),
        ]);
    }
}
