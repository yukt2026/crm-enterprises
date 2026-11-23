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

        // Special rule: business hours check (e.g. 09:00 - 20:00 IST)
        if ($operator === 'in_business_hours') {
            $tz = $rule['timezone'] ?? 'Asia/Kolkata';
            $now = Carbon::now($tz);
            $dayOfWeek = (int) $now->format('w'); // 0 = Sunday
            $hour = (int) $now->format('H');

            // Default business days Mon-Sat (1 to 6), 9 AM to 8 PM
            $isWorkingDay = $dayOfWeek >= 1 && $dayOfWeek <= 6;
            return $isWorkingDay && ($hour >= 9 && $hour < 20);
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

        // Date comparison helpers
        if (in_array($operator, ['before_date', 'after_date', 'is_today', 'is_in_past', 'is_in_future', 'within_days'])) {
            return $this->evaluateDateRule($operator, $actualValue, $targetValue);
        }

        // Tags / Array checks
        if (is_array($actualValue)) {
            return match ($operator) {
                'contains' => in_array($targetValue, $actualValue) || in_array(strtolower((string)$targetValue), array_map('strtolower', $actualValue)),
                'not_contains' => !in_array($targetValue, $actualValue) && !in_array(strtolower((string)$targetValue), array_map('strtolower', $actualValue)),
                'is_empty' => empty($actualValue),
                'is_not_empty' => !empty($actualValue),
                default => false,
            };
        }

        return match ($operator) {
            'equals' => strtolower((string)$actualValue) === strtolower((string)$targetValue),
            'not_equals' => strtolower((string)$actualValue) !== strtolower((string)$targetValue),
            'contains' => str_contains(strtolower((string)$actualValue), strtolower((string)$targetValue)),
            'not_contains' => !str_contains(strtolower((string)$actualValue), strtolower((string)$targetValue)),
            'starts_with' => str_starts_with(strtolower((string)$actualValue), strtolower((string)$targetValue)),
            'ends_with' => str_ends_with(strtolower((string)$actualValue), strtolower((string)$targetValue)),
            'greater_than' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue > (float)$targetValue),
            'less_than' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue < (float)$targetValue),
            'greater_or_equal' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue >= (float)$targetValue),
            'less_or_equal' => is_numeric($actualValue) && is_numeric($targetValue) && ((float)$actualValue <= (float)$targetValue),
            'is_empty' => $actualValue === null || $actualValue === '' || $actualValue === [],
            'is_not_empty' => $actualValue !== null && $actualValue !== '' && $actualValue !== [],
            default => false,
        };
    }

    protected function evaluateDateRule(string $operator, mixed $actualValue, mixed $targetValue): bool
    {
        if (empty($actualValue)) {
            return false;
        }

        try {
            $actualDate = Carbon::parse($actualValue);
        } catch (\Throwable) {
            return false;
        }

        return match ($operator) {
            'before_date' => $targetValue ? $actualDate->lt(Carbon::parse($targetValue)) : false,
            'after_date' => $targetValue ? $actualDate->gt(Carbon::parse($targetValue)) : false,
            'is_today' => $actualDate->isToday(),
            'is_in_past' => $actualDate->isPast(),
            'is_in_future' => $actualDate->isFuture(),
            'within_days' => is_numeric($targetValue) && abs($actualDate->diffInDays(now())) <= (int)$targetValue,
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
