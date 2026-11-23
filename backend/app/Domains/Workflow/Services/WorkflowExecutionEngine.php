<?php

namespace App\Domains\Workflow\Services;

use App\Domains\Workflow\Models\Workflow;
use App\Domains\Workflow\Models\WorkflowRun;
use App\Domains\Workflow\Models\WorkflowStepLog;
use App\Domains\Workflow\Models\WorkflowVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WorkflowExecutionEngine
{
    public function __construct(
        protected ConditionEvaluator $conditionEvaluator,
        protected ActionExecutor $actionExecutor
    ) {}

    /**
     * Dispatch an event to matching published workflows.
     */
    public function trigger(int $tenantId, string $triggerType, array $payload, ?string $idempotencyKey = null): array
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

        // 2. Find published & active workflows matching trigger
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
                'trigger_event' => $triggerType,
                'idempotency_key' => $idempotencyKey,
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => $payload['entity_id'] ?? null,
                'status' => 'running',
                'trigger_payload' => $payload,
                'context_data' => array_merge($payload, ['trigger_event' => $triggerType]),
                'started_at' => now(),
            ]);

            $workflow->increment('total_runs_count');
            $workflow->update(['last_run_at' => now()]);

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
    public function executeGraph(WorkflowRun $run, array $definition, bool $isDryRun = false, ?string $startNodeId = null): array
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
        $maxSteps = 50; // Loop prevention guardrail
        $stepCount = 0;
        $trace = [];

        while ($currentNode && $stepCount < $maxSteps) {
            $stepCount++;
            $nodeId = $currentNode['id'];
            $nodeType = $currentNode['type'] ?? 'action';
            $nodeName = $currentNode['data']['label'] ?? $nodeId;
            $startTime = microtime(true);

            $run->update(['current_node_id' => $nodeId]);

            // Branch decision holder
            $branchTaken = null;
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
                        $actionResult = $this->actionExecutor->execute($actionType, $actionParams, $context, $tenantId, $isDryRun);
                        $outputState = $actionResult;
                        if (($actionResult['status'] ?? '') === 'failed') {
                            $stepStatus = 'failed';
                            $errorMessage = $actionResult['error'] ?? ($actionResult['message'] ?? 'Action failed');
                        }
                        break;

                    case 'delay':
                        $duration = (int) ($currentNode['data']['duration'] ?? 1);
                        $unit = $currentNode['data']['unit'] ?? 'hours'; // minutes, hours, days
                        $resumeAt = now()->add($duration, $unit);

                        if (!$isDryRun) {
                            $run->update([
                                'status' => 'waiting',
                                'resume_at' => $resumeAt,
                                'context_data' => $context,
                            ]);
                        }

                        $stepStatus = 'waiting';
                        $outputState = ['delay_duration' => $duration, 'unit' => $unit, 'resume_at' => $resumeAt->toIso8601String()];
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

            // If node failed or suspended, halt loop
            if ($stepStatus === 'failed') {
                if (!$isDryRun) {
                    $run->update([
                        'status' => 'failed',
                        'error_message' => $errorMessage,
                        'completed_at' => now(),
                    ]);
                }
                break;
            }

            if ($stepStatus === 'waiting' || $nodeType === 'stop') {
                break;
            }

            // Find next node along edge
            $nextEdge = null;
            if ($nodeType === 'condition' && $branchTaken) {
                // Match edge with sourceHandle or label matching branch (true/false)
                $nextEdge = $edges->first(function ($e) use ($nodeId, $branchTaken) {
                    return $e['source'] === $nodeId && (
                        ($e['sourceHandle'] ?? null) === $branchTaken ||
                        strtolower($e['label'] ?? '') === $branchTaken
                    );
                });

                // Fallback to any edge from this condition if not labelled
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
        if ($run->status !== 'waiting') {
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
            'context_data' => array_merge($samplePayload, ['trigger_event' => $workflow->trigger_type]),
            'status' => 'running',
        ]);

        return $this->executeGraph($mockRun, $definition, true);
    }
}
