<?php

namespace App\Domains\Marketing\Services;

use App\Domains\CRM\Models\Contact;
use App\Domains\CRM\Models\Customer;
use App\Domains\LeadAcquisition\Models\Lead;
use App\Domains\Marketing\Models\MarketingSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SegmentQueryService
{
    /**
     * Resolve eligible recipients matching a marketing segment.
     */
    public function resolveRecipients(MarketingSegment $segment, ?string $channel = null): Collection
    {
        $tenantId = $segment->tenant_id;
        $targetEntity = $segment->target_entity ?? 'lead';
        $rules = $segment->rules ?? [];
        $inclusionIds = (array) ($segment->inclusion_ids ?? []);
        $exclusionIds = (array) ($segment->exclusion_ids ?? []);

        $query = $this->buildBaseQuery($tenantId, $targetEntity);

        // Apply rules and manual inclusions grouped together
        $query->where(function (Builder $q) use ($segment, $rules, $targetEntity, $inclusionIds) {
            $hasRules = false;
            if ($segment->type === 'dynamic' && !empty($rules)) {
                $this->applyRules($q, $rules, $targetEntity);
                $hasRules = true;
            }

            if (!empty($inclusionIds)) {
                if ($hasRules) {
                    $q->orWhereIn('id', $inclusionIds);
                } else {
                    $q->whereIn('id', $inclusionIds);
                }
            }
        });

        // Apply manual exclusions on top of everything
        if (!empty($exclusionIds)) {
            $query->whereNotIn('id', $exclusionIds);
        }

        $records = $query->get();

        // Map into normalized recipient structures
        return $records->map(function ($record) use ($targetEntity, $channel) {
            $address = null;
            if ($channel === 'email') {
                $address = $record->email;
            } elseif ($channel === 'whatsapp' || $channel === 'sms') {
                $address = $record->phone ?? ($record->mobile ?? null);
            } else {
                $address = $record->phone ?: $record->email;
            }

            return [
                'entity_type' => $targetEntity,
                'entity_id' => $record->id,
                'name' => $record->name,
                'email' => $record->email ?? null,
                'phone' => $record->phone ?? ($record->mobile ?? null),
                'address' => $address,
                'company' => $record->company_name ?? ($record->company?->name ?? null),
                'city' => $record->city ?? null,
                'status' => $record->status ?? null,
                'score' => $record->score ?? 0,
                'tags' => (array) ($record->tags ?? []),
            ];
        })->filter(fn($r) => !empty($r['address']));
    }

    /**
     * Preview count of matching recipients for a hypothetical rule set.
     */
    public function previewCount(
        int $tenantId,
        array $rules,
        array $inclusionIds = [],
        array $exclusionIds = [],
        string $targetEntity = 'lead'
    ): int {
        $query = $this->buildBaseQuery($tenantId, $targetEntity);

        $query->where(function (Builder $q) use ($rules, $targetEntity, $inclusionIds) {
            $hasRules = false;
            if (!empty($rules)) {
                $this->applyRules($q, $rules, $targetEntity);
                $hasRules = true;
            }

            if (!empty($inclusionIds)) {
                if ($hasRules) {
                    $q->orWhereIn('id', $inclusionIds);
                } else {
                    $q->whereIn('id', $inclusionIds);
                }
            }
        });

        if (!empty($exclusionIds)) {
            $query->whereNotIn('id', $exclusionIds);
        }

        return $query->count();
    }

    /**
     * Construct the base Eloquent query for the target entity type.
     */
    protected function buildBaseQuery(int $tenantId, string $targetEntity): Builder
    {
        return match ($targetEntity) {
            'contact' => Contact::where('tenant_id', $tenantId),
            'customer' => Customer::where('tenant_id', $tenantId),
            default => Lead::where('tenant_id', $tenantId),
        };
    }

    /**
     * Apply filter rules (status, score, tags, city, source, lifecycle) to query.
     */
    protected function applyRules(Builder $query, array $rules, string $targetEntity): void
    {
        $logicalOp = strtolower($rules['logical_operator'] ?? 'and');
        $ruleList = $rules['criteria'] ?? ($rules['rules'] ?? []);

        if (empty($ruleList)) {
            return;
        }

        $query->where(function (Builder $sub) use ($ruleList, $logicalOp, $targetEntity) {
            foreach ($ruleList as $rule) {
                $field = $rule['field'] ?? '';
                $operator = $rule['operator'] ?? 'equals';
                $value = $rule['value'] ?? null;

                $boolean = ($logicalOp === 'or') ? 'or' : 'and';

                if ($field === 'status') {
                    $sub->where('status', '=', $value, $boolean);
                } elseif ($field === 'min_score') {
                    $sub->where('score', '>=', (int) $value, $boolean);
                } elseif ($field === 'max_score') {
                    $sub->where('score', '<=', (int) $value, $boolean);
                } elseif ($field === 'tag' || $field === 'tags') {
                    // JSON tag matching
                    $sub->whereJsonContains('tags', $value, $boolean);
                } elseif ($field === 'source') {
                    $sub->where('source', '=', $value, $boolean);
                } elseif ($field === 'campaign') {
                    $sub->where('campaign', '=', $value, $boolean);
                } elseif ($field === 'city') {
                    $sub->where('city', 'like', "%{$value}%", $boolean);
                } elseif ($field === 'country') {
                    $sub->where('country', '=', $value, $boolean);
                } elseif ($field === 'lifecycle_stage') {
                    $sub->where('lifecycle_stage', '=', $value, $boolean);
                }
            }
        });
    }
}
