<?php

namespace App\Http\Controllers;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\MoveTask;
use App\Actions\Tasks\UpdateTask;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Http\Requests\Tasks\MoveTaskRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity\ActivityPresenter;
use App\Support\FormOptions;
use App\Support\Search\Contains;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public const array SORTS = ['due_date', 'priority', 'updated_at', 'created_at', 'number'];

    public function __construct(private readonly Tenancy $tenancy) {}

    public function index(Request $request, FormOptions $options): Response
    {
        Gate::authorize('viewAny', Task::class);

        /** @var User $user */
        $user = $request->user();
        $view = $request->query('view') === 'board' ? 'board' : 'list';
        $filters = $this->filters($request);
        $today = now($this->tenancy->currentOrFail()->timezone)->toDateString();

        $query = $this->filteredQuery($filters, $user, $today)
            ->with(['assignee:id,name,avatar_path', 'project:id,organization_id,name'])
            ->withCount([
                'checklistItems',
                'checklistItems as done_checklist_items_count' => fn (Builder $items) => $items->where('is_done', true),
                'comments',
            ]);

        if ($view === 'board') {
            $tasks = $query
                // Keep the Done column to recent work.
                ->where(fn (Builder $query) => $query
                    ->where('status', '!=', TaskStatus::Done->value)
                    ->orWhere('completed_at', '>=', now()->subDays(14)))
                ->orderBy('position')
                ->limit(500)
                ->get();

            $payload = ['board' => TaskResource::collection($tasks)];
        } else {
            $tasks = $this->sorted($query, $filters['sort'], $filters['direction'])->paginate(25)->withQueryString();
            $payload = ['list' => TaskResource::collection($tasks)];
        }

        return Inertia::render('tasks/Index', [
            ...$payload,
            'view' => $view,
            'filters' => $filters,
            'statuses' => TaskStatus::options(),
            'priorities' => Priority::options(),
            'members' => fn () => $options->members(),
            'projects' => fn () => $options->projects(),
            'counts' => [
                'mine' => Task::query()->open()->where('assignee_id', $user->id)->count(),
                'overdue' => Task::query()->overdue($today)->count(),
            ],
            'can' => ['create' => $user->can('create', Task::class)],
        ]);
    }

    public function store(StoreTaskRequest $request, CreateTask $createTask): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $task = $createTask->handle($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$task->reference()} created."]);

        return back();
    }

    public function show(Request $request, Task $task, FormOptions $options, ActivityPresenter $presenter): Response
    {
        Gate::authorize('view', $task);

        /** @var User $user */
        $user = $request->user();

        $task->load([
            'project:id,organization_id,name',
            'assignee:id,name,avatar_path',
            'reporter:id,name,avatar_path',
            'checklistItems',
            'dependencies:id,organization_id,number,title,status',
            'dependents:id,organization_id,number,title,status',
        ]);

        return Inertia::render('tasks/Show', [
            'task' => new TaskResource($task),
            'comments' => $task->comments()->with('author:id,name,avatar_path')->get()
                ->map(fn (Comment $comment): array => $comment->toCommentArray($user)),
            'attachments' => $task->attachments()->with('uploader:id,name')->get()
                ->map(fn (Attachment $attachment): array => [
                    ...$attachment->toFileArray(),
                    'can_delete' => $attachment->uploaded_by === $user->id || $user->can('update', $task),
                ]),
            'activity' => Inertia::defer(fn () => ActivityLog::query()
                ->where('subject_type', $task->getMorphClass())
                ->where('subject_id', $task->id)
                ->with('actor:id,name,avatar_path')
                ->latest('created_at')
                ->limit(30)
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))),
            'dependencyOptions' => fn () => Task::query()
                ->whereKeyNot($task->id)
                ->open()
                ->when($task->project_id, fn (Builder $query) => $query->orderByRaw('case when project_id = ? then 0 else 1 end', [$task->project_id]))
                ->orderByDesc('number')
                ->limit(100)
                ->get(['id', 'organization_id', 'number', 'title'])
                ->map(fn (Task $option): array => ['id' => $option->id, 'label' => "{$option->reference()} {$option->title}"]),
            'statuses' => TaskStatus::options(),
            'priorities' => Priority::options(),
            'members' => fn () => $options->members(),
            'projects' => fn () => $options->projects(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $updateTask): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateTask->handle($task, $user, $request->validated());

        return back();
    }

    /**
     * A drag on the board. Answers with the saved position so the board can
     * keep its local order without reloading.
     */
    public function move(MoveTaskRequest $request, Task $task, MoveTask $moveTask): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $task = $moveTask->handle($task, $user, $request->status(), $request->neighbour('after_id'), $request->neighbour('before_id'));

        if ($request->header('X-Inertia')) {
            return back();
        }

        return response()->json([
            'id' => $task->id,
            'status' => $task->status->value,
            'position' => $task->position,
        ]);
    }

    public function destroy(Request $request, Task $task, DeleteTask $deleteTask): RedirectResponse
    {
        Gate::authorize('delete', $task);

        /** @var User $user */
        $user = $request->user();
        $reference = $task->reference();
        $projectId = $task->project_id;

        $deleteTask->handle($task, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$reference} deleted."]);

        return $request->boolean('return_to_project') && $projectId
            ? to_route('projects.show', ['project' => $projectId, 'tab' => 'tasks'])
            : to_route('tasks.index');
    }

    /**
     * @return array{q: string, status: string|null, priority: string|null, assignee: string|null, project: string|null, due: string|null, sort: string, direction: 'asc'|'desc'}
     */
    private function filters(Request $request): array
    {
        $assignee = $request->string('assignee')->toString();
        $project = $request->string('project')->toString();
        $due = $request->string('due')->toString();

        return [
            'q' => $request->string('q')->trim()->limit(80, '')->toString(),
            'status' => TaskStatus::tryFrom($request->string('status')->toString())?->value,
            'priority' => Priority::tryFrom($request->string('priority')->toString())?->value,
            'assignee' => $assignee === 'me' || $assignee === 'unassigned' || ctype_digit($assignee) ? $assignee : null,
            'project' => Str::isUuid($project) ? $project : null,
            'due' => in_array($due, ['overdue', 'today', 'week'], true) ? $due : null,
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'due_date',
            'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * @param  array{q: string, status: string|null, priority: string|null, assignee: string|null, project: string|null, due: string|null, sort: string, direction: 'asc'|'desc'}  $filters
     * @return Builder<Task>
     */
    private function filteredQuery(array $filters, User $user, string $today): Builder
    {
        return Task::query()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $number = ltrim(strtoupper($filters['q']), 'T-');

                $query->where(fn (Builder $query) => ctype_digit($number)
                    ? $query->where('number', (int) $number)->orWhere(fn (Builder $q) => Contains::any($q, ['title'], $filters['q']))
                    : Contains::any($query, ['title', 'description'], $filters['q']));
            })
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'], fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['assignee'] === 'me', fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->when($filters['assignee'] === 'unassigned', fn (Builder $query) => $query->whereNull('assignee_id'))
            ->when($filters['assignee'] !== null && ctype_digit($filters['assignee']), fn (Builder $query) => $query->where('assignee_id', (int) $filters['assignee']))
            ->when($filters['project'], fn (Builder $query, string $project) => $query->where('project_id', $project))
            ->when($filters['due'] === 'overdue', fn (Builder $query) => $query->overdue($today))
            ->when($filters['due'] === 'today', fn (Builder $query) => $query->open()->whereDate('due_date', $today))
            ->when($filters['due'] === 'week', fn (Builder $query) => $query->open()->whereBetween('due_date', [$today, now($this->tenancy->currentOrFail()->timezone)->addDays(7)->toDateString()]));
    }

    /**
     * @param  Builder<Task>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<Task>
     */
    private function sorted(Builder $query, string $sort, string $direction): Builder
    {
        if ($sort === 'priority') {
            // "Ascending" priority means most urgent first.
            $direction === 'asc'
                ? $query->orderByRaw("case priority when 'urgent' then 4 when 'high' then 3 when 'medium' then 2 else 1 end desc")
                : $query->orderByRaw("case priority when 'urgent' then 4 when 'high' then 3 when 'medium' then 2 else 1 end asc");
        } elseif ($sort === 'due_date') {
            // Undated tasks after dated ones, whichever way the list is sorted.
            $query->orderByRaw('case when due_date is null then 1 else 0 end')->orderBy('due_date', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->orderByDesc('number');
    }
}
