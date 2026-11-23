<?php

namespace App\Http\Middleware;

use App\Domains\AuditSecurity\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey || !in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // Check if cached response exists and has not expired
        $existing = IdempotencyKey::where('key', $idempotencyKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response($existing->response_body, $existing->status_code)
                ->header('Content-Type', 'application/json')
                ->header('X-Cache-Lookup', 'HIT-IDEMPOTENT');
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            try {
                IdempotencyKey::updateOrCreate(
                    ['key' => $idempotencyKey],
                    [
                        'user_id' => Auth::id(),
                        'status_code' => $response->getStatusCode(),
                        'response_body' => $response->getContent(),
                        'expires_at' => now()->addHours(24),
                    ]
                );
            } catch (\Throwable) {
                // Ignore key storage error
            }
        }

        return $response;
    }
}
