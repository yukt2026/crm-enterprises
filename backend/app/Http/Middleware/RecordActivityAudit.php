<?php

namespace App\Http\Middleware;

use App\Domains\AuditSecurity\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RecordActivityAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log mutating API operations
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']) && $response->isSuccessful()) {
            $user = Auth::user();
            $path = $request->path();
            $segments = explode('/', trim($path, '/'));
            $module = $segments[2] ?? $segments[1] ?? 'system';

            try {
                ActivityLog::create([
                    'tenant_id' => $user?->tenant_id,
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'System',
                    'action' => strtoupper($request->method()) . '_' . strtoupper($module),
                    'module' => $module,
                    'details' => "Action on endpoint {$path} with status " . $response->getStatusCode(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $e) {
                // Silently avoid breaking response if audit log fails
            }
        }

        return $response;
    }
}
