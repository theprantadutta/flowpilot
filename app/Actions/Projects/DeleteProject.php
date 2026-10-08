<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Files\AttachmentStorage;
use Illuminate\Support\Facades\DB;

class DeleteProject
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly AttachmentStorage $files,
    ) {}

    /**
     * Delete a project with its tasks and files. Issues are kept and simply
     * lose their project, because problems outlive the project they were found in.
     */
    public function handle(Project $project, User $actor): void
    {
        DB::transaction(function () use ($project, $actor): void {
            $taskCount = $project->tasks()->count();

            $this->activity->log('project.deleted', $project, [
                'name' => $project->name,
                'tasks' => $taskCount,
            ], actor: $actor);

            foreach ($project->tasks()->with('attachments')->get() as $task) {
                $this->files->deleteAll($task->attachments);
                $task->comments()->delete();
            }

            $this->files->deleteAll($project->attachments()->get());

            $project->delete();
        });
    }
}
