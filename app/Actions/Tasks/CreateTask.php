<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\OrganizationSequence;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OrganizationSequence $sequence,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Validated task attributes.
     */
    public function handle(User $actor, array $attributes): Task
    {
        $task = DB::transaction(function () use ($actor, $attributes): Task {
            $status = TaskStatus::tryFrom((string) ($attributes['status'] ?? '')) ?? TaskStatus::Todo;

            $task = Task::query()->create([
                ...$attributes,
                'number' => $this->sequence->next('tasks'),
                'status' => $status,
                'reporter_id' => $actor->id,
                // New cards go to the bottom of their column.
                'position' => (float) (Task::query()->where('status', $status->value)->max('position') ?? 0) + 1024,
                'completed_at' => $status->isDone() ? now() : null,
            ]);

            $this->activity->log('task.created', $task, [
                'title' => $task->title,
                'reference' => $task->reference(),
            ], context: $task->project, actor: $actor);

            return $task;
        });

        if ($task->assignee_id !== null && $task->assignee_id !== $actor->id) {
            $task->assignee?->notify(TaskAssignedNotification::by($task, $actor));
        }

        return $task;
    }
}
