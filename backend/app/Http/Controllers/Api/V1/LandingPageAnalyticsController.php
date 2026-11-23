<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\LandingPages\Models\LandingPage;
use App\Domains\LandingPages\Services\LandingPageAnalyticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LandingPageAnalyticsController extends Controller
{
    public function __construct(
        protected LandingPageAnalyticsService $analyticsService
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $page = LandingPage::where('tenant_id', $tenantId)->findOrFail($id);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $metrics = $this->analyticsService->getMetrics($page, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'landing_page_id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'metrics' => $metrics,
            ],
        ]);
    }
}
