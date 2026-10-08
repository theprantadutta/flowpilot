<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Notifications\TaskAssignedNotification;
use App\Support\Activity\ActivityLogger;
use App\Workflows\Engine\WorkflowTriggers;
use Illuminate\Support\Facades\DB;

class UpdateTask
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly WorkflowTriggers $triggers,
    ) {}

    /**
     * Apply edits to a task, keep completion time and board order consistent,
     * record what changed and tell a new assignee.
     *
     * @param  array<string, mixed>  $attributes  Validated attributes to change.
     * @param  WorkflowRun|null  $automation  The workflow run making the change, if a workflow is.
     */
    public function handle(Task $task, ?User $actor, array $attributes, ?WorkflowRun $automation = null): Task
    {
        $previousAssignee = $task->assignee_id;

        DB::transaction(function () use ($task, $actor, $attributes, $automation): void {
            $before = $this->snapshot($task);

            $task->fill($attributes);

            if ($task->isDirty('status')) {
                $task->completed_at = $task->status->isDone() ? now() : null;
                // Changing status from a form puts the card at the bottom of its new column.
                if (! array_key_exists('position', $attributes)) {
                    $task->position = (float) (Task::query()->where('status', $task->status->value)->whereKeyNot($task->id)->max('position') ?? 0) + 1024;
                }
            }

            if ($task->isDirty('due_date')) {
                $task->overdue_notified_at = null;
            }

            $task->save();

            $changes = ActivityLogger::diff($before, $this->snapshot($task));

            if ($changes !== []) {
                $action = $this->action($changes);

                $this->activity->log($action, $task, [
                    'title' => $task->title,
                    'reference' => $task->reference(),
                    'changes' => $changes,
                    ...ActivityLogger::automation($automation),
                ], context: $task->project, actor: $actor, actorType: $automation ? 'workflow' : 'user');

                if ($action === 'task.completed') {
                    $this->triggers->fire('task.completed', $task, $actor, $automation, occurrence: (string) $task->completed_at?->getTimestamp());
                }
            }
        });

        if ($task->assignee_id !== null && $task->assignee_id !== $previousAssignee && $task->assignee_id !== $actor?->id) {
            $task->assignee?->notify(new TaskAssignedNotification($task, $automation ? ActivityLogger::automation($automation)['workflow'] : $actor?->name));
        }

        return $task;
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    private function action(array $changes): string
    {
        return match (true) {
            isset($changes['status']) && $changes['status']['to'] === TaskStatus::Done->value => 'task.completed',
            isset($changes['status']) => 'task.status_changed',
            isset($changes['assignee_id']) => 'task.assigned',
            default => 'task.updated',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Task $task): array
    {
        return [
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'assignee_id' => $task->assignee_id,
            'project_id' => $task->project_id,
            'due_date' => $task->due_date?->toDateString(),
            'tags' => $task->tags,
        ];
    }
}
