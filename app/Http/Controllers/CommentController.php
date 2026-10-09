<?php

namespace App\Http\Controllers;

use App\Actions\Comments\AddComment;
use App\Models\Approval;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function storeForTask(Request $request, Task $task, AddComment $addComment): RedirectResponse
    {
        Gate::authorize('view', $task);

        return $this->store($request, $task, $addComment);
    }

    public function storeForIssue(Request $request, Issue $issue, AddComment $addComment): RedirectResponse
    {
        Gate::authorize('view', $issue);

        return $this->store($request, $issue, $addComment);
    }

    public function storeForApproval(Request $request, Approval $approval, AddComment $addComment): RedirectResponse
    {
        Gate::authorize('view', $approval);

        return $this->store($request, $approval, $addComment);
    }

    /**
     * People can remove their own comments; the record of what happened stays
     * in the activity log.
     */
    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($comment->author_id === $user->id, 403, 'You can only delete your own comments.');

        $comment->delete();

        return back();
    }

    private function store(Request $request, Task|Issue|Approval $commentable, AddComment $addComment): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], ['body.required' => 'Write something before posting.']);

        /** @var User $user */
        $user = $request->user();

        $addComment->handle($commentable, $user, trim($validated['body']));

        return back();
    }
}
