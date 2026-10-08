<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttachmentRequest;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Files\AttachmentStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Files on projects, tasks and issues. Whoever can edit the record can add
 * files; whoever can see the record can download them.
 */
class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentStorage $storage,
        private readonly ActivityLogger $activity,
    ) {}

    public function storeForProject(StoreAttachmentRequest $request, Project $project): RedirectResponse
    {
        return $this->store($request, $project);
    }

    public function storeForTask(StoreAttachmentRequest $request, Task $task): RedirectResponse
    {
        return $this->store($request, $task);
    }

    public function storeForIssue(StoreAttachmentRequest $request, Issue $issue): RedirectResponse
    {
        return $this->store($request, $issue);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $attachable = $this->attachable($attachment);
        Gate::authorize('view', $attachable);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attachable = $this->attachable($attachment);

        abort_unless($attachment->uploaded_by === $user->id || $user->can('update', $attachable), 403);

        $this->activity->log('file.deleted', $attachable, [
            'title' => $this->label($attachable),
            'file' => $attachment->original_name,
        ], context: $this->context($attachable), actor: $user);

        $this->storage->delete($attachment);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$attachment->original_name} removed."]);

        return back();
    }

    private function store(StoreAttachmentRequest $request, Project|Task|Issue $attachable): RedirectResponse
    {
        Gate::authorize('update', $attachable);

        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');
        abort_if($file === null, 422);

        abort_if($attachable->attachments()->count() >= 50, 422, 'A record can have up to 50 files.');

        $attachment = $this->storage->store($attachable, $file, $user);

        $this->activity->log('file.uploaded', $attachable, [
            'title' => $this->label($attachable),
            'file' => $attachment->original_name,
        ], context: $this->context($attachable), actor: $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$attachment->original_name} uploaded."]);

        return back();
    }

    /**
     * The record a file belongs to, found through its tenant-scoped model, so
     * a file from another organization never resolves.
     */
    private function attachable(Attachment $attachment): Project|Task|Issue
    {
        $attachable = $attachment->attachable;

        abort_unless($attachable instanceof Project || $attachable instanceof Task || $attachable instanceof Issue, 404);

        return $attachable;
    }

    private function label(Project|Task|Issue $attachable): string
    {
        return $attachable instanceof Project ? $attachable->name : "{$attachable->reference()} {$attachable->title}";
    }

    private function context(Project|Task|Issue $attachable): ?Model
    {
        return $attachable instanceof Project ? null : $attachable->project;
    }
}
