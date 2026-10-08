<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Support\Activity\ActivityLogger;

/**
 * The steps inside a task. Ticking items is logged only when the last one is
 * done, so the activity feed is not flooded with every tick.
 */
class TaskChecklist
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function add(Task $task, string $body): TaskChecklistItem
    {
        return $task->checklistItems()->create([
            'body' => $body,
            'position' => (int) $task->checklistItems()->max('position') + 1,
        ]);
    }

    public function rename(TaskChecklistItem $item, string $body): TaskChecklistItem
    {
        $item->update(['body' => $body]);

        return $item;
    }

    public function toggle(TaskChecklistItem $item, bool $done, User $actor): TaskChecklistItem
    {
        $item->update([
            'is_done' => $done,
            'completed_by' => $done ? $actor->id : null,
            'completed_at' => $done ? now() : null,
        ]);

        $task = $item->task;
        $remaining = $task->checklistItems()->where('is_done', false)->count();

        if ($done && $remaining === 0) {
            $this->activity->log('task.checklist_completed', $task, [
                'title' => $task->title,
                'reference' => $task->reference(),
            ], context: $task->project, actor: $actor);
        }

        return $item;
    }

    public function remove(TaskChecklistItem $item): void
    {
        $item->delete();
    }
}
