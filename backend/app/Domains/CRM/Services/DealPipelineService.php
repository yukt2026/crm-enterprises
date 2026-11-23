<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\CrmActivity;
use App\Domains\CRM\Models\Deal;
use App\Domains\CRM\Models\DealStageHistory;
use App\Domains\CRM\Models\Pipeline;
use App\Domains\CRM\Models\PipelineStage;
use App\Domains\Communications\Services\NotificationService;
use App\Domains\Tenancy\Services\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DealPipelineService
{
    /**
     * Ensure at least one default pipeline with standard stages exists for the tenant.
     */
    public function ensureDefaultPipeline(int $tenantId): Pipeline
    {
        $existing = Pipeline::where('tenant_id', $tenantId)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($tenantId) {
            $pipeline = Pipeline::create([
                'tenant_id' => $tenantId,
                'name' => 'Standard Sales Pipeline',
                'description' => 'Default consultative enterprise pipeline',
                'is_default' => true,
                'is_active' => true,
            ]);

            $stages = [
                ['name' => 'Qualification', 'code' => 'qualification', 'probability' => 20, 'order_column' => 1, 'color' => '#3b82f6', 'is_won' => false, 'is_lost' => false],
                ['name' => 'Needs Discovery', 'code' => 'discovery', 'probability' => 40, 'order_column' => 2, 'color' => '#6366f1', 'is_won' => false, 'is_lost' => false],
                ['name' => 'Proposal & Demo', 'code' => 'proposal', 'probability' => 60, 'order_column' => 3, 'color' => '#f59e0b', 'is_won' => false, 'is_lost' => false, 'required_fields' => ['value']],
                ['name' => 'Contract Negotiation', 'code' => 'negotiation', 'probability' => 80, 'order_column' => 4, 'color' => '#ec4899', 'is_won' => false, 'is_lost' => false, 'required_fields' => ['close_date', 'value']],
                ['name' => 'Closed Won', 'code' => 'won', 'probability' => 100, 'order_column' => 5, 'color' => '#10b981', 'is_won' => true, 'is_lost' => false, 'required_fields' => ['value', 'close_date']],
                ['name' => 'Closed Lost', 'code' => 'lost', 'probability' => 0, 'order_column' => 6, 'color' => '#ef4444', 'is_won' => false, 'is_lost' => true, 'required_fields' => ['lost_reason']],
            ];

            foreach ($stages as $stageData) {
                $stageData['tenant_id'] = $tenantId;
                $stageData['pipeline_id'] = $pipeline->id;
                PipelineStage::create($stageData);
            }

            return $pipeline;
        });
    }

    /**
     * Move deal to target stage with transition validation and historical logging.
     */
    public function moveDealToStage(Deal $deal, int $targetStageId, array $payload = [], ?int $userId = null): Deal
    {
        $tenantId = $deal->tenant_id ?? TenantContext::getTenantId() ?? 1;

        $targetStage = PipelineStage::where('tenant_id', $tenantId)->findOrFail($targetStageId);

        // Stage Transition Validation
        $this->validateStageTransition($deal, $targetStage, $payload);

        return DB::transaction(function () use ($deal, $targetStage, $payload, $tenantId, $userId) {
            $fromStageId = $deal->pipeline_stage_id;
            $fromStageName = $deal->pipelineStage?->name ?? $deal->stage;

            // Calculate days in previous stage
            $previousUpdated = $deal->stage_updated_at ?? $deal->created_at;
            $daysInPrev = $previousUpdated ? Carbon::parse($previousUpdated)->diffInDays(now()) : 0;

            $dealValue = isset($payload['value']) ? (float) $payload['value'] : (float) $deal->value;
            $probability = $targetStage->probability;
            $weightedValue = round(($dealValue * $probability) / 100, 2);

            $updateData = [
                'pipeline_id' => $targetStage->pipeline_id,
                'pipeline_stage_id' => $targetStage->id,
                'stage' => $targetStage->code,
                'probability' => $probability,
                'weighted_value' => $weightedValue,
                'stage_updated_at' => now(),
            ];

            if (isset($payload['value'])) {
                $updateData['value'] = $dealValue;
            }

            if (isset($payload['close_date'])) {
                $updateData['close_date'] = $payload['close_date'];
            }

            if ($targetStage->is_won) {
                $updateData['won_at'] = now();
                $updateData['lost_at'] = null;
            } elseif ($targetStage->is_lost) {
                $updateData['lost_at'] = now();
                $updateData['won_at'] = null;
                $updateData['lost_reason'] = $payload['lost_reason'] ?? $deal->lost_reason ?? 'Lost to competition or budget';
                $updateData['competitor'] = $payload['competitor'] ?? $deal->competitor;
            } else {
                // Reopened or open stage
                $updateData['won_at'] = null;
                $updateData['lost_at'] = null;
            }

            $deal->update($updateData);

            // Record Stage History
            DealStageHistory::create([
                'tenant_id' => $tenantId,
                'deal_id' => $deal->id,
                'from_stage_id' => $fromStageId,
                'to_stage_id' => $targetStage->id,
                'from_stage_name' => $fromStageName,
                'to_stage_name' => $targetStage->name,
                'days_in_previous_stage' => $daysInPrev,
                'changed_by_user_id' => $userId,
                'notes' => $payload['notes'] ?? null,
            ]);

            // Register Activity
            CrmActivity::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'deal_id' => $deal->id,
                'activity_type' => 'status_change',
                'title' => "Deal Moved to {$targetStage->name}",
                'description' => "Stage transitioned from '{$fromStageName}' to '{$targetStage->name}' (Weighted: \${$weightedValue}).",
                'metadata' => [
                    'from_stage_id' => $fromStageId,
                    'to_stage_id' => $targetStage->id,
                    'days_in_prev_stage' => $daysInPrev,
                    'changed_by' => $userId,
                ],
            ]);

            // If Won, auto-trigger sales commission calculation
            if ($targetStage->is_won) {
                try {
                    app(\App\Domains\Sales\Services\SalesCommissionService::class)->calculateDealWonCommission($deal);
                } catch (\Throwable $e) {
                    // Non-blocking for transition
                }
            }

            return $deal->load(['pipelineStage', 'pipeline', 'products']);
        });
    }

    /**
     * Transfer deal ownership with audit trail.
     */
    public function transferOwnership(Deal $deal, int $newUserId, int $transferredByUserId, ?string $reason = null): Deal
    {
        $oldUserId = $deal->assigned_user_id;

        return DB::transaction(function () use ($deal, $newUserId, $transferredByUserId, $oldUserId, $reason) {
            $deal->update(['assigned_user_id' => $newUserId]);

            CrmActivity::create([
                'tenant_id' => $deal->tenant_id,
                'user_id' => $transferredByUserId,
                'deal_id' => $deal->id,
                'activity_type' => 'status_change',
                'title' => "Deal Ownership Reassigned",
                'description' => "Ownership transferred from User #{$oldUserId} to User #{$newUserId}. Reason: " . ($reason ?: 'Team rebalancing'),
                'metadata' => [
                    'old_assigned_user_id' => $oldUserId,
                    'new_assigned_user_id' => $newUserId,
                    'transferred_by_user_id' => $transferredByUserId,
                ],
            ]);

            return $deal->fresh(['assignedUser']);
        });
    }

    /**
     * Reopen a closed deal with authorization.
     */
    public function reopenDeal(Deal $deal, int $reopenStageId, int $userId, ?string $reason = null): Deal
    {
        $targetStage = PipelineStage::where('tenant_id', $deal->tenant_id)->findOrFail($reopenStageId);

        return DB::transaction(function () use ($deal, $targetStage, $userId, $reason) {
            $deal->update([
                'pipeline_stage_id' => $targetStage->id,
                'stage' => $targetStage->code,
                'probability' => $targetStage->probability,
                'weighted_value' => round(((float)$deal->value * $targetStage->probability) / 100, 2),
                'won_at' => null,
                'lost_at' => null,
                'reopened_at' => now(),
                'reopened_by_user_id' => $userId,
                'notes' => $reason ? ($deal->notes . "\n[Reopened]: " . $reason) : $deal->notes,
            ]);

            CrmActivity::create([
                'tenant_id' => $deal->tenant_id,
                'user_id' => $userId,
                'deal_id' => $deal->id,
                'activity_type' => 'status_change',
                'title' => "Deal Reopened",
                'description' => "Deal reopened to stage '{$targetStage->name}'. Reason: " . ($reason ?: 'Client re-engaged'),
                'metadata' => [
                    'reopened_by' => $userId,
                    'reopen_stage_id' => $targetStage->id,
                ],
            ]);

            return $deal->fresh(['pipelineStage', 'pipeline']);
        });
    }

    /**
     * Convert won deal into onboarding project / workflow.
     */
    public function convertToProject(Deal $deal, int $userId, array $projectData = []): \App\Domains\Projects\Models\Project
    {
        return DB::transaction(function () use ($deal, $userId, $projectData) {
            $project = \App\Domains\Projects\Models\Project::create([
                'tenant_id' => $deal->tenant_id,
                'name' => $projectData['name'] ?? "Onboarding: {$deal->name}",
                'description' => $projectData['description'] ?? "Client onboarding delivery workflow for Won Deal #{$deal->id}",
                'status' => 'planning',
                'budget' => (float) $deal->value,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'customer_id' => $deal->customer_id,
                'manager_id' => $deal->assigned_user_id ?: $userId,
            ]);

            $deal->update(['project_id' => $project->id]);

            CrmActivity::create([
                'tenant_id' => $deal->tenant_id,
                'user_id' => $userId,
                'deal_id' => $deal->id,
                'activity_type' => 'status_change',
                'title' => "Deal Converted to Project",
                'description' => "Won deal converted to Project #{$project->id} ({$project->name}).",
                'metadata' => [
                    'project_id' => $project->id,
                    'converted_by' => $userId,
                ],
            ]);

            return $project;
        });
    }

    /**
     * Validate required fields and allowed transitions for the destination stage.
     */
    protected function validateStageTransition(Deal $deal, PipelineStage $targetStage, array $payload): void
    {
        // 1. Allowed transitions check
        $currentStage = $deal->pipelineStage;
        if ($currentStage && !empty($currentStage->allowed_transitions)) {
            $allowed = $currentStage->allowed_transitions;
            $targetCode = $targetStage->code;
            $targetId = (string) $targetStage->id;

            $isAllowed = in_array($targetCode, $allowed, true) || in_array($targetId, $allowed, true);
            if (!$isAllowed) {
                throw ValidationException::withMessages([
                    'stage' => ["Transition from '{$currentStage->name}' to '{$targetStage->name}' is not permitted by pipeline workflow rules."],
                ]);
            }
        }

        // 2. Required fields check
        $required = $targetStage->required_fields ?? [];
        $errors = [];

        foreach ($required as $field) {
            $val = $payload[$field] ?? $deal->{$field};
            if (is_null($val) || $val === '' || ($field === 'value' && (float)$val <= 0)) {
                $errors[$field] = ["Field '{$field}' is required when advancing deal to stage '{$targetStage->name}'."];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Calculate comprehensive weighted sales forecast by pipeline.
     */
    public function getForecast(int $tenantId, ?int $pipelineId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Deal::where('tenant_id', $tenantId)->where('is_archived', false);

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('close_date', [$startDate, $endDate]);
        }

        $allDeals = $query->with('pipelineStage')->get();

        $totalPipelineValue = 0;
        $totalWeightedValue = 0;
        $totalWonValue = 0;
        $totalLostValue = 0;
        $stageBreakdown = [];

        foreach ($allDeals as $deal) {
            $val = (float) $deal->value;
            $stageCode = $deal->stage;
            $weighted = (float) ($deal->weighted_value ?: ($val * $deal->probability / 100));

            if ($stageCode === 'won') {
                $totalWonValue += $val;
            } elseif ($stageCode === 'lost') {
                $totalLostValue += $val;
            } else {
                $totalPipelineValue += $val;
                $totalWeightedValue += $weighted;
            }

            if (!isset($stageBreakdown[$stageCode])) {
                $stageBreakdown[$stageCode] = [
                    'stage' => $deal->pipelineStage?->name ?? ucfirst($stageCode),
                    'count' => 0,
                    'total_value' => 0,
                    'weighted_value' => 0,
                ];
            }

            $stageBreakdown[$stageCode]['count']++;
            $stageBreakdown[$stageCode]['total_value'] += $val;
            $stageBreakdown[$stageCode]['weighted_value'] += $weighted;
        }

        $winRate = ($totalWonValue + $totalLostValue) > 0
            ? round(($totalWonValue / ($totalWonValue + $totalLostValue)) * 100, 1)
            : 0;

        return [
            'total_open_pipeline_value' => round($totalPipelineValue, 2),
            'total_weighted_forecast' => round($totalWeightedValue, 2),
            'total_won_value' => round($totalWonValue, 2),
            'total_lost_value' => round($totalLostValue, 2),
            'win_rate_percentage' => $winRate,
            'total_deals_count' => count($allDeals),
            'stage_breakdown' => array_values($stageBreakdown),
        ];
    }

    /**
     * Identify stale deals that have exceeded their stage's duration threshold.
     */
    public function detectStaleDeals(int $tenantId, int $defaultDays = 14): array
    {
        $openDeals = Deal::where('tenant_id', $tenantId)
            ->open()
            ->with(['pipelineStage', 'assignedUser', 'company', 'contact'])
            ->get();

        $staleDeals = [];

        foreach ($openDeals as $deal) {
            $threshold = $deal->pipelineStage?->stale_days_threshold ?? $defaultDays;
            $lastUpdated = $deal->stage_updated_at ?? $deal->created_at;
            $daysInStage = Carbon::parse($lastUpdated)->diffInDays(now());

            if ($daysInStage >= $threshold) {
                $staleDeals[] = [
                    'deal' => $deal,
                    'days_in_stage' => $daysInPrev = $daysInStage,
                    'threshold_days' => $threshold,
                    'stale_level' => $daysInStage >= ($threshold * 2) ? 'critical' : 'warning',
                ];
            }
        }

        return $staleDeals;
    }
}
