<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "This task can't be finished until that one is." Dependencies must never
 * form a loop, or neither task could ever be done.
 */
class TaskDependencies
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function add(Task $task, Task $blocker, User $actor): void
    {
        if ($task->is($blocker)) {
            throw ValidationException::withMessages(['depends_on_id' => 'A task cannot wait on itself.']);
        }

        if ($this->wouldCreateLoop($task, $blocker)) {
            throw ValidationException::withMessages([
                'depends_on_id' => "{$blocker->reference()} already waits on {$task->reference()}, so this would create a loop.",
            ]);
        }

        $task->dependencies()->syncWithoutDetaching([$blocker->id]);

        $this->activity->log('task.dependency_added', $task, [
            'title' => $task->title,
            'reference' => $task->reference(),
            'blocker' => $blocker->reference(),
        ], context: $task->project, actor: $actor);
    }

    public function remove(Task $task, Task $blocker, User $actor): void
    {
        if ($task->dependencies()->detach($blocker->id) > 0) {
            $this->activity->log('task.dependency_removed', $task, [
                'title' => $task->title,
                'reference' => $task->reference(),
                'blocker' => $blocker->reference(),
            ], context: $task->project, actor: $actor);
        }
    }

    /**
     * True when the blocker already (directly or indirectly) waits on the task.
     */
    private function wouldCreateLoop(Task $task, Task $blocker): bool
    {
        $visited = [];
        $frontier = [$blocker->id];

        while ($frontier !== []) {
            $next = DB::table('task_dependencies')
                ->whereIn('task_id', $frontier)
                ->pluck('depends_on_id')
                ->map(fn (mixed $id): string => (string) $id)
                ->all();

            if (in_array($task->id, $next, true)) {
                return true;
            }

            $visited = [...$visited, ...$frontier];
            $frontier = array_values(array_diff(array_unique($next), $visited));
        }

        return false;
    }
}
