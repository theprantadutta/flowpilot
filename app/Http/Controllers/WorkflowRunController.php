<?php

namespace App\Http\Controllers;

use App\Actions\Workflows\CancelWorkflowRun;
use App\Actions\Workflows\RetryWorkflowRun;
use App\Actions\Workflows\StartWorkflowRun;
use App\Enums\NodeType;
use App\Enums\WorkflowRunStatus;
use App\Http\Requests\Workflows\StartWorkflowRunRequest;
use App\Http\Resources\WorkflowRunResource;
use App\Http\Resources\WorkflowStepRunResource;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Support\Tenancy\Tenancy;
use App\Workflows\BuilderCatalog;
use App\Workflows\Definition\WorkflowDefinition;
use App\Workflows\Fields\Field;
use App\Workflows\Nodes\BranchHandler;
use App\Workflows\Support\TemplateRenderer;
use App\Workflows\Triggers\TriggerRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowRunController extends Controller
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WorkflowRun::class);

        $status = WorkflowRunStatus::tryFrom($request->string('status')->toString());
        $workflowId = $request->string('workflow')->toString();
        $workflowId = Str::isUuid($workflowId) ? $workflowId : null;

        $runs = WorkflowRun::query()
            ->with(['workflow:id,organization_id,name', 'version:id,organization_id,version', 'starter:id,name,avatar_path'])
            ->when($status, fn (Builder $query, WorkflowRunStatus $status) => $query->where('status', $status->value))
            ->when($workflowId, fn (Builder $query, string $id) => $query->where('workflow_id', $id))
            ->latest('created_at')
            ->orderByDesc('number')
            ->paginate(25)
            ->withQueryString();

        $activeStatuses = array_map(fn (WorkflowRunStatus $status): string => $status->value, WorkflowRunStatus::active());

        return Inertia::render('workflow-runs/Index', [
            'runs' => WorkflowRunResource::collection($runs),
            'filters' => ['status' => $status?->value, 'workflow' => $workflowId],
            'statuses' => WorkflowRunStatus::options(),
            'workflows' => fn () => Workflow::query()->orderBy('name')->get(['id', 'organization_id', 'name'])
                ->map(fn (Workflow $workflow): array => ['id' => $workflow->id, 'name' => $workflow->name]),
            // The list refreshes itself while anything on it is still moving.
            'hasActiveRuns' => WorkflowRun::query()->whereIn('status', $activeStatuses)->exists(),
        ]);
    }

    public function show(Request $request, WorkflowRun $run, TriggerRegistry $triggers, TemplateRenderer $renderer, BuilderCatalog $catalog): Response
    {
        Gate::authorize('view', $run);

        /** @var User $user */
        $user = $request->user();
        $run->load(['workflow:id,organization_id,name', 'version', 'starter:id,name,avatar_path']);
        $graph = $run->version->graph();
        $steps = $run->steps()->get();

        $deliveries = WebhookDelivery::query()
            ->whereIn('workflow_step_run_id', $steps->pluck('id'))
            ->get()
            ->keyBy('workflow_step_run_id');

        return Inertia::render('workflow-runs/Show', [
            'run' => (new WorkflowRunResource($run))->resolve($request),
            'steps' => $steps->map(fn (WorkflowStepRun $step): array => [
                ...(new WorkflowStepRunResource($step, $this->outcomeLabels($graph, $step->node_id)))->resolve($request),
                'delivery' => ($delivery = $deliveries->get($step->id)) ? [
                    'url' => $delivery->url,
                    'status' => $delivery->status,
                    'response_status' => $delivery->response_status,
                    'response_body' => $delivery->response_body,
                    'attempts' => $delivery->attempts,
                    'duration_ms' => $delivery->duration_ms,
                    'delivery_key' => $delivery->delivery_key,
                ] : null,
            ])->values(),
            'graph' => [
                ...$graph->toArray(),
                'trigger' => $run->version->trigger_type,
                'current' => $run->current_node_id,
            ],
            'catalog' => fn () => $catalog->toArray(),
            'input' => $this->inputSummary($run, $graph, $triggers, $renderer),
            'can' => [
                'cancel' => $user->can('cancel', $run),
                'retry' => $user->can('retry', $run),
                'editWorkflow' => $user->can('update', $run->workflow),
            ],
        ]);
    }

    public function store(StartWorkflowRunRequest $request, Workflow $workflow, StartWorkflowRun $startRun): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, mixed> $input */
        $input = $request->validated('input');

        $run = $startRun->handle($workflow, $user, $input, (string) $request->validated('request_key'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$run->reference()} started."]);

        return to_route('workflow-runs.show', ['run' => $run->id]);
    }

    public function cancel(Request $request, WorkflowRun $run, CancelWorkflowRun $cancelRun): RedirectResponse
    {
        Gate::authorize('cancel', $run);

        /** @var User $user */
        $user = $request->user();

        $cancelRun->handle($run, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$run->reference()} cancelled."]);

        return back();
    }

    public function retry(Request $request, WorkflowRun $run, RetryWorkflowRun $retryRun): RedirectResponse
    {
        Gate::authorize('retry', $run);

        /** @var User $user */
        $user = $request->user();

        $retryRun->handle($run, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Trying {$run->reference()} again."]);

        return back();
    }

    /**
     * Readable names for the paths out of a node ("Yes", "Approved", a branch case).
     *
     * @return array<string, string>
     */
    private function outcomeLabels(WorkflowDefinition $graph, string $nodeId): array
    {
        $node = $graph->node($nodeId);
        $type = $node !== null ? NodeType::tryFrom($node['type']) : null;

        if ($node === null || $type === null) {
            return [];
        }

        $labels = array_column($type->handles(), 'label', 'id');

        if ($type === NodeType::Branch) {
            foreach (BranchHandler::cases($node['data']['config']) as $case) {
                $labels[$case['id']] = $case['label'];
            }
        }

        return $labels;
    }

    /**
     * The values a person entered when starting the run, labelled and
     * formatted (amounts with their currency, members by name).
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    private function inputSummary(WorkflowRun $run, WorkflowDefinition $graph, TriggerRegistry $triggers, TemplateRenderer $renderer): array
    {
        if ($run->trigger_type !== 'manual' || $run->input === null) {
            return [];
        }

        $manual = $triggers->manual();
        $config = $graph->triggerConfig();
        $fields = $manual->fields($config);
        $organization = $this->tenancy->currentOrFail();

        return array_map(fn (array $input): array => [
            'key' => $input['key'],
            'label' => $input['label'],
            'value' => $renderer->format($run->input[$input['key']] ?? null, Field::find($fields, 'input.'.$input['key']), $organization),
        ], $manual->inputs($config));
    }
}
