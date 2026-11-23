<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Models\SavedReport;
use App\Domains\Analytics\Services\AttributionService;
use App\Domains\Analytics\Services\ExecutiveAnalyticsService;
use App\Domains\Analytics\Services\LeadAnalyticsService;
use App\Domains\Analytics\Services\ReportExportService;
use App\Domains\Analytics\Services\SalesAnalyticsService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected ExecutiveAnalyticsService $executiveService,
        protected LeadAnalyticsService $leadService,
        protected SalesAnalyticsService $salesService,
        protected AttributionService $attributionService,
        protected ReportExportService $exportService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * GET /api/v1/crm/analytics/executive
     */
    public function executive(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $filters = $request->only(['from_date', 'to_date', 'employee_id']);

        $data = $this->executiveService->getExecutiveMetrics($tenantId, $filters);

        return $this->successResponse($data, 'Executive metrics retrieved successfully');
    }

    /**
     * GET /api/v1/crm/analytics/leads
     */
    public function leads(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $filters = $request->only(['from_date', 'to_date', 'employee_id']);

        $data = $this->leadService->getLeadMetrics($tenantId, $filters);

        return $this->successResponse($data, 'Lead analytics retrieved successfully');
    }

    /**
     * GET /api/v1/crm/analytics/sales
     */
    public function sales(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $filters = $request->only(['from_date', 'to_date', 'employee_id']);

        $data = $this->salesService->getSalesMetrics($tenantId, $filters);

        return $this->successResponse($data, 'Sales analytics retrieved successfully');
    }

    /**
     * GET /api/v1/crm/analytics/attribution
     */
    public function attribution(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $filters = $request->only(['from_date', 'to_date']);

        $data = $this->attributionService->compareAttributionModels($tenantId, $filters);

        return $this->successResponse($data, 'Attribution model comparison retrieved successfully');
    }

    /**
     * POST /api/v1/crm/analytics/attribution/touchpoint
     */
    public function recordTouchpoint(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'lead_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'touchpoint_type' => 'required|string|max:50',
            'channel' => 'required|string|max:50',
            'campaign_id' => 'nullable|integer',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'landing_page_id' => 'nullable|integer',
            'form_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        $touchpoint = $this->attributionService->recordTouchpoint($tenantId, $validated);

        return $this->successResponse($touchpoint, 'Touchpoint recorded successfully', 201);
    }

    /**
     * GET /api/v1/crm/analytics/export
     */
    public function export(Request $request)
    {
        $tenantId = $this->getTenantId();
        $reportType = $request->get('report_type', 'executive_summary');
        $filters = $request->only(['from_date', 'to_date']);
        $columns = $request->get('columns') ? explode(',', $request->get('columns')) : [];

        $result = $this->exportService->exportReport($tenantId, $reportType, $filters, $columns);

        if ($request->get('format') === 'csv') {
            return response($result['csv_content'], 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$result['filename']}\"",
            ]);
        }

        return $this->successResponse($result, 'Report generated successfully');
    }

    /**
     * GET /api/v1/crm/analytics/saved-reports
     */
    public function savedReports(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $reports = SavedReport::where('tenant_id', $tenantId)->orderBy('created_at', 'desc')->get();

        return $this->successResponse($reports, 'Saved reports retrieved successfully');
    }

    /**
     * POST /api/v1/crm/analytics/saved-reports
     */
    public function saveReport(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'report_type' => 'required|string|max:50',
            'filters' => 'nullable|array',
            'selected_columns' => 'nullable|array',
            'chart_type' => 'nullable|string|max:50',
            'is_scheduled' => 'nullable|boolean',
            'schedule_frequency' => 'nullable|string|in:daily,weekly,monthly',
            'schedule_recipients' => 'nullable|array',
        ]);

        $report = SavedReport::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'user_id' => auth()->id() ?? 1,
        ]));

        return $this->successResponse($report, 'Report saved successfully', 201);
    }

    /**
     * DELETE /api/v1/crm/analytics/saved-reports/{id}
     */
    public function deleteSavedReport(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $report = SavedReport::where('tenant_id', $tenantId)->findOrFail($id);
        $report->delete();

        return $this->successResponse(null, 'Saved report deleted successfully');
    }
}
