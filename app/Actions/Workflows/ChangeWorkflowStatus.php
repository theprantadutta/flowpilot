<?php

namespace App\Actions\Workflows;

use App\Enums\Limit;
use App\Enums\WorkflowStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Activity\ActivityLogger;
use App\Support\Billing\Entitlements;
use Illuminate\Validation\ValidationException;

/**
 * Pause, resume or archive a workflow. Paused and archived workflows start no
 * new runs; runs already under way finish on their version.
 */
class ChangeWorkflowStatus
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(Workflow $workflow, User $actor, WorkflowStatus $status): Workflow
    {
        if ($workflow->status === $status) {
            return $workflow;
        }

        if ($status === WorkflowStatus::Active && $workflow->current_version_id === null) {
            throw ValidationException::withMessages(['status' => 'Publish the workflow before turning it on.']);
        }

        if ($status === WorkflowStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'A workflow cannot be moved back to draft.']);
        }

        // Bringing a workflow back from the archive counts against the limit again.
        if ($workflow->status === WorkflowStatus::Archived) {
            app(Entitlements::class)->ensureRoom(Limit::Workflows, 1, 'status');
        }

        $from = $workflow->status;

        $workflow->forceFill(['status' => $status, 'updated_by' => $actor->id])->save();

        $this->activity->log(match ($status) {
            WorkflowStatus::Active => 'workflow.resumed',
            WorkflowStatus::Paused => 'workflow.paused',
            default => 'workflow.archived',
        }, $workflow, [
            'name' => $workflow->name,
            'changes' => ['status' => ['from' => $from->value, 'to' => $status->value]],
        ], actor: $actor);

        return $workflow;
    }
}
