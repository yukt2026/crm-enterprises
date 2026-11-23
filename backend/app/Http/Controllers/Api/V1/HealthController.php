<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    use ApiResponse;

    public function check(): JsonResponse
    {
        $dbStatus = 'disconnected';
        $dbLatencyMs = null;

        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $start) * 1000, 2);
            $dbStatus = 'connected';
        } catch (\Throwable $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        $healthData = [
            'status' => $dbStatus === 'connected' ? 'healthy' : 'degraded',
            'version' => '1.0.0',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'database' => [
                    'connection' => config('database.default'),
                    'status' => $dbStatus,
                    'latency_ms' => $dbLatencyMs,
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'status' => 'operational',
                ],
                'queue' => [
                    'driver' => config('queue.default'),
                    'status' => 'operational',
                ],
                'storage' => [
                    'disk' => config('filesystems.default'),
                    'status' => 'operational',
                ],
            ],
        ];

        $statusCode = $dbStatus === 'connected' ? 200 : 503;
        return $this->successResponse($healthData, 'System health diagnostics', $statusCode);
    }
}
