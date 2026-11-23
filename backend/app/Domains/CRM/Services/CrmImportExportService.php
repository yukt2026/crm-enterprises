<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Company;
use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Deal;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Tenancy\Services\TenantContext;

class CrmImportExportService
{
    public function __construct(
        protected DuplicateDetectionService $duplicateService,
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Export entity records to CSV format
     */
    public function exportCsv(string $entity, array $filters = []): string
    {
        $tenantId = TenantContext::getTenantId() ?? 1;
        $output = fopen('php://temp', 'r+');

        switch ($entity) {
            case 'leads':
                fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Designation', 'Status', 'Estimated Value', 'Score', 'Qualification', 'Source', 'Created At']);
                $records = Lead::where('tenant_id', $tenantId)->active()->get();
                foreach ($records as $r) {
                    fputcsv($output, [
                        $r->id, $r->name, $r->email, $r->phone, $r->company_name, $r->designation,
                        $r->status, $r->estimated_value, $r->score, $r->qualification_status, $r->source,
                        $r->created_at?->toIso8601String(),
                    ]);
                }
                break;

            case 'contacts':
                fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Job Title', 'Company ID', 'Address', 'City', 'Consent Status', 'Created At']);
                $records = Contact::where('tenant_id', $tenantId)->get();
                foreach ($records as $r) {
                    fputcsv($output, [
                        $r->id, $r->name, $r->email, $r->phone, $r->job_title, $r->company_id,
                        $r->address, $r->city, $r->consent_status, $r->created_at?->toIso8601String(),
                    ]);
                }
                break;

            case 'companies':
                fputcsv($output, ['ID', 'Name', 'Legal Name', 'Industry', 'Website', 'Phone', 'Address', 'City', 'GSTIN', 'Status', 'Created At']);
                $records = Company::where('tenant_id', $tenantId)->get();
                foreach ($records as $r) {
                    fputcsv($output, [
                        $r->id, $r->name, $r->legal_name, $r->industry, $r->website, $r->phone,
                        $r->address, $r->billing_city, $r->gstin_tax_id, $r->account_status, $r->created_at?->toIso8601String(),
                    ]);
                }
                break;

            case 'deals':
                fputcsv($output, ['ID', 'Name', 'Value', 'Stage', 'Probability', 'Close Date', 'Company ID', 'Contact ID', 'Created At']);
                $records = Deal::where('tenant_id', $tenantId)->get();
                foreach ($records as $r) {
                    fputcsv($output, [
                        $r->id, $r->name, $r->value, $r->stage, $r->probability, $r->close_date,
                        $r->company_id, $r->contact_id, $r->created_at?->toIso8601String(),
                    ]);
                }
                break;

            default:
                fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Status', 'Created At']);
                $records = Customer::where('tenant_id', $tenantId)->get();
                foreach ($records as $r) {
                    fputcsv($output, [
                        $r->id, $r->name, $r->email, $r->phone, $r->status, $r->created_at?->toIso8601String(),
                    ]);
                }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Import records from CSV content with field mapping and duplicate checking
     */
    public function importLeadsCsv(string $csvContent, array $columnMapping = []): array
    {
        $tenantId = TenantContext::getTenantId() ?? 1;
        $lines = explode("\n", trim($csvContent));
        if (count($lines) < 2) {
            return ['imported_count' => 0, 'skipped_count' => 0, 'errors' => ['CSV contains no data rows.']];
        }

        $headers = str_getcsv(array_shift($lines));
        $headerMap = [];
        foreach ($headers as $idx => $header) {
            $normalized = strtolower(trim(str_replace([' ', '_', '-'], '', $header)));
            $headerMap[$normalized] = $idx;
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($lines as $lineNum => $line) {
            if (!trim($line)) continue;
            $row = str_getcsv($line);

            $name = $this->getValue($row, $headerMap, ['name', 'leadname', 'fullname']);
            $email = $this->getValue($row, $headerMap, ['email', 'emailaddress']);
            $phone = $this->getValue($row, $headerMap, ['phone', 'phonenumber', 'mobile']);
            $company = $this->getValue($row, $headerMap, ['company', 'companyname', 'organization']);
            $value = (float) $this->getValue($row, $headerMap, ['value', 'estimatedvalue', 'budget', 'amount']) ?: 5000;
            $source = $this->getValue($row, $headerMap, ['source', 'channel']) ?: 'CSV Import';

            if (empty($name) && empty($email)) {
                $skipped++;
                continue;
            }

            // Check duplicate
            $dups = $this->duplicateService->checkDuplicates([
                'email' => $email,
                'phone' => $phone,
                'company_name' => $company,
            ]);

            if (!empty($dups)) {
                $skipped++;
                $errors[] = "Row #" . ($lineNum + 2) . " ({$name}) matches existing duplicate ({$dups[0]['name']}). Skipped.";
                continue;
            }

            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'name' => $name ?: 'Imported Lead',
                'email' => $email,
                'phone' => $phone,
                'company_name' => $company,
                'estimated_value' => $value,
                'status' => 'new',
                'source' => $source,
            ]);

            $this->scoringService->updateLeadScore($lead);
            $imported++;
        }

        return [
            'imported_count' => $imported,
            'skipped_count' => $skipped,
            'errors' => $errors,
        ];
    }

    private function getValue(array $row, array $headerMap, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($headerMap[$k]) && isset($row[$headerMap[$k]])) {
                return trim($row[$headerMap[$k]]);
            }
        }
        return null;
    }
}
