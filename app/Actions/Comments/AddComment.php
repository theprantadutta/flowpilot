<?php

namespace App\Actions\Comments;

use App\Models\Approval;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Str;

class AddComment
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(Task|Issue|Approval $commentable, User $author, string $body): Comment
    {
        $comment = $commentable->comments()->create([
            'author_id' => $author->id,
            'body' => $body,
        ]);

        $this->activity->log("{$commentable->getMorphClass()}.commented", $commentable, [
            'title' => $commentable->title,
            'reference' => $commentable->reference(),
            'excerpt' => Str::limit($body, 140),
        ], context: $commentable instanceof Approval ? null : $commentable->project, actor: $author);

        return $comment;
    }
}
