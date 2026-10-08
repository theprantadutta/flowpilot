<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\TaskDependencies;
use App\Models\Task;
use App\Models\User;
use App\Rules\BelongsToCurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskDependencyController extends Controller
{
    public function __construct(private readonly TaskDependencies $dependencies) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'depends_on_id' => ['required', new BelongsToCurrentOrganization(Task::class, 'Choose a task from this organization.')],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->dependencies->add($task, Task::query()->findOrFail((string) $validated['depends_on_id']), $user);

        return back();
    }

    public function destroy(Request $request, Task $task, Task $blocker): RedirectResponse
    {
        Gate::authorize('update', $task);

        /** @var User $user */
        $user = $request->user();

        $this->dependencies->remove($task, $blocker, $user);

        return back();
    }
}
