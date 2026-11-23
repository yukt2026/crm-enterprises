<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Domains\PlatformAdmin\Services\PlatformProviderService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformProviderController extends Controller
{
    public function __construct(
        protected PlatformProviderService $providerService
    ) {}

    /**
     * GET /api/v1/platform/providers
     */
    public function index(): JsonResponse
    {
        $providers = $this->providerService->getProviders();
        return response()->json([
            'success' => true,
            'data' => $providers,
            'message' => 'Platform AI and infrastructure providers retrieved',
        ], 200);
    }

    /**
     * PUT /api/v1/platform/providers/{key}
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'credentials' => 'nullable', // string or array, will be encrypted at rest
            'available_models' => 'nullable|array',
            'default_model' => 'nullable|string',
            'routing_policy' => 'nullable|array',
            'rate_limit_rpm' => 'nullable|integer|min:1',
            'rate_limit_tpm' => 'nullable|integer|min:100',
        ]);

        $provider = $this->providerService->updateProvider(
            $key,
            $validated,
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $provider,
            'message' => "Provider '{$provider->name}' configuration updated successfully",
        ], 200);
    }

    /**
     * POST /api/v1/platform/providers/{key}/test-health
     */
    public function testHealth(Request $request, string $key): JsonResponse
    {
        $result = $this->providerService->testProviderHealth(
            $key,
            $request->user(),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Provider health check executed',
        ], 200);
    }
}
