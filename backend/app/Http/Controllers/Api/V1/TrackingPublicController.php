<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Services\TrackingScriptGeneratorService;
use App\Domains\Analytics\Services\TrackingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingPublicController extends Controller
{
    public function __construct(
        protected TrackingService $trackingService,
        protected TrackingScriptGeneratorService $scriptService
    ) {}

    /**
     * Ingest tracking event from website or landing page (Public)
     */
    public function ingestEvent(Request $request): JsonResponse
    {
        $payload = $request->all();
        
        // Also support X-Tracking-Key header
        if (!$request->has('tracking_key') && $request->hasHeader('X-Tracking-Key')) {
            $payload['tracking_key'] = $request->header('X-Tracking-Key');
        }

        $clientIp = $request->ip();
        $userAgent = $request->userAgent();

        $result = $this->trackingService->ingestEvent($payload, $clientIp, $userAgent);

        return response()->json([
            'success' => $result['status'] === 'success' || $result['status'] === 'duplicate',
            'data' => $result,
        ]);
    }

    /**
     * Serve dynamic tracking script snippet for a given tracking key (Public)
     */
    public function serveScript(string $key, Request $request): Response
    {
        $baseUrl = config('app.url') ?? $request->getSchemeAndHttpHost();
        $js = $this->scriptService->generateScript($key, $baseUrl);

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
