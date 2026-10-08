<?php

namespace App\Workflows\Engine;

use App\Jobs\StartTriggeredWorkflows;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Model;

/**
 * Called by actions when something happens that workflows can start from
 * ("task.created"). Matching workflows are found and started on the queue,
 * after the change is committed, so the action stays fast.
 */
class WorkflowTriggers
{
    /**
     * How many workflows can set each other off in a chain (a workflow creates
     * a task, which starts another workflow…) before the chain is stopped.
     */
    public const int MAX_DEPTH = 3;

    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @param  string  $trigger  Trigger key, e.g. "task.created".
     * @param  WorkflowRun|null  $source  The run that caused the event, when a workflow did.
     * @param  string|null  $occurrence  Distinguishes repeat events on one record (a task completed twice).
     */
    public function fire(string $trigger, Model $subject, ?User $actor, ?WorkflowRun $source = null, ?string $occurrence = null): void
    {
        $organization = $this->tenancy->current();

        if ($organization === null) {
            return;
        }

        $depth = $source ? (int) data_get($source->context, 'meta.depth', 0) + 1 : 0;

        if ($depth > self::MAX_DEPTH) {
            return;
        }

        StartTriggeredWorkflows::dispatch(
            organizationId: $organization->id,
            trigger: $trigger,
            subjectType: $subject->getMorphClass(),
            subjectId: (string) $subject->getKey(),
            actorId: $actor?->id,
            sourceWorkflowId: $source?->workflow_id,
            depth: $depth,
            occurrence: $occurrence,
        )->afterCommit();
    }
}
