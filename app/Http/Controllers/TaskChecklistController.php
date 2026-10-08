<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\TaskChecklist;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskChecklistController extends Controller
{
    public function __construct(private readonly TaskChecklist $checklist) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:500'],
        ], ['body.required' => 'Describe the step.']);

        abort_if($task->checklistItems()->count() >= 100, 422, 'A task can have up to 100 checklist items.');

        $this->checklist->add($task, $validated['body']);

        return back();
    }

    public function update(Request $request, Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:500'],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (array_key_exists('body', $validated)) {
            $this->checklist->rename($checklistItem, $validated['body']);
        }

        if (array_key_exists('is_done', $validated)) {
            $this->checklist->toggle($checklistItem, (bool) $validated['is_done'], $user);
        }

        return back();
    }

    public function destroy(Task $task, TaskChecklistItem $checklistItem): RedirectResponse
    {
        Gate::authorize('update', $task);

        $this->checklist->remove($checklistItem);

        return back();
    }
}
