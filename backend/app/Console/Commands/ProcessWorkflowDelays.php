<?php

namespace App\Console\Commands;

use App\Domains\Workflow\Models\WorkflowRun;
use App\Domains\Workflow\Services\WorkflowExecutionEngine;
use Illuminate\Console\Command;

class ProcessWorkflowDelays extends Command
{
    protected $signature = 'workflow:process-delays';
    protected $description = 'Process and resume suspended workflow runs whose delay timers have elapsed';

    public function handle(WorkflowExecutionEngine $engine): int
    {
        $dueRuns = WorkflowRun::where('status', 'waiting')
            ->whereNotNull('resume_at')
            ->where('resume_at', '<=', now())
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($dueRuns as $run) {
            $this->info("Resuming workflow run #{$run->id} (Workflow: {$run->workflow_id})");
            $engine->resume($run);
            $count++;
        }

        $this->info("Successfully processed {$count} due workflow delays.");
        return Command::SUCCESS;
    }
}
