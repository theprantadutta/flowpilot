<?php

namespace App\Workflows\Engine;

use App\Enums\MembershipStatus;
use App\Enums\NodeType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StepRunStatus;
use App\Enums\WorkflowRunStatus;
use App\Jobs\AdvanceWorkflowRun;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Notifications\WorkflowCompletedNotification;
use App\Notifications\WorkflowFailedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\OrganizationSequence;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Definition\WorkflowDefinition;
use App\Workflows\Fields\Field;
use App\Workflows\Nodes\NodeHandler;
use App\Workflows\Nodes\NodeRegistry;
use App\Workflows\Nodes\StepContext;
use App\Workflows\Nodes\StepFailed;
use App\Workflows\Nodes\StepResult;
use App\Workflows\Support\RecordLinks;
use App\Workflows\Support\TemplateRenderer;
use App\Workflows\Triggers\Trigger;
use App\Workflows\Triggers\TriggerRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Runs workflows. The engine starts runs, walks each run through its
 * version's graph one step at a time, parks runs that are waiting, retries
 * steps that fail for passing reasons, and records everything on the run.
 *
 * Advancing always happens in the AdvanceWorkflowRun job, which makes sure
 * only one worker moves a given run at a time.
 */
class WorkflowEngine
{
    /**
     * Steps taken in one go before handing over to a fresh job.
     */
    public const int MAX_STEPS_PER_ADVANCE = 200;

    /**
     * Seconds to wait before retrying a step, by attempt.
     */
    public const array RETRY_BACKOFF = [1 => 30, 2 => 120, 3 => 600, 4 => 1800];

