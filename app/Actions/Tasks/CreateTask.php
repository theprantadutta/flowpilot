<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Notifications\TaskAssignedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\OrganizationSequence;
use App\Workflows\Engine\WorkflowTriggers;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OrganizationSequence $sequence,
        private readonly WorkflowTriggers $triggers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated task attributes.
     * @param  WorkflowRun|null  $automation  The workflow run creating the task, if a workflow is.
     */
    public function handle(?User $actor, array $attributes, ?WorkflowRun $automation = null): Task
    {
        $task = DB::transaction(function () use ($actor, $attributes, $automation): Task {
            $status = TaskStatus::tryFrom((string) ($attributes['status'] ?? '')) ?? TaskStatus::Todo;

            $task = Task::query()->create([
                ...$attributes,
                'number' => $this->sequence->next('tasks'),
                'status' => $status,
                'reporter_id' => $actor?->id,
                // New cards go to the bottom of their column.
                'position' => (float) (Task::query()->where('status', $status->value)->max('position') ?? 0) + 1024,
                'completed_at' => $status->isDone() ? now() : null,
            ]);

            $this->activity->log('task.created', $task, [
                'title' => $task->title,
                'reference' => $task->reference(),
                ...ActivityLogger::automation($automation),
            ], context: $task->project, actor: $actor, actorType: $automation ? 'workflow' : 'user');

            $this->triggers->fire('task.created', $task, $actor, $automation);

            return $task;
        });

        if ($task->assignee_id !== null && $task->assignee_id !== $actor?->id) {
            $task->assignee?->notify(new TaskAssignedNotification($task, $automation ? ActivityLogger::automation($automation)['workflow'] : $actor?->name));
        }

        return $task;
    }
}
