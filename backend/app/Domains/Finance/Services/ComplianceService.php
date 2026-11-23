<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\ComplianceDocument;
use App\Domains\Finance\Models\ComplianceReminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ComplianceService
{
    /**
     * Get or seed default statutory Indian GST & Tax compliance calendar events.
     */
    public function getComplianceReminders(int $tenantId, ?string $type = null, ?string $status = null): Collection
    {
        $count = ComplianceReminder::where('tenant_id', $tenantId)->count();
        if ($count === 0) {
            $this->seedDefaultStatutoryReminders($tenantId);
        }

        $query = ComplianceReminder::where('tenant_id', $tenantId)->orderBy('due_date', 'asc');

        if ($type) {
            $query->where('compliance_type', $type);
        }
        if ($status) {
            $query->where('status', $status);
        }

        // Auto-mark overdue
        $reminders = $query->get();
        $now = now()->toDateString();
        foreach ($reminders as $r) {
            if ($r->status === 'pending' && $r->due_date->format('Y-m-d') < $now) {
                $r->status = 'overdue';
                $r->escalation_level = max(1, $r->escalation_level);
                $r->save();
            }
        }

        return $reminders;
    }

    /**
     * Seed statutory deadlines per Indian tax framework.
     */
    public function seedDefaultStatutoryReminders(int $tenantId): void
    {
        $currentMonth = now()->format('F Y');
        $nextMonth = now()->addMonth()->format('F Y');

        $statutory = [
            [
                'title' => 'TDS Monthly Tax Payment',
                'compliance_type' => 'tds_payment',
                'due_date' => now()->startOfMonth()->addDays(6)->toDateString(), // 7th of month
                'financial_period' => $currentMonth,
                'frequency' => 'monthly',
                'priority' => 'high',
                'responsible_accountant_name' => 'Finance Controller',
                'notes' => 'Challan ITNS 281 deposit for section 194C, 194J, 194I deductions.',
            ],
            [
                'title' => 'GSTR-1 Monthly Outward Supplies Return',
                'compliance_type' => 'gstr1',
                'due_date' => now()->startOfMonth()->addDays(10)->toDateString(), // 11th of month
                'financial_period' => $currentMonth,
                'frequency' => 'monthly',
                'priority' => 'critical',
                'responsible_accountant_name' => 'Tax Manager',
                'notes' => 'File sales register, B2B invoices and credit/debit notes on GST portal.',
            ],
            [
                'title' => 'GSTR-3B Monthly Summary & Net Tax Payment',
                'compliance_type' => 'gstr3b',
                'due_date' => now()->startOfMonth()->addDays(19)->toDateString(), // 20th of month
                'financial_period' => $currentMonth,
                'frequency' => 'monthly',
                'priority' => 'critical',
                'responsible_accountant_name' => 'Finance Director',
                'notes' => 'Reconcile ITC with GSTR-2B, offset output tax liability and pay net cash ledger dues.',
            ],
            [
                'title' => 'Advance Tax Installment (Q3)',
                'compliance_type' => 'advance_tax',
                'due_date' => '2026-12-15',
                'financial_period' => 'FY 2026-27 Q3',
                'frequency' => 'quarterly',
                'priority' => 'high',
                'responsible_accountant_name' => 'Chief Financial Officer',
                'notes' => 'Deposit minimum 75% cumulative estimated corporate income tax liability.',
            ],
            [
                'title' => 'Annual GST Return (GSTR-9 & 9C)',
                'compliance_type' => 'gstr9',
                'due_date' => '2026-12-31',
                'financial_period' => 'FY 2025-26',
                'frequency' => 'annual',
                'priority' => 'critical',
                'responsible_accountant_name' => 'Auditor & Tax Partner',
                'notes' => 'Complete audited reconciliation statement and annual outward/inward return.',
            ],
        ];

        foreach ($statutory as $item) {
            ComplianceReminder::create(array_merge($item, [
                'tenant_id' => $tenantId,
                'status' => 'pending',
                'escalation_level' => 0,
            ]));
        }
    }

    /**
     * Create custom compliance task or reminder.
     */
    public function createReminder(int $tenantId, array $data, ?User $user = null): ComplianceReminder
    {
        return ComplianceReminder::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'compliance_type' => $data['compliance_type'] ?? 'license_renewal',
            'due_date' => $data['due_date'],
            'financial_period' => $data['financial_period'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'responsible_accountant_name' => $data['responsible_accountant_name'] ?? null,
            'frequency' => $data['frequency'] ?? 'one_time',
            'status' => 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'notes' => $data['notes'] ?? null,
            'escalation_level' => 0,
        ]);
    }

    /**
     * Complete a compliance task.
     */
    public function completeReminder(int $tenantId, int $reminderId, ?User $user = null): ComplianceReminder
    {
        $reminder = ComplianceReminder::where('tenant_id', $tenantId)->findOrFail($reminderId);
        $reminder->status = 'completed';
        $reminder->completed_at = now();
        $reminder->save();

        return $reminder;
    }

    /**
     * List compliance vault documents.
     */
    public function getDocuments(int $tenantId, ?string $type = null): Collection
    {
        $count = ComplianceDocument::where('tenant_id', $tenantId)->count();
        if ($count === 0) {
            $this->seedDefaultVaultDocuments($tenantId);
        }

        $query = ComplianceDocument::where('tenant_id', $tenantId)->orderBy('created_at', 'desc');
        if ($type) {
            $query->where('document_type', $type);
        }

        $docs = $query->get();
        $now = now()->toDateString();
        foreach ($docs as $d) {
            if ($d->expiry_date && $d->expiry_date->format('Y-m-d') < $now) {
                $d->is_expired = true;
                $d->save();
            }
        }

        return $docs;
    }

    /**
     * Seed initial sample corporate tax credentials in vault.
     */
    private function seedDefaultVaultDocuments(int $tenantId): void
    {
        $defaults = [
            [
                'title' => 'Official GST Registration Certificate (REG-06)',
                'document_type' => 'gst_certificate',
                'document_number' => '27AABCA1234D1Z5',
                'file_name' => 'GST_REG06_27AABCA1234D1Z5.pdf',
                'file_path' => '/storage/compliance/GST_REG06.pdf',
                'file_size' => 345000,
                'mime_type' => 'application/pdf',
                'issue_date' => '2017-07-01',
                'expiry_date' => null,
                'version' => '1.0',
                'tags' => ['GST', 'REG-06', 'Maharashtra'],
            ],
            [
                'title' => 'Corporate PAN Card Record',
                'document_type' => 'pan_card',
                'document_number' => 'AABCA1234D',
                'file_name' => 'PAN_AABCA1234D.pdf',
                'file_path' => '/storage/compliance/PAN_CARD.pdf',
                'file_size' => 120000,
                'mime_type' => 'application/pdf',
                'issue_date' => '2015-04-12',
                'expiry_date' => null,
                'version' => '1.0',
                'tags' => ['IncomeTax', 'PAN'],
            ],
            [
                'title' => 'Municipal Trade License 2026-27',
                'document_type' => 'trade_license',
                'document_number' => 'MCGM-TL-2026-8891',
                'file_name' => 'Trade_License_2026_27.pdf',
                'file_path' => '/storage/compliance/Trade_License.pdf',
                'file_size' => 512000,
                'issue_date' => '2026-04-01',
                'expiry_date' => '2027-03-31',
                'version' => '2.0',
                'tags' => ['Municipal', 'ShopAct', 'Annual'],
            ],
        ];

        foreach ($defaults as $doc) {
            ComplianceDocument::create(array_merge($doc, ['tenant_id' => $tenantId]));
        }
    }

    /**
     * Store uploaded compliance document.
     */
    public function storeDocument(int $tenantId, array $data, ?User $user = null): ComplianceDocument
    {
        return ComplianceDocument::create([
            'tenant_id' => $tenantId,
            'title' => $data['title'],
            'document_type' => $data['document_type'] ?? 'contract',
            'document_number' => $data['document_number'] ?? null,
            'file_name' => $data['file_name'] ?? 'document.pdf',
            'file_path' => $data['file_path'] ?? '/storage/compliance/' . uniqid() . '.pdf',
            'file_size' => $data['file_size'] ?? 1024,
            'mime_type' => $data['mime_type'] ?? 'application/pdf',
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'is_expired' => !empty($data['expiry_date']) && ($data['expiry_date'] < now()->toDateString()),
            'version' => $data['version'] ?? '1.0',
            'tags' => $data['tags'] ?? [],
            'uploaded_by_user_id' => $user?->id,
            'access_restricted_roles' => $data['access_restricted_roles'] ?? null,
        ]);
    }
}
