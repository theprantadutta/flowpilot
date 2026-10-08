<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Files\AttachmentStorage;
use Illuminate\Support\Facades\DB;

class DeleteIssue
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly AttachmentStorage $files,
    ) {}

    public function handle(Issue $issue, User $actor): void
    {
        DB::transaction(function () use ($issue, $actor): void {
            $this->activity->log('issue.deleted', $issue, [
                'title' => $issue->title,
                'reference' => $issue->reference(),
            ], context: $issue->project, actor: $actor);

            $this->files->deleteAll($issue->attachments()->get());
            $issue->comments()->delete();
            $issue->delete();
        });
    }
}
