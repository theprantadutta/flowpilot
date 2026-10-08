<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Files\AttachmentStorage;
use Illuminate\Support\Facades\DB;

class DeleteTask
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly AttachmentStorage $files,
    ) {}

    public function handle(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor): void {
            $this->activity->log('task.deleted', $task, [
                'title' => $task->title,
                'reference' => $task->reference(),
            ], context: $task->project, actor: $actor);

            $this->files->deleteAll($task->attachments()->get());
            $task->comments()->delete();
            $task->delete();
        });
    }
}
