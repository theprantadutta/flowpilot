<?php

namespace App\Console\Commands;

use App\Enums\StepRunStatus;
use App\Enums\WorkflowRunStatus;
use App\Jobs\AdvanceWorkflowRun;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

/**
 * Wakes runs whose wait is over (a delay finished, a retry is due) and
 * re-queues runs that stalled because a worker stopped mid-run. Runs every
 * minute; crosses organizations on purpose, then each run is advanced inside
 * its own organization by the job.
 */
#[Signature('workflows:resume')]
#[Description('Resume workflow runs that are due and recover stalled ones')]
class ResumeWorkflowRuns extends Command
{
    /**
     * A run left "pending" or "running" this long without changing is stalled.
     */
    public const int STALLED_AFTER_MINUTES = 10;

    public function handle(): int
    {
        $due = WorkflowStepRun::withoutOrganizationScope()
            ->whereIn('workflow_step_runs.status', [StepRunStatus::Pending->value, StepRunStatus::Waiting->value])
            ->whereNotNull('workflow_step_runs.resume_at')
            ->where('workflow_step_runs.resume_at', '<=', now())
            ->join('workflow_runs', 'workflow_runs.id', '=', 'workflow_step_runs.workflow_run_id')
            ->whereIn('workflow_runs.status', [WorkflowRunStatus::Running->value, WorkflowRunStatus::Waiting->value])
            ->limit(500)
            ->get(['workflow_runs.id as run_id', 'workflow_runs.organization_id as run_organization_id']);

        $stalled = WorkflowRun::withoutOrganizationScope()
            ->whereIn('status', [WorkflowRunStatus::Pending->value, WorkflowRunStatus::Running->value])
            ->where('updated_at', '<', now()->subMinutes(self::STALLED_AFTER_MINUTES))
            // Runs parked on a retry that is not due yet are waiting, not stalled.
            ->whereNotExists(fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('workflow_step_runs')
                ->whereColumn('workflow_step_runs.workflow_run_id', 'workflow_runs.id')
                ->where('workflow_step_runs.resume_at', '>', now()))
            ->limit(500)
            ->get(['id as run_id', 'organization_id as run_organization_id']);

        $runs = [];

        foreach ([...$due->all(), ...$stalled->all()] as $row) {
            $runs[(string) $row->getAttribute('run_id')] = (string) $row->getAttribute('run_organization_id');
        }

        foreach ($runs as $runId => $organizationId) {
            AdvanceWorkflowRun::dispatch($organizationId, $runId);
        }

        $this->components->info(count($runs) === 1 ? 'Resumed 1 workflow run.' : 'Resumed '.count($runs).' workflow runs.');

        return self::SUCCESS;
    }
}
