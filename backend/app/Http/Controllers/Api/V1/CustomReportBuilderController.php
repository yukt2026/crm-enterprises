<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Analytics\Models\SavedReport;
use App\Domains\Analytics\Services\CustomReportBuilderService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

class CustomReportBuilderController extends Controller
{
    public function __construct(
        protected CustomReportBuilderService $reportService
    ) {}

    /**
     * Get Module Catalog with Fields, Relationships & Aggregations
     */
    public function getCatalog(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->getModuleCatalog(),
        ]);
    }

    /**
     * Live Preview of Custom Report Query
     */
    public function previewReport(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        if ($request->has('fields') && !$request->has('selected_columns')) {
            $request->merge(['selected_columns' => $request->input('fields')]);
        }

        $validated = $request->validate([
            'module' => 'required|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'filters' => 'nullable|array',
            'selected_columns' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'group_by' => 'nullable|string',
            'sort_by' => 'nullable|string',
            'sort_direction' => 'nullable|in:asc,desc',
            'chart_type' => 'nullable|string',
            'relations' => 'nullable|array',
            'limit' => 'nullable|integer|max:100',
        ]);

        $result = $this->reportService->executeQuery($tenantId, $validated, (int) ($validated['limit'] ?? 50));
        $result['total_count'] = $result['total_records'] ?? 0;

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Save Custom Report Definition
     */
    public function saveReport(Request $request): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        $userId = $request->user()->id;

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'module' => 'required|string',
            'report_type' => 'nullable|string',
            'group_by' => 'nullable|string',
            'sort_by' => 'nullable|string',
            'sort_direction' => 'nullable|in:asc,desc',
            'chart_type' => 'nullable|string',
            'filters' => 'nullable|array',
            'selected_columns' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'is_scheduled' => 'boolean',
            'schedule_frequency' => 'nullable|in:daily,weekly,monthly',
            'schedule_recipients' => 'nullable|array',
        ]);

        $report = SavedReport::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'report_type' => $validated['report_type'] ?? 'custom',
            'module' => $validated['module'],
            'group_by' => $validated['group_by'] ?? null,
            'sort_by' => $validated['sort_by'] ?? 'id',
            'sort_direction' => $validated['sort_direction'] ?? 'desc',
            'chart_type' => $validated['chart_type'] ?? 'table',
            'filters' => $validated['filters'] ?? null,
            'selected_columns' => $validated['selected_columns'] ?? null,
            'aggregations' => $validated['aggregations'] ?? null,
            'is_scheduled' => $validated['is_scheduled'] ?? false,
            'schedule_frequency' => $validated['schedule_frequency'] ?? null,
            'schedule_recipients' => $validated['schedule_recipients'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Custom report definition saved successfully.',
        ], 201);
    }

    /**
     * Export Report to CSV / Excel
     */
    public function exportReport(Request $request): HttpResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $validated = $request->validate([
            'module' => 'required|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'filters' => 'nullable|array',
            'selected_columns' => 'nullable|array',
            'aggregations' => 'nullable|array',
            'group_by' => 'nullable|string',
            'sort_by' => 'nullable|string',
            'sort_direction' => 'nullable|in:asc,desc',
            'format' => 'nullable|in:csv,excel,pdf',
        ]);

        $csv = $this->reportService->exportCsv($tenantId, $validated);
        $filename = "report_{$validated['module']}_" . Carbon::now()->format('Ymd_His') . ".csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Schedule Report Delivery Configuration
     */
    public function scheduleReport(Request $request, int $id): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);

        $report = SavedReport::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'is_scheduled' => 'required|boolean',
            'schedule_frequency' => 'required_if:is_scheduled,true|in:daily,weekly,monthly',
            'schedule_recipients' => 'required_if:is_scheduled,true|array',
        ]);

        $report->update($validated);

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Report schedule updated successfully.',
        ]);
    }
}
