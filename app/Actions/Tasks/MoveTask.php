<?php

namespace App\Actions\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MoveTask
{
    public function __construct(private readonly UpdateTask $updateTask) {}

    /**
     * Move a card on the board: into a column, between two neighbours.
     *
     * The position is computed on the server from the neighbours the person
     * dropped it between, so two people reordering at once cannot corrupt the
     * order. When the gap between neighbours gets too small, the column is
     * renumbered.
     */
    public function handle(Task $task, User $actor, TaskStatus $status, ?Task $after, ?Task $before): Task
    {
        return DB::transaction(function () use ($task, $actor, $status, $after, $before): Task {
            $position = $this->positionBetween($after?->position, $before?->position);

            if ($position === null) {
                $this->renumber($status, $task);

                $after?->refresh();
                $before?->refresh();
                $position = $this->positionBetween($after?->position, $before?->position) ?? 1024.0;
            }

            return $this->updateTask->handle($task, $actor, [
                'status' => $status,
                'position' => $position,
            ]);
        });
    }

    private function positionBetween(?float $after, ?float $before): ?float
    {
        return match (true) {
            $after === null && $before === null => 1024.0,
            $after === null => $before - 1024.0,
            $before === null => $after + 1024.0,
            $before - $after < 0.0001 => null,
            default => ($after + $before) / 2,
        };
    }

    /**
     * Spread a column out evenly again.
     */
    private function renumber(TaskStatus $status, Task $moving): void
    {
        $position = 1024.0;

        Task::query()
            ->where('status', $status->value)
            ->whereKeyNot($moving->id)
            ->orderBy('position')
            ->lockForUpdate()
            ->get(['id', 'organization_id', 'position'])
            ->each(function (Task $task) use (&$position): void {
                $task->forceFill(['position' => $position])->saveQuietly();
                $position += 1024.0;
            });
    }
}
