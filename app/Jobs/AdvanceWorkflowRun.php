<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\WorkflowRun;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Engine\WorkflowEngine;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Moves one workflow run forward. Only one of these runs per workflow run at
 * a time; a second one waits its turn rather than racing the first.
 */
class AdvanceWorkflowRun implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    public int $timeout = 120;

    public function __construct(
        public string $organizationId,
        public string $runId,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("workflow-run:{$this->runId}"))->releaseAfter(5)->expireAfter(180)];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    public function handle(Tenancy $tenancy, WorkflowEngine $engine): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $tenancy->run($organization, function () use ($engine): void {
            $run = WorkflowRun::query()->find($this->runId);

            if ($run !== null) {
                $engine->advance($run);
            }
        });
    }

    /**
     * The engine records step failures itself; this only runs when the job
     * kept crashing, so the run does not sit "running" forever.
     */
    public function failed(?Throwable $exception): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        app(Tenancy::class)->run($organization, function (): void {
            $run = WorkflowRun::query()->find($this->runId);

            if ($run !== null) {
                app(WorkflowEngine::class)->failRun($run, null, 'The run stopped unexpectedly and could not be resumed. Try it again from the run page.');
            }
        });
    }
}