    public function __construct(
        private readonly NodeRegistry $nodes,
        private readonly TriggerRegistry $triggers,
        private readonly OrganizationSequence $sequence,
        private readonly Tenancy $tenancy,
        private readonly ActivityLogger $activity,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * Start a run of the workflow's published version.
     *
     * With an idempotency key, starting twice for the same event returns the
     * first run instead of creating another.
     *
     * @param  array<string, mixed>  $input  Normalized trigger input.
     */
    public function start(
        Workflow $workflow,
        ?Model $subject = null,
        array $input = [],
        ?User $actor = null,
        ?string $idempotencyKey = null,
        int $depth = 0,
    ): WorkflowRun {
        $organization = $this->tenancy->currentOrFail();
        $workflow->loadMissing('currentVersion');
        $version = $workflow->currentVersion ?? throw new LogicException("The workflow {$workflow->name} has not been published.");

        if ($idempotencyKey !== null && ($existing = $this->existingRun($workflow, $idempotencyKey))) {
            return $existing;
        }

        $trigger = $this->triggers->get($version->trigger_type);
        $graph = $version->graph();
        $triggerNode = $graph->trigger() ?? throw new LogicException('Published workflows always have a trigger.');

        try {
            $run = DB::transaction(function () use ($workflow, $version, $subject, $input, $actor, $idempotencyKey, $depth, $trigger, $triggerNode, $organization): WorkflowRun {
                $run = new WorkflowRun([
                    'workflow_id' => $workflow->id,
                    'workflow_version_id' => $version->id,
                    'number' => $this->sequence->next('workflow_runs'),
                    'status' => WorkflowRunStatus::Pending,
                    'trigger_type' => $version->trigger_type,
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => $subject ? (string) $subject->getKey() : null,
                    'subject_label' => $subject ? $trigger->subjectLabel($subject) : null,
                    'input' => $input === [] ? null : $input,
                    'current_node_id' => $triggerNode['id'],
                    'started_by' => $actor?->id,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $run->id = (string) Str::uuid7();

                $run->context = [
                    'trigger' => $trigger->key(),
                    'workflow' => ['id' => $workflow->id, 'name' => $workflow->name, 'version' => $version->version],
                    'organization' => ['name' => $organization->name],
                    'run' => ['id' => $run->id, 'reference' => $run->reference(), 'url' => RecordLinks::for($run, $organization)],
                    'actor' => ['id' => $actor?->id, 'name' => $actor?->name],
                    'input' => $input,
                    'subject' => $subject ? [...$trigger->snapshot($subject), 'url' => RecordLinks::for($subject, $organization)] : null,
                    'steps' => [],
                    'meta' => ['depth' => $depth],
                ];

                $run->save();

                $this->activity->log('workflow.run_started', $run, [
                    'workflow' => $workflow->name,
                    'reference' => $run->reference(),
                    'record' => $run->subject_label,
                ], context: $workflow, actor: $actor, actorType: $actor ? 'user' : 'system');

                return $run;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Two workers started the same event at once: the other one won.
            if ($idempotencyKey !== null && ($existing = $this->existingRun($workflow, $idempotencyKey))) {
                return $existing;
            }

            throw $exception;
        }

        AdvanceWorkflowRun::dispatch($organization->id, $run->id)->afterCommit();

        return $run;
    }

    /**
     * Move a run forward as far as it can go right now.
     */
    public function advance(WorkflowRun $run): void
    {
        $run->refresh();

        if ($run->status->isFinished()) {
            return;
        }

        $organization = $this->tenancy->currentOrFail();
        $run->load(['version', 'workflow']);
        $graph = $run->version->graph();
        $trigger = $this->triggers->get($run->trigger_type);
        $fields = $trigger->fields($graph->triggerConfig());

        for ($taken = 0; $taken < self::MAX_STEPS_PER_ADVANCE; $taken++) {
            $nodeId = $run->current_node_id;

            if ($nodeId === null) {
                $this->complete($run);

                return;
            }

            $node = $graph->node($nodeId);

            if ($node === null || ! $this->nodes->has($node['type'])) {
                $this->failRun($run, null, "The step \"{$nodeId}\" is missing from version {$run->version->version}.");

                return;
            }

            $step = $this->openStep($run, $node);

            // Parked until a set time (a delay, or a retry after a failure).
            if ($step->resume_at !== null && $step->resume_at->isFuture()) {
                return;
            }

            $handler = $this->nodes->get($node['type']);

            if (! $this->runStep($organization, $run, $step, $node, $handler, $graph, $trigger, $fields)) {
                return;
            }
        }

        AdvanceWorkflowRun::dispatch($organization->id, $run->id);
    }

    /**
     * Stop a run that has not finished. Steps not yet done are cancelled.
     */
    public function cancel(WorkflowRun $run, ?User $actor): bool
    {
        if ($run->status->isFinished()) {
            return false;
        }

        $run->loadMissing('workflow');

        DB::transaction(function () use ($run, $actor): void {
            $run->steps()
                ->whereIn('status', [StepRunStatus::Pending->value, StepRunStatus::Running->value, StepRunStatus::Waiting->value])
                ->update(['status' => StepRunStatus::Cancelled->value, 'resume_at' => null, 'completed_at' => now()]);

            $run->forceFill([
                'status' => WorkflowRunStatus::Cancelled,
                'cancelled_at' => now(),
                'current_node_id' => null,
            ])->save();

            $this->activity->log('workflow.run_cancelled', $run, [
                'workflow' => $this->workflowName($run),
                'reference' => $run->reference(),
            ], context: $run->workflow, actor: $actor);
        });

        return true;
    }

    /**
     * Try a failed run again from the step that failed.
     */
    public function retry(WorkflowRun $run, ?User $actor): bool
    {
        if ($run->status !== WorkflowRunStatus::Failed) {
            return false;
        }

        $run->loadMissing('workflow');

        DB::transaction(function () use ($run, $actor): void {
            $failed = $run->steps()->where('status', StepRunStatus::Failed->value)->orderByDesc('sequence')->first();

            $failed?->forceFill([
                'status' => StepRunStatus::Pending,
                'attempts' => 0,
                'error' => null,
                'resume_at' => null,
                'completed_at' => null,
            ])->save();

            $run->forceFill([
                'status' => WorkflowRunStatus::Running,
                'error' => null,
                'failed_at' => null,
            ])->save();

            $this->activity->log('workflow.run_retried', $run, [
                'workflow' => $this->workflowName($run),
                'reference' => $run->reference(),
            ], context: $run->workflow, actor: $actor);
        });

        AdvanceWorkflowRun::dispatch($run->organization_id, $run->id)->afterCommit();

        return true;
    }

    /**
     * Mark a run failed, record why, and tell the people who look after workflows.
     */
    public function failRun(WorkflowRun $run, ?WorkflowStepRun $step, string $error): void
    {
        if ($run->status->isFinished()) {
            return;
        }

        $run->loadMissing('workflow');

        $run->forceFill([
            'status' => WorkflowRunStatus::Failed,
            'error' => Str::limit($error, 1000),
            'failed_at' => now(),
        ])->save();

        $this->activity->log('workflow.run_failed', $run, [
            'workflow' => $this->workflowName($run),
            'reference' => $run->reference(),
            'step' => $step?->label,
            'error' => Str::limit($error, 300),
        ], context: $run->workflow, actorType: 'system');

        $organization = $this->tenancy->currentOrFail();

        if (! $organization->setting('workflows.notify_on_failure', true)) {
            return;
        }

        $recipients = $this->workflowOwners($organization);

        if ($run->started_by !== null && ! $recipients->contains('id', $run->started_by) && ($starter = User::query()->find($run->started_by))) {
            $recipients->push($starter);
        }

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new WorkflowFailedNotification($run, $step?->label, Str::limit($error, 300)));
        }
    }

    /**
     * @param  array{id: string, type: string, position: array{x: float|int, y: float|int}, data: array{label: string, config: array<string, mixed>}}  $node
     * @param  list<Field>  $fields
     * @return bool Whether the run can keep going straight away.
     */
    private function runStep(
        Organization $organization,
        WorkflowRun $run,
        WorkflowStepRun $step,
        array $node,
        NodeHandler $handler,
        WorkflowDefinition $graph,
        Trigger $trigger,
        array $fields,
    ): bool {
        $step->forceFill([
            'status' => StepRunStatus::Running,
            // Resuming a waiting step is not a new attempt.
            'attempts' => $step->status === StepRunStatus::Waiting ? max(1, $step->attempts) : $step->attempts + 1,
            'started_at' => $step->started_at ?? now(),
        ])->save();

        if ($run->status !== WorkflowRunStatus::Running) {
            $run->forceFill(['status' => WorkflowRunStatus::Running, 'started_at' => $run->started_at ?? now()])->save();
        }

        $context = new StepContext($organization, $run, $step, $node, $graph, $trigger, $run->context ?? [], $fields, $this->renderer);

        $perform = function () use ($handler, $context, $run, $step, $node, $graph): StepResult {
            $result = $handler->execute($context);
            $this->record($run, $step, $node, $graph, $result);

            return $result;
        };

        try {
            $result = $handler->transactional() ? DB::transaction($perform) : $perform();
        } catch (Throwable $exception) {
            $this->stepFailed($organization, $run, $step, $exception);

            return false;
        }

        return $result->finished;
    }

    /**
     * @param  array{id: string, type: string, position: array{x: float|int, y: float|int}, data: array{label: string, config: array<string, mixed>}}  $node
     */
    private function record(WorkflowRun $run, WorkflowStepRun $step, array $node, WorkflowDefinition $graph, StepResult $result): void
    {
        if (! $result->finished) {
            $step->forceFill([
                'status' => StepRunStatus::Waiting,
                'output' => $result->output ?: null,
                'error' => null,
                'resume_at' => $result->resumeAt,
                'waiting_on_type' => $result->waitingOnType,
                'waiting_on_id' => $result->waitingOnId,
            ])->save();

            $run->forceFill(['status' => WorkflowRunStatus::Waiting])->save();

            return;
        }

        $step->forceFill([
            'status' => StepRunStatus::Completed,
            'outcome' => $result->outcome,
            'output' => $result->output ?: null,
            'error' => null,
            'resume_at' => null,
            'completed_at' => now(),
        ])->save();

        $context = $run->context ?? [];
        $context['steps'][$node['id']] = $result->output;

        $run->forceFill([
            'context' => $context,
            'current_node_id' => $graph->next($node['id'], $result->outcome),
            'status' => WorkflowRunStatus::Running,
        ])->save();
    }

    private function stepFailed(Organization $organization, WorkflowRun $run, WorkflowStepRun $step, Throwable $exception): void
    {
        if ($exception instanceof StepFailed) {
            $retryable = $exception->retryable;
            $message = $exception->getMessage();
        } else {
            // Unexpected errors (a database blip, a bug) are reported, and tried again in case they pass.
            report($exception);
            $retryable = true;
            $message = 'Something went wrong while running this step. It has been reported.';
        }

        $step->refresh();
        $maxAttempts = max(1, (int) $organization->setting('workflows.max_step_attempts', 3));

        if ($retryable && $step->attempts < $maxAttempts) {
            $step->forceFill([
                'status' => StepRunStatus::Pending,
                'error' => Str::limit($message, 1000),
                'resume_at' => now()->addSeconds(self::RETRY_BACKOFF[$step->attempts] ?? 1800),
            ])->save();

            $run->forceFill(['status' => WorkflowRunStatus::Running])->save();

            return;
        }

        $step->forceFill([
            'status' => StepRunStatus::Failed,
            'error' => Str::limit($message, 1000),
            'resume_at' => null,
            'completed_at' => now(),
        ])->save();

        $this->failRun($run, $step, $message);
    }

    private function complete(WorkflowRun $run): void
    {
        $run->loadMissing('workflow');

        $run->forceFill([
            'status' => WorkflowRunStatus::Completed,
            'completed_at' => now(),
            'current_node_id' => null,
        ])->save();

        $this->activity->log('workflow.run_completed', $run, [
            'workflow' => $this->workflowName($run),
            'reference' => $run->reference(),
            'record' => $run->subject_label,
        ], context: $run->workflow, actorType: 'system');

        // People who start a run by hand hear when it is done.
        if ($run->started_by !== null && $run->trigger_type === 'manual' && ($starter = User::query()->find($run->started_by))) {
            $starter->notify(new WorkflowCompletedNotification($run, $run->steps()->where('status', StepRunStatus::Completed->value)->count()));
        }
    }

    /**
     * The step run for a node: the unfinished one if the run is resuming it,
     * otherwise a new one.
     *
     * @param  array{id: string, type: string, position: array{x: float|int, y: float|int}, data: array{label: string, config: array<string, mixed>}}  $node
     */
    private function openStep(WorkflowRun $run, array $node): WorkflowStepRun
    {
        $open = $run->steps()
            ->where('node_id', $node['id'])
            ->whereIn('status', [StepRunStatus::Pending->value, StepRunStatus::Running->value, StepRunStatus::Waiting->value])
            ->orderByDesc('sequence')
            ->first();

        if ($open) {
            return $open;
        }

        $type = NodeType::from($node['type']);

        return $run->steps()->create([
            'sequence' => (int) $run->steps()->max('sequence') + 1,
            'node_id' => $node['id'],
            'node_type' => $type,
            'label' => $node['data']['label'] !== '' ? $node['data']['label'] : $type->label(),
            'status' => StepRunStatus::Pending,
            'input' => $node['data']['config'] ?: null,
        ]);
    }

    private function existingRun(Workflow $workflow, string $idempotencyKey): ?WorkflowRun
    {
        return WorkflowRun::query()
            ->where('workflow_id', $workflow->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Active members who can publish workflows: the people who fix them.
     *
     * @return Collection<int, User>
     */
    private function workflowOwners(Organization $organization): Collection
    {
        $roles = array_map(
            fn (Role $role): string => $role->value,
            array_filter(Role::cases(), fn (Role $role): bool => $role->allows(Permission::WorkflowsPublish)),
        );

        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', MembershipStatus::Active)
            ->whereIn('role', array_values($roles))
            ->with('user')
            ->get()
            ->map(fn (OrganizationMembership $membership): User => $membership->user)
            ->values()
            ->toBase();
    }

    private function workflowName(WorkflowRun $run): string
    {
        return (string) data_get($run->context, 'workflow.name', 'A workflow');
    }
}
