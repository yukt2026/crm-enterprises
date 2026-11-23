<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Integrations\Services\OpenApiDocumentationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function __construct(
        protected OpenApiDocumentationService $openApiService
    ) {}

    /**
     * GET /api/v1/crm/docs/openapi.json
     * Output full OpenAPI 3.0.3 specification
     */
    public function spec(): JsonResponse
    {
        $spec = $this->openApiService->generateSpecification();
        return response()->json($spec, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'max-age=3600, public',
        ]);
    }
}
