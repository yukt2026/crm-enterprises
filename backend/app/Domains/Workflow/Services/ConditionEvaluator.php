<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Omnichannel\Models\CommunicationSuppression;
use Carbon\Carbon;

class ConditionEvaluator
{
    /**
     * Evaluate rules tree against context and entity data.
     * Guaranteed safe: Never executes arbitrary code.
     */
    public function evaluate(array $conditionGroup, array $context, int $tenantId): bool
    {
        $combinator = strtolower($conditionGroup['combinator'] ?? 'and'); // 'and' | 'or'
        $rules = $conditionGroup['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            // Nested rule group
            if (isset($rule['rules'])) {
                $passed = $this->evaluate($rule, $context, $tenantId);
            } else {
                $passed = $this->evaluateSingleRule($rule, $context, $tenantId);
            }

            if ($combinator === 'and' && !$passed) {
                return false;
            }
            if ($combinator === 'or' && $passed) {
                return true;
            }
        }

        return $combinator === 'and';
    }

    protected function evaluateSingleRule(array $rule, array $context, int $tenantId): bool
    {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $targetValue = $rule['value'] ?? null;

        // Special rule: business hours check
        if ($operator === 'in_business_hours') {
            $now = Carbon::now('Asia/Kolkata');
            $hour = (int) $now->format('H');
            return ($hour >= 9 && $hour < 21); // 9 AM to 9 PM IST
        }

        // Special rule: communication consent check
        if ($operator === 'has_consent') {
            $identifier = $this->resolveValue($field, $context);
            if (!$identifier) return false;

            $channel = $targetValue ?? 'all';
            return !CommunicationSuppression::where('tenant_id', $tenantId)
                ->whereIn('channel', [$channel, 'all'])
                ->where('identifier', strtolower(trim((string)$identifier)))
                ->exists();
        }

        $actualValue = $this->resolveValue($field, $context);

        return match ($operator) {
            'equals' => strtolower((string)$actualValue) === strtolower((string)$targetValue),
            'not_equals' => strtolower((string)$actualValue) !== strtolower((string)$targetValue),
            'contains' => str_contains(strtolower((string)$actualValue), strtolower((string)$targetValue)),
            'not_contains' => !str_contains(strtolower((string)$actualValue), strtolower((string)$targetValue)),
            'greater_than' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue > (float)$targetValue),
            'less_than' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue < (float)$targetValue),
            'greater_or_equal' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue >= (float)$targetValue),
            'less_or_equal' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue <= (float)$targetValue),
            'is_empty' => empty($actualValue),
            'is_not_empty' => !empty($actualValue),
            default => false,
        };
    }

    protected function resolveValue(string $fieldPath, array $context): mixed
    {
        $keys = explode('.', $fieldPath);
        $curr = $context;

        foreach ($keys as $key) {
            if (is_array($curr) && array_key_exists($key, $curr)) {
                $curr = $curr[$key];
            } else {
                return null;
            }
        }

        return $curr;
    }
}
