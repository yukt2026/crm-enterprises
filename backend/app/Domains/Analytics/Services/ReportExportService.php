<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\SavedReport;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\MarketingCampaign;
use App\Domains\Support\Models\SupportTicket;
use Carbon\Carbon;

class ReportExportService
{
    /**
     * Generate export data and CSV string based on report type, filters, and column definitions
     */
    public function exportReport(int $tenantId, string $reportType, array $filters = [], array $selectedColumns = []): array
    {
        $fromDate = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $toDate = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'])->endOfDay() : Carbon::now()->endOfDay();

        $rows = [];
        $headers = [];

        switch ($reportType) {
            case 'leads':
                $headers = ['ID', 'Name', 'Email', 'Phone', 'Source', 'Status', 'Score', 'Created At'];
                $leads = Lead::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->get();

                foreach ($leads as $lead) {
                    $rows[] = [
                        $lead->id,
                        $lead->name,
                        $lead->email,
                        $lead->phone,
                        $lead->source,
                        $lead->status,
                        $lead->score,
                        $lead->created_at ? $lead->created_at->toDateTimeString() : '',
                    ];
                }
                break;

            case 'deals':
                $headers = ['ID', 'Deal Name', 'Value', 'Currency', 'Stage', 'Probability', 'Source', 'Close Date', 'Won At'];
                $deals = Deal::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->get();

                foreach ($deals as $deal) {
                    $rows[] = [
                        $deal->id,
                        $deal->name,
                        $deal->value,
                        $deal->currency ?? 'USD',
                        $deal->stage,
                        $deal->probability,
                        $deal->source,
                        $deal->close_date ? $deal->close_date->toDateString() : '',
                        $deal->won_at ? Carbon::parse($deal->won_at)->toDateTimeString() : '',
                    ];
                }
                break;

            case 'campaigns':
                $headers = ['ID', 'Campaign Name', 'Channel', 'Status', 'Spend Amount', 'Spend Type', 'CPL', 'ROAS', 'Created At'];
                $campaigns = MarketingCampaign::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->get();

                foreach ($campaigns as $camp) {
                    $rows[] = [
                        $camp->id,
                        $camp->name,
                        $camp->channel,
                        $camp->status,
                        $camp->spend_amount,
                        $camp->spend_type ?? 'manual_estimate',
                        $camp->cpl,
                        $camp->roas,
                        $camp->created_at ? $camp->created_at->toDateTimeString() : '',
                    ];
                }
                break;

            case 'tickets':
                $headers = ['ID', 'Ticket #', 'Subject', 'Priority', 'Status', 'SLA Status', 'Created At'];
                $tickets = SupportTicket::where('tenant_id', $tenantId)
                    ->whereBetween('created_at', [$fromDate, $toDate])
                    ->get();

                foreach ($tickets as $ticket) {
                    $rows[] = [
                        $ticket->id,
                        $ticket->ticket_number,
                        $ticket->subject,
                        $ticket->priority,
                        $ticket->status,
                        $ticket->sla_status,
                        $ticket->created_at ? $ticket->created_at->toDateTimeString() : '',
                    ];
                }
                break;

            case 'attribution':
            default:
                $headers = ['Channel', 'First-Touch Revenue', 'Last-Touch Revenue', 'Linear Revenue', 'U-Shaped Revenue'];
                $attributionSvc = new AttributionService();
                $attr = $attributionSvc->compareAttributionModels($tenantId, $filters);
                foreach ($attr['attribution_model_comparison'] as $item) {
                    $rows[] = [
                        $item['channel'],
                        $item['first_touch_revenue'],
                        $item['last_touch_revenue'],
                        $item['linear_revenue'],
                        $item['u_shaped_revenue'],
                    ];
                }
                break;
        }

        // Filter columns if selectedColumns specified and not empty
        if (!empty($selectedColumns)) {
            $colIndices = [];
            $filteredHeaders = [];
            foreach ($selectedColumns as $col) {
                $idx = array_search(strtolower($col), array_map('strtolower', $headers));
                if ($idx !== false) {
                    $colIndices[] = $idx;
                    $filteredHeaders[] = $headers[$idx];
                }
            }

            if (!empty($filteredHeaders)) {
                $headers = $filteredHeaders;
                $filteredRows = [];
                foreach ($rows as $row) {
                    $newRow = [];
                    foreach ($colIndices as $idx) {
                        $newRow[] = $row[$idx] ?? '';
                    }
                    $filteredRows[] = $newRow;
                }
                $rows = $filteredRows;
            }
        }

        // Generate CSV output
        $csvHandle = fopen('php://temp', 'r+');
        fputcsv($csvHandle, $headers);
        foreach ($rows as $row) {
            fputcsv($csvHandle, $row);
        }
        rewind($csvHandle);
        $csvContent = stream_get_contents($csvHandle);
        fclose($csvHandle);

        return [
            'report_type' => $reportType,
            'headers' => $headers,
            'rows_count' => count($rows),
            'rows' => $rows,
            'csv_content' => $csvContent,
            'filename' => "report_{$reportType}_" . Carbon::now()->format('Ymd_His') . ".csv",
        ];
    }
}
