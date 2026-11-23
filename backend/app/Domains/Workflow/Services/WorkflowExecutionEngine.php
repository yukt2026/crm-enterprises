<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\Workflow;
use App\Domains\Workflow\Models\WorkflowApproval;
use App\Domains\Workflow\Models\WorkflowAutomationLimit;
use App\Domains\Workflow\Models\WorkflowRun;
use App\Domains\Workflow\Models\WorkflowStepLog;
use App\Domains\Workflow\Models\WorkflowVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowExecutionEngine
{
    public function __construct(
        protected ConditionEvaluator $conditionEvaluator,
        protected ActionExecutor $actionExecutor
    ) {}

    /**
     * Dispatch an event to matching published workflows with loop protection and usage limits.
     */
    public function trigger(int $tenantId, string $triggerType, array $payload, ?string $idempotencyKey = null, ?int $parentRunId = null, int $depth = 1): array
    {
        // 1. Idempotency Check: Prevent duplicate event runs
        if ($idempotencyKey) {
            $existingRun = WorkflowRun::where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingRun) {
                return [
                    'status' => 'duplicate_ignored',
                    'run_id' => $existingRun->id,
                    'message' => "Duplicate event with idempotency key [{$idempotencyKey}] ignored.",
                ];
            }
        }

        // 2. Loop Protection: Check recursion depth
        if ($depth > 10) {
            return [
                'status' => 'loop_detected',
                'message' => "Execution loop detected: maximum recursion depth ({$depth}) exceeded.",
            ];
        }

        // 3. Loop Protection: Rate-limit runs per entity within rolling 15 minutes
        $entityType = $payload['entity_type'] ?? null;
        $entityId = $payload['entity_id'] ?? null;
        if ($entityType && $entityId) {
            $recentRunsCount = WorkflowRun::where('tenant_id', $tenantId)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('created_at', '>=', now()->subMinutes(15))
                ->count();

            if ($recentRunsCount >= 10) {
                return [
                    'status' => 'loop_detected',
                    'message' => "Execution loop detected: Entity [{$entityType}:{$entityId}] triggered {$recentRunsCount} runs in 15 minutes. Execution halted to protect system.",
                ];
            }
        }

        // 4. Check monthly execution limits
        $limits = WorkflowAutomationLimit::getForTenant($tenantId);
        if ($limits->max_monthly_executions > 0 && $limits->current_month_executions >= $limits->max_monthly_executions) {
            return [
                'status' => 'limit_exceeded',
                'message' => "Organization monthly automation limit of {$limits->max_monthly_executions} executions reached.",
            ];
        }

        // 5. Find published & active workflows matching trigger
        $workflows = Workflow::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('status', 'published')
            ->where('trigger_type', $triggerType)
            ->get();

        $runs = [];

        foreach ($workflows as $workflow) {
            $version = $workflow->currentVersionModel ?? $workflow->versions()->first();
            if (!$version) continue;

            $run = WorkflowRun::create([
                'tenant_id' => $tenantId,
                'workflow_id' => $workflow->id,
                'workflow_version_id' => $version->id,
                'parent_run_id' => $parentRunId,
                'depth' => $depth,
                'trigger_event' => $triggerType,
                'idempotency_key' => $idempotencyKey,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => 'running',
                'is_dry_run' => false,
                'trigger_payload' => $payload,
                'context_data' => array_merge($payload, [
                    'trigger_event' => $triggerType,
                    'workflow_id' => $workflow->id,
                    'tenant_id' => $tenantId,
                ]),
                'started_at' => now(),
            ]);

            $workflow->increment('total_runs_count');
            $workflow->update(['last_run_at' => now()]);
            $limits->increment('current_month_executions');

            $this->executeGraph($run, $version->definition);
            $runs[] = $run->fresh(['stepLogs']);
        }

        return [
            'status' => 'dispatched',
            'workflows_matched' => $workflows->count(),
            'runs' => $runs,
        ];
    }

    /**
     * Execute the node graph of a workflow run.
     */
    public function executeGraph(WorkflowRun $run, array $definition, bool $isDryRun = false, ?string $startNodeId = null, ?string $overrideBranch = null): array
    {
        $nodes = collect($definition['nodes'] ?? [])->keyBy('id');
        $edges = collect($definition['edges'] ?? []);

        // Find starting node
        if ($startNodeId && $nodes->has($startNodeId)) {
            $currentNode = $nodes->get($startNodeId);
        } else {
            $currentNode = $nodes->firstWhere('type', 'trigger');
            if (!$currentNode && $nodes->isNotEmpty()) {
                $currentNode = $nodes->first();
            }
        }

        $context = $run->context_data ?? [];
        $tenantId = $run->tenant_id;
        $maxSteps = 50; // Guardrail against infinite internal graphs
        $stepCount = 0;
        $trace = [];
        $engineStart = microtime(true);

        while ($currentNode && $stepCount < $maxSteps) {
            $stepCount++;
            $nodeId = $currentNode['id'];
            $nodeType = $currentNode['type'] ?? 'action';
            $nodeName = $currentNode['data']['label'] ?? $nodeId;
            $startTime = microtime(true);

            $run->update(['current_node_id' => $nodeId]);

            $branchTaken = $overrideBranch;
            $overrideBranch = null; // consume once
            $stepStatus = 'completed';
            $errorMessage = null;
            $outputState = null;

            try {
                switch ($nodeType) {
                    case 'trigger':
                        $outputState = ['trigger_event' => $run->trigger_event, 'payload' => $run->trigger_payload];
                        break;

                    case 'condition':
                        $conditionConfig = $currentNode['data']['conditions'] ?? ($currentNode['data'] ?? []);
                        $result = $this->conditionEvaluator->evaluate($conditionConfig, $context, $tenantId);
                        $branchTaken = $result ? 'true' : 'false';
                        $outputState = ['condition_result' => $result, 'branch' => $branchTaken];
                        break;

                    case 'action':
                        $actionType = $currentNode['data']['action_type'] ?? 'crm.add_note';
                        $actionParams = $currentNode['data']['params'] ?? [];
                        $actionResult = $this->actionExecutor->execute(
                            actionType: $actionType,
                            params: $actionParams,
                            context: $context,
                            tenantId: $tenantId,
                            isDryRun: $isDryRun,
                            runId: $run->id,
                            nodeId: $nodeId
                        );
                        $outputState = $actionResult;
                        if (($actionResult['status'] ?? '') === 'failed') {
                            $stepStatus = 'failed';
                            $errorMessage = $actionResult['error'] ?? ($actionResult['message'] ?? 'Action failed');
                        }
                        break;

                    case 'approval':
                        if ($isDryRun) {
                            $stepStatus = 'simulated';
                            $outputState = ['status' => 'simulated', 'message' => 'Human approval node simulated.'];
                            $branchTaken = 'approved';
                        } else {
                            $approvalConfig = $currentNode['data'] ?? [];
                            $approvalResult = $this->actionExecutor->execute(
                                actionType: 'approval.request_approval',
                                params: array_merge($approvalConfig, ['workflow_id' => $run->workflow_id]),
                                context: $context,
                                tenantId: $tenantId,
                                isDryRun: false,
                                runId: $run->id,
                                nodeId: $nodeId
                            );

                            $run->update([
                                'status' => 'waiting_approval',
                                'approval_id' => $approvalResult['approval_id'] ?? null,
                                'context_data' => $context,
                            ]);

                            $stepStatus = 'waiting_approval';
                            $outputState = $approvalResult;
                        }
                        break;

                    case 'delay':
                        $duration = (int) ($currentNode['data']['duration'] ?? 1);
                        $unit = $currentNode['data']['unit'] ?? 'hours'; // minutes, hours, days
                        $workingHoursOnly = (bool) ($currentNode['data']['working_hours_only'] ?? false);

                        $resumeAt = now()->add($duration, $unit);

                        // If working hours only, adjust to within 09:00 - 18:00 IST Monday-Saturday
                        if ($workingHoursOnly) {
                            $resumeAt = $this->calculateNextWorkingTime($resumeAt);
                        }

                        if (!$isDryRun) {
                            $run->update([
                                'status' => 'waiting',
                                'resume_at' => $resumeAt,
                                'context_data' => $context,
                            ]);
                        }

                        $stepStatus = 'waiting';
                        $outputState = [
                            'delay_duration' => $duration,
                            'unit' => $unit,
                            'working_hours_only' => $workingHoursOnly,
                            'resume_at' => $resumeAt->toIso8601String(),
                        ];
                        break;

                    case 'wait':
                        $waitForEvent = $currentNode['data']['event'] ?? 'customer.response';
                        $timeoutHours = (int) ($currentNode['data']['timeout_hours'] ?? 48);
                        $timeoutAt = now()->addHours($timeoutHours);

                        if (!$isDryRun) {
                            $run->update([
                                'status' => 'waiting',
                                'wait_for_event' => $waitForEvent,
                                'wait_timeout_at' => $timeoutAt,
                                'context_data' => $context,
                            ]);
                        }

                        $stepStatus = 'waiting';
                        $outputState = ['waiting_for' => $waitForEvent, 'timeout_at' => $timeoutAt->toIso8601String()];
                        break;

                    case 'stop':
                        $stepStatus = 'completed';
                        $outputState = ['stopped' => true];
                        break;
                }
            } catch (\Throwable $e) {
                $stepStatus = 'failed';
                $errorMessage = $e->getMessage();
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // Log step execution (unless dry run)
            if (!$isDryRun) {
                WorkflowStepLog::create([
                    'tenant_id' => $tenantId,
                    'workflow_run_id' => $run->id,
                    'node_id' => $nodeId,
                    'node_type' => $nodeType,
                    'node_name' => $nodeName,
                    'status' => $stepStatus,
                    'input_state' => $context,
                    'output_state' => $outputState,
                    'branch_taken' => $branchTaken,
                    'error_message' => $errorMessage,
                    'execution_duration_ms' => $durationMs,
                ]);
            }

            $trace[] = [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'node_name' => $nodeName,
                'status' => $stepStatus,
                'branch_taken' => $branchTaken,
                'output' => $outputState,
                'error' => $errorMessage,
                'duration_ms' => $durationMs,
            ];

            // If node failed or suspended in waiting/approval, halt loop
            if ($stepStatus === 'failed') {
                if (!$isDryRun) {
                    $run->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage,
                        'completed_at' => now(),
                        'execution_duration_ms' => (int) round((microtime(true) - $engineStart) * 1000),
                    ]);
                }
                break;
            }

            if (in_array($stepStatus, ['waiting', 'waiting_approval']) || $nodeType === 'stop') {
                break;
            }

            // Find next node along edge
            $nextEdge = null;
            if (in_array($nodeType, ['condition', 'approval']) && $branchTaken) {
                // Match edge with sourceHandle or label matching branch (e.g. true/false or approved/rejected)
                $nextEdge = $edges->first(function ($e) use ($nodeId, $branchTaken) {
                    return $e['source'] === $nodeId && (
                        ($e['sourceHandle'] ?? null) === $branchTaken ||
                        strtolower($e['label'] ?? '') === strtolower($branchTaken)
                    );
                });

                // Fallback to any edge if not labelled
                if (!$nextEdge) {
                    $nextEdge = $edges->firstWhere('source', $nodeId);
                }
            } else {
                $nextEdge = $edges->firstWhere('source', $nodeId);
            }

            if ($nextEdge && isset($nextEdge['target']) && $nodes->has($nextEdge['target'])) {
                $currentNode = $nodes->get($nextEdge['target']);
            } else {
                $currentNode = null;
            }
        }

        // Mark run completed if finished
        if (!$isDryRun && $run->status === 'running') {
            $run->update([
                'status' => 'completed',
                'context_data' => $context,
                'completed_at' => now(),
                'execution_duration_ms' => (int) round((microtime(true) - $engineStart) * 1000),
            ]);
        }

        return [
            'status' => $run->status,
            'steps_executed' => count($trace),
            'trace' => $trace,
        ];
    }

    /**
     * Resume a suspended / waiting workflow run.
     */
    public function resume(WorkflowRun $run, ?array $eventPayload = null): WorkflowRun
    {
        if (!in_array($run->status, ['waiting', 'waiting_approval'])) {
            return $run;
        }

        $version = $run->version;
        if (!$version) return $run;

        $context = $run->context_data ?? [];
        if ($eventPayload) {
            $context = array_merge($context, ['resumed_event' => $eventPayload]);
        }

        $run->update([
            'status' => 'running',
            'resume_at' => null,
            'wait_for_event' => null,
            'context_data' => $context,
        ]);

        // Find the next node connected downstream from current_node_id
        $edges = collect($version->definition['edges'] ?? []);
        $nextEdge = $edges->firstWhere('source', $run->current_node_id);
        $startNodeId = $nextEdge['target'] ?? null;

        $this->executeGraph($run, $version->definition, false, $startNodeId);

        return $run->fresh(['stepLogs']);
    }

    /**
     * Decide on an approval node and advance the workflow.
     */
    public function decideApproval(WorkflowApproval $approval, string $decision, ?string $comments = null, ?User $user = null): WorkflowRun
    {
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $branch = $decision === 'approve' ? 'approved' : 'rejected';

        $approval->update([
            'status' => $status,
            'comments' => $comments,
            'decision_by_user_id' => $user?->id,
            'decision_at' => now(),
        ]);

        $run = $approval->run;
        if (!$run || $run->status !== 'waiting_approval') {
            return $run;
        }

        $run->update([
            'status' => 'running',
            'context_data' => array_merge($run->context_data ?? [], [
                'approval_decision' => $status,
                'approval_comments' => $comments,
            ]),
        ]);

        $version = $run->version;
        if (!$version) return $run;

        $edges = collect($version->definition['edges'] ?? []);
        $nodeId = $approval->node_id;

        // Find edge for approved/rejected or fallback to any edge
        $nextEdge = $edges->first(function ($e) use ($nodeId, $branch) {
            return $e['source'] === $nodeId && (
                ($e['sourceHandle'] ?? null) === $branch ||
                strtolower($e['label'] ?? '') === $branch
            );
        });

        if ($nextEdge && isset($nextEdge['target'])) {
            $this->executeGraph($run, $version->definition, false, $nextEdge['target']);
        } else {
            // If rejected and no explicit reject edge, complete the workflow
            $run->update([
                'status' => $status === 'approved' ? 'completed' : 'cancelled',
                'completed_at' => now(),
            ]);
        }

        return $run->fresh(['stepLogs']);
    }

    /**
     * Test / Simulation mode: evaluates definition on sample payload without side-effects.
     */
    public function simulate(Workflow $workflow, array $definition, array $samplePayload): array
    {
        $mockRun = new WorkflowRun([
            'tenant_id' => $workflow->tenant_id,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $workflow->current_version,
            'trigger_event' => $workflow->trigger_type,
            'trigger_payload' => $samplePayload,
            'context_data' => array_merge($samplePayload, [
                'trigger_event' => $workflow->trigger_type,
                'workflow_id' => $workflow->id,
            ]),
            'status' => 'running',
            'is_dry_run' => true,
        ]);

        return $this->executeGraph($mockRun, $definition, true);
    }

    /**
     * Adjust time to fall within 09:00 - 18:00 IST Monday through Saturday.
     */
    protected function calculateNextWorkingTime(Carbon $target): Carbon
    {
        $tz = 'Asia/Kolkata';
        $time = $target->copy()->setTimezone($tz);

        // If Sunday, roll to Monday 09:00 AM
        if ($time->isSunday()) {
            $time->addDay()->setTime(9, 0, 0);
        }

        // If before 09:00 AM, set to 09:00 AM
        if ($time->hour < 9) {
            $time->setTime(9, 0, 0);
        }

        // If after 18:00 (6 PM), roll to next morning 09:00 AM
        if ($time->hour >= 18) {
            $time->addDay()->setTime(9, 0, 0);
            if ($time->isSunday()) {
                $time->addDay()->setTime(9, 0, 0);
            }
        }

        return $time;
    }
}
