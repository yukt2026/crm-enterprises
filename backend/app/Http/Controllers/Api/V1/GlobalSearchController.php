<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Search\Services\GlobalSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected GlobalSearchService $searchService
    ) {}

    /**
     * Unified multi-entity search endpoint
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->input('q', '');
        $module = $request->input('module');
        $limit = min((int) $request->input('limit', 20), 50);

        $results = $this->searchService->search($query, $module, $limit);

        return response()->json([
            'success' => true,
            'status_code' => 200,
            'message' => 'Search results retrieved.',
            'data' => $results,
            'meta' => [
                'total_results' => count($results),
                'query' => $query,
                'module_filter' => $module,
            ],
        ]);
    }
}
