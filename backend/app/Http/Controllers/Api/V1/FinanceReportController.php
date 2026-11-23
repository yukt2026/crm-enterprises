<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Finance\Services\AdvancedFinanceReportService;
use App\Domains\Finance\Services\InvoiceBillingService;
use App\Domains\Tenancy\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TenantContext $tenantContext,
        protected InvoiceBillingService $invoiceBillingService,
        protected AdvancedFinanceReportService $advancedFinanceReportService
    ) {}

    protected function getTenantId(): int
    {
        return $this->tenantContext->getTenantId() ?? 1;
    }

    /**
     * Get overall financial summary metrics (backward compatible + enriched).
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $summary = $this->invoiceBillingService->getFinanceSummary($tenantId);

        return $this->successResponse($summary, 'Finance summary retrieved.');
    }

    /**
     * Get complete comprehensive financial report.
     * Enforces strict distinction between Cash Collected, Invoiced Revenue, and Forecast Pipeline.
     */
    public function comprehensive(Request $request): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $report = $this->advancedFinanceReportService->getComprehensiveReport($tenantId);

        return $this->successResponse($report, 'Comprehensive financial report retrieved.');
    }
}
