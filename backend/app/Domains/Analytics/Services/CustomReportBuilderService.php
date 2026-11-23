<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\FieldSalesVisit;
use App\Domains\Analytics\Models\SavedReport;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Projects\Models\Project;
use App\Domains\Support\Models\SupportTicket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;

class CustomReportBuilderService
{
    /**
     * Module Catalog with Available Fields, Types & Relationships
     */
    public function getModuleCatalog(): array
    {
        return [
            'leads' => [
                'name' => 'Leads & Enquiries',
                'model' => Lead::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Lead ID', 'type' => 'number'],
                    ['key' => 'name', 'label' => 'Full Name', 'type' => 'string'],
                    ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                    ['key' => 'phone', 'label' => 'Phone', 'type' => 'string'],
                    ['key' => 'source', 'label' => 'Lead Source', 'type' => 'string'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                    ['key' => 'score', 'label' => 'Lead Score', 'type' => 'number'],
                    ['key' => 'created_at', 'label' => 'Created Date', 'type' => 'date'],
                ],
                'relations' => ['assignedUser', 'branch'],
            ],
            'deals' => [
                'name' => 'Deals & Opportunities',
                'model' => Deal::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Deal ID', 'type' => 'number'],
                    ['key' => 'name', 'label' => 'Deal Title', 'type' => 'string'],
                    ['key' => 'value', 'label' => 'Deal Value', 'type' => 'number', 'is_currency' => true],
                    ['key' => 'stage', 'label' => 'Pipeline Stage', 'type' => 'string'],
                    ['key' => 'probability', 'label' => 'Probability %', 'type' => 'number'],
                    ['key' => 'close_date', 'label' => 'Expected Close Date', 'type' => 'date'],
                    ['key' => 'lost_reason', 'label' => 'Lost Reason', 'type' => 'string'],
                    ['key' => 'created_at', 'label' => 'Created Date', 'type' => 'date'],
                ],
                'relations' => ['customer', 'assignedUser', 'branch'],
            ],
            'invoices' => [
                'name' => 'Invoices & Billing',
                'model' => Invoice::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Invoice ID', 'type' => 'number'],
                    ['key' => 'invoice_number', 'label' => 'Invoice #', 'type' => 'string'],
                    ['key' => 'total_amount', 'label' => 'Total Amount', 'type' => 'number', 'is_currency' => true],
                    ['key' => 'balance_due', 'label' => 'Balance Due', 'type' => 'number', 'is_currency' => true],
                    ['key' => 'status', 'label' => 'Payment Status', 'type' => 'string'],
                    ['key' => 'due_date', 'label' => 'Due Date', 'type' => 'date'],
                    ['key' => 'created_at', 'label' => 'Invoice Date', 'type' => 'date'],
                ],
                'relations' => ['customer', 'branch'],
            ],
            'customers' => [
                'name' => 'Customers',
                'model' => Customer::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Customer ID', 'type' => 'number'],
                    ['key' => 'name', 'label' => 'Customer Name', 'type' => 'string'],
                    ['key' => 'email', 'label' => 'Email', 'type' => 'string'],
                    ['key' => 'phone', 'label' => 'Phone', 'type' => 'string'],
                    ['key' => 'city', 'label' => 'City', 'type' => 'string'],
                    ['key' => 'state', 'label' => 'State', 'type' => 'string'],
                    ['key' => 'created_at', 'label' => 'Joined Date', 'type' => 'date'],
                ],
                'relations' => ['branch'],
            ],
            'tickets' => [
                'name' => 'Support Tickets',
                'model' => SupportTicket::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Ticket ID', 'type' => 'number'],
                    ['key' => 'ticket_number', 'label' => 'Ticket #', 'type' => 'string'],
                    ['key' => 'subject', 'label' => 'Subject', 'type' => 'string'],
                    ['key' => 'priority', 'label' => 'Priority', 'type' => 'string'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                    ['key' => 'sla_status', 'label' => 'SLA Status', 'type' => 'string'],
                    ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
                ],
                'relations' => ['customer', 'assignedEmployee'],
            ],
            'projects' => [
                'name' => 'Projects',
                'model' => Project::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Project ID', 'type' => 'number'],
                    ['key' => 'name', 'label' => 'Project Name', 'type' => 'string'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                    ['key' => 'budget', 'label' => 'Budget', 'type' => 'number', 'is_currency' => true],
                    ['key' => 'progress', 'label' => 'Progress %', 'type' => 'number'],
                    ['key' => 'created_at', 'label' => 'Created At', 'type' => 'date'],
                ],
                'relations' => ['customer', 'manager'],
            ],
            'field_sales' => [
                'name' => 'Field Sales Visits',
                'model' => FieldSalesVisit::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Visit ID', 'type' => 'number'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'string'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                    ['key' => 'scheduled_at', 'label' => 'Scheduled Date', 'type' => 'date'],
                    ['key' => 'outcome', 'label' => 'Outcome', 'type' => 'string'],
                    ['key' => 'distance_travelled_km', 'label' => 'Distance (KM)', 'type' => 'number'],
                    ['key' => 'travel_expense_amount', 'label' => 'Expense Amount', 'type' => 'number', 'is_currency' => true],
                ],
                'relations' => ['employee', 'customer', 'lead', 'branch'],
            ],
            'expenses' => [
                'name' => 'Corporate Expenses',
                'model' => Expense::class,
                'fields' => [
                    ['key' => 'id', 'label' => 'Expense ID', 'type' => 'number'],
                    ['key' => 'category', 'label' => 'Category', 'type' => 'string'],
                    ['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'is_currency' => true],
                    ['key' => 'expense_date', 'label' => 'Expense Date', 'type' => 'date'],
                    ['key' => 'status', 'label' => 'Status', 'type' => 'string'],
                ],
                'relations' => ['user', 'branch'],
            ],
        ];
    }

    /**
     * Execute Report Query dynamically with filtering, grouping & sorting
     */
    public function executeQuery(int $tenantId, array $config, ?int $limit = 50): array
    {
        $module = $config['module'] ?? 'leads';
        $catalog = $this->getModuleCatalog();

        if (!isset($catalog[$module])) {
            throw new \InvalidArgumentException("Invalid report module [{$module}].");
        }

        $modelClass = $catalog[$module]['model'];
        /** @var Builder $query */
        $query = $modelClass::where('tenant_id', $tenantId);

        // Date range filtering
        $dateField = $module === 'expenses' ? 'expense_date' : ($module === 'field_sales' ? 'scheduled_at' : 'created_at');
        if (!empty($config['from_date'])) {
            $query->where($dateField, '>=', Carbon::parse($config['from_date'])->startOfDay());
        }
        if (!empty($config['to_date'])) {
            $query->where($dateField, '<=', Carbon::parse($config['to_date'])->endOfDay());
        }

        // Custom filters
        if (!empty($config['filters']) && is_array($config['filters'])) {
            foreach ($config['filters'] as $f) {
                $field = $f['field'] ?? null;
                $operator = $f['operator'] ?? '=';
                $value = $f['value'] ?? null;

                if (!$field || $value === null) continue;

                if ($operator === 'like') {
                    $query->where($field, 'like', "%{$value}%");
                } elseif ($operator === 'in' && is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $operator, $value);
                }
            }
        }

        // Selected relationships
        $relations = $config['relations'] ?? [];
        if (!empty($relations)) {
            $allowedRelations = $catalog[$module]['relations'];
            $validRelations = array_intersect($relations, $allowedRelations);
            if (!empty($validRelations)) {
                $query->with($validRelations);
            }
        }

        // Sorting
        $sortBy = $config['sort_by'] ?? 'id';
        $sortDirection = strtolower($config['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $totalCount = (clone $query)->count();

        if ($limit) {
            $query->take($limit);
        }

        $records = $query->get();

        // Selected columns filter
        $columns = $config['selected_columns'] ?? array_column($catalog[$module]['fields'], 'key');

        $rows = $records->map(function ($record) use ($columns) {
            $row = [];
            foreach ($columns as $col) {
                $row[$col] = $record->{$col} ?? null;
            }
            return $row;
        });

        // Aggregations (e.g., sum, avg, count)
        $aggregations = [];
        if (!empty($config['aggregations']) && is_array($config['aggregations'])) {
            foreach ($config['aggregations'] as $aggType => $fields) {
                foreach ((array) $fields as $f) {
                    if ($aggType === 'sum') {
                        $aggregations["sum_{$f}"] = (float) $records->sum($f);
                    } elseif ($aggType === 'avg') {
                        $aggregations["avg_{$f}"] = (float) round($records->avg($f) ?? 0, 2);
                    } elseif ($aggType === 'count') {
                        $aggregations["count_{$f}"] = $records->count();
                    }
                }
            }
        }

        // Grouping
        $groupBy = $config['group_by'] ?? null;
        $groupedData = [];
        if ($groupBy) {
            $groupedData = $records->groupBy($groupBy)->map(function ($items, $key) {
                return [
                    'group_key' => $key,
                    'count' => $items->count(),
                    'total_value' => (float) $items->sum('value') ?: (float) $items->sum('total_amount') ?: (float) $items->sum('amount') ?: 0,
                ];
            })->values();
        }

        return [
            'module' => $module,
            'module_name' => $catalog[$module]['name'],
            'total_records' => $totalCount,
            'columns' => $columns,
            'rows' => $rows,
            'aggregations' => $aggregations,
            'grouped' => $groupedData,
            'chart_type' => $config['chart_type'] ?? 'table',
        ];
    }

    /**
     * Export Report to CSV / Excel
     */
    public function exportCsv(int $tenantId, array $config): string
    {
        $result = $this->executeQuery($tenantId, $config, null); // no limit
        $columns = $result['columns'];
        $rows = $result['rows'];

        $fp = fopen('php://temp', 'r+');
        // Header
        fputcsv($fp, $columns);

        // Data rows
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $col) {
                $val = $row[$col] ?? '';
                if ($val instanceof \DateTimeInterface) {
                    $val = $val->format('Y-m-d H:i:s');
                }
                $line[] = is_array($val) ? json_encode($val) : (string) $val;
            }
            fputcsv($fp, $line);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }
}
