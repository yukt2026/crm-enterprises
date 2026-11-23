<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\DuplicateMergeLog;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Media\Models\MediaFile;
use App\Domains\Tenancy\Services\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DuplicateDetectionService
{
    public function __construct(
        protected LeadScoringService $scoringService
    ) {}

    /**
     * Normalize a phone number to bare digits
     */
    public function normalizePhone(?string $phone): string
    {
        if (!$phone) return '';
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Normalize an email address
     */
    public function normalizeEmail(?string $email): string
    {
        if (!$email) return '';
        return strtolower(trim($email));
    }

    /**
     * Check for potential duplicates against existing Leads, Contacts, and Companies
     */
    public function checkDuplicates(array $params, ?int $ignoreId = null): array
    {
        $tenantId = TenantContext::getTenantId() ?? 1;
        $duplicates = [];

        $normEmail = $this->normalizeEmail($params['email'] ?? null);
        $normPhone = $this->normalizePhone($params['phone'] ?? null);
        $companyName = trim($params['company_name'] ?? $params['company'] ?? '');

        // 1. Search Leads
        $leadQuery = Lead::where('tenant_id', $tenantId)->where('is_archived', false);
        if ($ignoreId) {
            $leadQuery->where('id', '!=', $ignoreId);
        }

        $leadMatches = $leadQuery->where(function ($q) use ($normEmail, $normPhone, $companyName) {
            $matched = false;
            if ($normEmail) {
                $q->where('email', $normEmail);
                $matched = true;
            }
            if ($normPhone && strlen($normPhone) >= 7) {
                if ($matched) {
                    $q->orWhere('phone', 'like', "%{$normPhone}%");
                } else {
                    $q->where('phone', 'like', "%{$normPhone}%");
                    $matched = true;
                }
            }
            if (!empty($companyName) && strlen($companyName) >= 3) {
                if ($matched) {
                    $q->orWhere('company_name', 'like', "%{$companyName}%");
                } else {
                    $q->where('company_name', 'like', "%{$companyName}%");
                }
            }
        })->limit(5)->get();

        foreach ($leadMatches as $l) {
            $reasons = [];
            $confidence = 'medium';
            if ($normEmail && $this->normalizeEmail($l->email) === $normEmail) {
                $reasons[] = 'Exact Email Match';
                $confidence = 'exact';
            }
            if ($normPhone && str_contains($this->normalizePhone($l->phone), $normPhone)) {
                $reasons[] = 'Phone Number Match';
                if ($confidence !== 'exact') $confidence = 'high';
            }
            if (!empty($companyName) && strtolower($l->company_name) === strtolower($companyName)) {
                $reasons[] = 'Company Name Match';
            }

            $duplicates[] = [
                'entity_type' => 'lead',
                'id' => $l->id,
                'name' => $l->name,
                'company' => $l->company_name,
                'email' => $l->email,
                'phone' => $l->phone,
                'status' => $l->status,
                'confidence' => $confidence,
                'match_reasons' => $reasons,
                'created_at' => $l->created_at?->toIso8601String(),
            ];
        }

        return $duplicates;
    }

    /**
     * Merge duplicate lead into master lead with controlled field overrides
     */
    public function mergeLeads(Lead $master, Lead $duplicate, array $fieldOverrides = [], ?int $userId = null): Lead
    {
        $tenantId = $master->tenant_id ?? TenantContext::getTenantId() ?? 1;

        DB::transaction(function () use ($master, $duplicate, $fieldOverrides, $tenantId, $userId) {
            $duplicateData = $duplicate->toArray();

            // 1. Apply selected field overrides onto master
            $allowedFields = [
                'name', 'email', 'phone', 'alternate_phone', 'company_name',
                'designation', 'address', 'city', 'state', 'country', 'postal_code',
                'requirement', 'product_interest', 'estimated_value', 'expected_purchase_date',
                'status', 'priority', 'source', 'assigned_user_id',
            ];

            foreach ($fieldOverrides as $field => $val) {
                if (in_array($field, $allowedFields) && !empty($val)) {
                    $master->{$field} = $val;
                }
            }

            // 2. Merge Tags
            $masterTags = $master->tags ?? [];
            $dupTags = $duplicate->tags ?? [];
            $mergedTags = array_values(array_unique(array_merge($masterTags, $dupTags)));
            $master->tags = $mergedTags;

            // 3. Append Notes
            if (!empty($duplicate->notes)) {
                $master->notes = trim(($master->notes ? $master->notes . "\n\n" : "") .
                    "[Merged from Lead #{$duplicate->id}]: " . $duplicate->notes);
            }

            $master->save();

            // 4. Migrate Activities to Master
            CrmActivity::where('lead_id', $duplicate->id)
                ->update(['lead_id' => $master->id]);

            // 5. Migrate Media Attachments
            MediaFile::where('fileable_type', Lead::class)
                ->where('fileable_id', $duplicate->id)
                ->update(['fileable_id' => $master->id]);

            // 6. Record Duplicate Merge Audit Log
            DuplicateMergeLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'entity_type' => 'lead',
                'master_record_id' => $master->id,
                'merged_record_id' => $duplicate->id,
                'preserved_fields' => $fieldOverrides,
                'merged_data' => $duplicateData,
                'created_at' => now(),
            ]);

            // 7. Create Activity Log on Master
            CrmActivity::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'lead_id' => $master->id,
                'activity_type' => 'audit',
                'title' => "Duplicate Lead Merged (#{$duplicate->id})",
                'description' => "Merged records and consolidated activities from duplicate lead {$duplicate->name} ({$duplicate->email}).",
                'metadata' => [
                    'duplicate_lead_id' => $duplicate->id,
                    'duplicate_email' => $duplicate->email,
                    'overrides_applied' => $fieldOverrides,
                ],
            ]);

            // 8. Soft Delete Duplicate Lead
            $duplicate->delete();

            // 9. Recalculate score on master
            $this->scoringService->updateLeadScore($master);
        });

        return $master->fresh(['company', 'assignedUser', 'activities']);
    }
}
