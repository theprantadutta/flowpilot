<?php

namespace App\Http\Controllers;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\UpdateProject;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\IssueResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Activity\ActivityPresenter;
use App\Support\FormOptions;
use App\Support\Search\Contains;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public const array SORTS = ['name', 'due_date', 'updated_at', 'created_at', 'priority'];

    public const array TABS = ['overview', 'tasks', 'timeline', 'issues', 'files', 'activity'];

    public function index(Request $request, FormOptions $options): Response
    {
        Gate::authorize('viewAny', Project::class);

        /** @var User $user */
        $user = $request->user();

        $filters = [
            'q' => $request->string('q')->trim()->limit(80, '')->toString(),
            'status' => ProjectStatus::tryFrom($request->string('status')->toString())?->value,
            'mine' => $request->boolean('mine'),
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'updated_at',
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];

        $projects = Project::query()
            ->with('owner:id,name,avatar_path')
            ->withProgress()
            ->when($filters['q'] !== '', fn (Builder $query) => Contains::any($query, ['name', 'description'], $filters['q']))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status),
                fn (Builder $query) => $query->where('status', '!=', ProjectStatus::Archived->value))
            ->when($filters['mine'], fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id))))
            ->when($filters['sort'] === 'priority',
                fn (Builder $query) => $filters['direction'] === 'asc'
                    ? $query->orderByRaw("case priority when 'urgent' then 4 when 'high' then 3 when 'medium' then 2 else 1 end asc")
                    : $query->orderByRaw("case priority when 'urgent' then 4 when 'high' then 3 when 'medium' then 2 else 1 end desc"),
                fn (Builder $query) => $query->orderBy($filters['sort'], $filters['direction']))
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('projects/Index', [
            'projects' => ProjectResource::collection($projects),
            'filters' => $filters,
            'counts' => Project::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'statuses' => ProjectStatus::options(),
            'priorities' => Priority::options(),
            'members' => fn () => $options->members(),
            'can' => ['create' => $user->can('create', Project::class)],
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProject $createProject): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $project = $createProject->handle($user, $request->projectAttributes(), $request->memberIds() ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$project->name} created."]);

        return to_route('projects.show', ['project' => $project->id]);
    }

    public function show(Request $request, Project $project, FormOptions $options, ActivityPresenter $presenter, Tenancy $tenancy): Response
    {
        Gate::authorize('view', $project);

        /** @var User $user */
        $user = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'overview';
        $today = now($tenancy->currentOrFail()->timezone)->toDateString();

        $project->load(['owner:id,name,avatar_path', 'members:id,name,avatar_path'])->loadCount([
            'tasks',
            'tasks as done_tasks_count' => fn (Builder $tasks) => $tasks->where('status', TaskStatus::Done->value),
            'issues as open_issues_count' => fn (Builder $issues) => $issues->whereIn('status', [IssueStatus::Open->value, IssueStatus::Investigating->value]),
        ]);

        return Inertia::render('projects/Show', [
            'project' => new ProjectResource($project),
            'tab' => $tab,
            'summary' => fn () => [
                'by_status' => $project->tasks()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'overdue' => $project->tasks()->overdue($today)->count(),
                'due_this_week' => $project->tasks()->open()->whereBetween('due_date', [$today, now($tenancy->currentOrFail()->timezone)->addDays(7)->toDateString()])->count(),
                'critical_issues' => $project->issues()->open()->where('severity', IssueSeverity::Critical->value)->count(),
            ],
            'tasks' => Inertia::optional(fn () => TaskResource::collection(
                $project->tasks()
                    ->with(['assignee:id,name,avatar_path'])
                    ->withCount(['checklistItems', 'checklistItems as done_checklist_items_count' => fn (Builder $items) => $items->where('is_done', true), 'comments'])
                    ->orderBy('position')
                    ->get(),
            )),
            'timeline' => Inertia::optional(fn () => TaskResource::collection(
                $project->tasks()
                    ->with('assignee:id,name,avatar_path')
                    ->whereNotNull('due_date')
                    ->orderBy('due_date')
                    ->get(),
            )),
            'issues' => Inertia::optional(fn () => IssueResource::collection(
                $project->issues()
                    ->with('assignee:id,name,avatar_path')
                    ->orderByRaw("case status when 'open' then 1 when 'investigating' then 2 when 'resolved' then 3 else 4 end")
                    ->orderByRaw("case severity when 'critical' then 4 when 'high' then 3 when 'medium' then 2 else 1 end desc")
                    ->get(),
            )),
            'files' => Inertia::optional(fn () => $this->projectFiles($project)),
            'activity' => Inertia::optional(fn () => ActivityLog::query()
                ->about($project)
                ->with('actor:id,name,avatar_path')
                ->latest('created_at')
                ->limit(50)
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))),
            'statuses' => ProjectStatus::options(),
            'priorities' => Priority::options(),
            'taskStatuses' => TaskStatus::options(),
            'severities' => IssueSeverity::options(),
            'members' => fn () => $options->members(),
            'canCreateTasks' => $user->can('create', Task::class),
            'canCreateIssues' => $user->can('create', Issue::class),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $updateProject): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateProject->handle($project, $user, $request->projectAttributes(), $request->memberIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project updated.']);

        return back();
    }

    public function destroy(Request $request, Project $project, DeleteProject $deleteProject): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $request->validate([
            'confirm_name' => ['required', 'string', Rule::in([$project->name])],
        ], [
            'confirm_name.in' => 'Type the project name exactly to confirm.',
            'confirm_name.required' => 'Type the project name to confirm.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $name = $project->name;

        $deleteProject->handle($project, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} deleted."]);

        return to_route('projects.index');
    }

    /**
     * Files attached to the project itself and to its tasks.
     *
     * @return list<array<string, mixed>>
     */
    private function projectFiles(Project $project): array
    {
        $taskIds = $project->tasks()->pluck('id');

        return array_values(Attachment::query()
            ->with('uploader:id,name')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $q) => $q->where('attachable_type', $project->getMorphClass())->where('attachable_id', $project->id))
                ->orWhere(fn (Builder $q) => $q->where('attachable_type', 'task')->whereIn('attachable_id', $taskIds)))
            ->latest()
            ->get()
            ->map(fn (Attachment $attachment): array => [
                ...$attachment->toFileArray(),
                'source' => $attachment->attachable_type === 'task' ? 'task' : 'project',
            ])
            ->all());
    }
}
