<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\CreateWorkflow;
use App\Actions\Workflows\DeleteWorkflow;
use App\Actions\Workflows\UpdateWorkflowDetails;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStatus;
use App\Http\Requests\Workflows\StoreWorkflowRequest;
use App\Http\Requests\Workflows\UpdateWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Http\Resources\WorkflowRunResource;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use App\Support\Search\Contains;
use App\Support\Tenancy\Tenancy;
use App\Workflows\BuilderCatalog;
use App\Workflows\Definition\DefinitionValidator;
use App\Workflows\Templates\WorkflowTemplates;
use App\Workflows\Triggers\TriggerRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function index(Request $request, WorkflowTemplates $templates, TriggerRegistry $triggers): Response
    {
        Gate::authorize('viewAny', Workflow::class);

        /** @var User $user */
        $user = $request->user();
        $status = WorkflowStatus::tryFrom($request->string('status')->toString());
        $q = $request->string('q')->trim()->limit(80, '')->toString();

        $workflows = Workflow::query()
            ->with(['currentVersion:id,organization_id,workflow_id,version,checksum,trigger_type', 'editor:id,name,avatar_path'])
            ->withCount([
                'runs',
                'runs as failed_runs_count' => fn (Builder $runs) => $runs->where('status', WorkflowRunStatus::Failed->value)->where('created_at', '>=', now()->subDays(30)),
                'runs as active_runs_count' => fn (Builder $runs) => $runs->whereIn('status', array_map(fn (WorkflowRunStatus $status): string => $status->value, WorkflowRunStatus::active())),
            ])
            ->withMax('runs as last_run_at', 'created_at')
            ->when($q !== '', fn (Builder $query) => Contains::any($query, ['name', 'description'], $q))
            ->when($status, fn (Builder $query, WorkflowStatus $status) => $query->where('status', $status->value))
            // Archived workflows only show up when asked for.
            ->when($status === null, fn (Builder $query) => $query->where('status', '!=', WorkflowStatus::Archived->value))
            ->orderByRaw("case status when 'active' then 0 when 'draft' then 1 when 'paused' then 2 else 3 end")
            ->orderBy('name')
            ->limit(200)
            ->get();

        $activeStatuses = array_map(fn (WorkflowRunStatus $status): string => $status->value, WorkflowRunStatus::active());

        return Inertia::render('workflows/Index', [
            'workflows' => WorkflowResource::collection($workflows),
            'filters' => ['q' => $q, 'status' => $status?->value],
            'statuses' => WorkflowStatus::options(),
            'stats' => [
                'active' => Workflow::query()->where('status', WorkflowStatus::Active->value)->count(),
                'running' => WorkflowRun::query()->whereIn('status', $activeStatuses)->count(),
                'completed_week' => WorkflowRun::query()->where('status', WorkflowRunStatus::Completed->value)->where('completed_at', '>=', now()->subDays(7))->count(),
                'failed_week' => WorkflowRun::query()->where('status', WorkflowRunStatus::Failed->value)->where('failed_at', '>=', now()->subDays(7))->count(),
            ],
            'templates' => fn () => $templates->options(),
            'triggers' => fn () => array_map(fn (array $trigger): array => [
                'value' => $trigger['value'],
                'label' => $trigger['label'],
                'description' => $trigger['description'],
            ], $triggers->options()),
            'can' => ['create' => $user->can('create', Workflow::class)],
        ]);
    }

    public function store(StoreWorkflowRequest $request, CreateWorkflow $createWorkflow): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $workflow = $createWorkflow->handle(
            $user,
            (string) $request->validated('name'),
            $request->validated('description'),
            (string) ($request->validated('trigger') ?? 'manual'),
            $request->validated('template'),
        );

        return to_route('workflows.show', ['workflow' => $workflow->id]);
    }

    /**
     * The visual builder.
     */
    public function show(
        Request $request,
        Workflow $workflow,
        DefinitionValidator $validator,
        BuilderCatalog $catalog,
        TriggerRegistry $triggers,
    ): Response {
        Gate::authorize('view', $workflow);

        /** @var User $user */
        $user = $request->user();
        $workflow->load(['currentVersion', 'editor:id,name,avatar_path']);
        $draft = $workflow->draft();
        $trigger = $workflow->draftTrigger();
        $live = $workflow->currentVersion;

        return Inertia::render('workflows/Builder', [
            'workflow' => (new WorkflowResource($workflow))->resolve($request),
            'draft' => [...$draft->toArray(), 'trigger' => $trigger],
            'issues' => $validator->validate($draft, $trigger, $this->tenancy->currentOrFail()),
            'catalog' => fn () => $catalog->toArray(),
            'versions' => fn () => WorkflowVersion::query()
                ->where('workflow_id', $workflow->id)
                ->with('publisher:id,name,avatar_path')
                ->orderByDesc('version')
                ->limit(50)
                ->get()
                ->map(fn (WorkflowVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'notes' => $version->notes,
                    'trigger' => $triggers->find($version->trigger_type)?->label() ?? $version->trigger_type,
                    'steps' => count($version->graph()->nodes),
                    'published_at' => $version->published_at->toIso8601String(),
                    'publisher' => $version->publisher?->name,
                    'is_current' => $version->id === $workflow->current_version_id,
                ]),
            'recentRuns' => Inertia::defer(fn () => WorkflowRunResource::collection(
                $workflow->runs()->with('starter:id,name,avatar_path')->latest('created_at')->limit(8)->get(),
            )),
            // What a person fills in to start the published version by hand.
            'startForm' => $live !== null && $live->trigger_type === 'manual'
                ? ['inputs' => $triggers->manual()->inputs($live->graph()->triggerConfig())]
                : null,
            'can' => [
                'update' => $user->can('update', $workflow),
                'publish' => $user->can('publish', $workflow),
                'execute' => $user->can('execute', $workflow),
                'delete' => $user->can('delete', $workflow),
            ],
        ]);
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow, UpdateWorkflowDetails $updateWorkflow): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{name?: string, description?: string|null} $attributes */
        $attributes = $request->validated();

        $updateWorkflow->handle($workflow, $user, $attributes);

        return back();
    }

    public function destroy(Request $request, Workflow $workflow, DeleteWorkflow $deleteWorkflow): RedirectResponse
    {
        Gate::authorize('delete', $workflow);

        /** @var User $user */
        $user = $request->user();
        $name = $workflow->name;

        $deleteWorkflow->handle($workflow, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} deleted."]);

        return to_route('workflows.index');
    }
}
