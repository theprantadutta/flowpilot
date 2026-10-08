<?php

use App\Actions\Workflows\PublishWorkflow;
use App\Enums\IssueSeverity;
use App\Enums\Priority;
use App\Enums\Role;
use App\Enums\StepRunStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkflowRunStatus;
use App\Jobs\StartTriggeredWorkflows;
use App\Models\ActivityLog;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Task;
use App\Models\WebhookDelivery;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\WorkflowCompletedNotification;
use App\Notifications\WorkflowFailedNotification;
use App\Notifications\WorkflowMessageNotification;
use App\Workflows\Engine\WorkflowEngine;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

/**
 * Start a manual run as the organization's owner, inside the tenant.
 *
 * @param  array<string, mixed>  $input
 */
function startRun(Workflow $workflow, array $input = []): WorkflowRun
{
    $organization = $workflow->organization;

    return inTenant($organization, fn () => app(WorkflowEngine::class)->start($workflow, input: $input, actor: $organization->owner)->refresh());
}

/**
 * @return list<string>
 */
function stepsTaken(WorkflowRun $run): array
{
    return inTenant($run->organization, fn () => $run->steps()->pluck('node_id')->all());
}

describe('running', function () {
    it('follows the path chosen by a condition and records every step', function () {
        Notification::fake();
        $organization = Organization::factory()->create(['currency' => 'USD']);
        $finance = memberIn($organization, Role::Finance);

        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger', ['inputs' => [['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'required' => true]]]),
            step('large', 'condition', ['match' => 'all', 'rules' => [['field' => 'input.amount', 'operator' => 'greater_than', 'value' => '5000']]]),
            step('tell_finance', 'notification', [
                'recipients' => [['type' => 'role', 'role' => 'finance']],
                'title' => 'Large request of {{ input.amount }}',
                'message' => 'Started by {{ actor.name }}.',
            ]),
            step('small', 'end', ['summary' => 'Small request']),
            step('large_end', 'end', ['summary' => 'Sent to finance']),
        ], [
            path('trigger', 'large'),
            path('large', 'tell_finance', 'true'),
            path('large', 'small', 'false'),
            path('tell_finance', 'large_end'),
        ]);

        $run = startRun($workflow, ['amount' => 620000]);

        expect($run->status)->toBe(WorkflowRunStatus::Completed)
            ->and(stepsTaken($run))->toBe(['trigger', 'large', 'tell_finance', 'large_end'])
            ->and($run->context['steps']['large']['matched'])->toBeTrue()
            ->and($run->context['steps']['large_end']['summary'])->toBe('Sent to finance');

        Notification::assertSentTo($finance, WorkflowMessageNotification::class, fn ($notification) => $notification->messageTitle === 'Large request of $6,200.00'
            && $notification->message === "Started by {$organization->owner->name}."
            && $notification->workflowName === 'Test workflow');
        Notification::assertSentTo($organization->owner, WorkflowCompletedNotification::class);

        $small = startRun($workflow, ['amount' => 120000]);
        expect(stepsTaken($small))->toBe(['trigger', 'large', 'small']);
    });

    it('chooses the first matching branch case, or otherwise', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger', ['inputs' => [['key' => 'category', 'label' => 'Category', 'type' => 'select', 'required' => true, 'options' => [['value' => 'it', 'label' => 'IT'], ['value' => 'facilities', 'label' => 'Facilities'], ['value' => 'other', 'label' => 'Other']]]]]),
            step('route', 'branch', ['cases' => [
                ['id' => 'it', 'label' => 'IT', 'match' => 'all', 'rules' => [['field' => 'input.category', 'operator' => 'equals', 'value' => 'it']]],
                ['id' => 'facilities', 'label' => 'Facilities', 'match' => 'all', 'rules' => [['field' => 'input.category', 'operator' => 'equals', 'value' => 'facilities']]],
            ]]),
            step('to_it', 'end'),
            step('to_facilities', 'end'),
            step('elsewhere', 'end'),
        ], [
            path('trigger', 'route'),
            path('route', 'to_it', 'it'),
            path('route', 'to_facilities', 'facilities'),
            path('route', 'elsewhere', 'otherwise'),
        ]);

        expect(stepsTaken(startRun($workflow, ['category' => 'facilities'])))->toBe(['trigger', 'route', 'to_facilities'])
            ->and(stepsTaken(startRun($workflow, ['category' => 'other'])))->toBe(['trigger', 'route', 'elsewhere']);
    });

    it('ends the run when a path leads nowhere', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger'),
            step('check', 'condition', ['match' => 'all', 'rules' => [['field' => 'actor.id', 'operator' => 'is_empty', 'value' => null]]]),
        ], [path('trigger', 'check')]);

        $run = startRun($workflow);

        expect($run->status)->toBe(WorkflowRunStatus::Completed)
            ->and(stepsTaken($run))->toBe(['trigger', 'check']);
    });

    it('keeps running the version a run started on after a new version is published', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger'),
            step('wait', 'delay', ['amount' => 1, 'unit' => 'hours']),
            step('first_end', 'end'),
        ], [path('trigger', 'wait'), path('wait', 'first_end')]);

        $run = startRun($workflow);

        $published = inTenant($organization, function () use ($workflow, $organization) {
            $workflow->forceFill(['draft_definition' => [
                'trigger' => 'manual',
                'nodes' => [step('trigger', 'trigger'), step('second_end', 'end')],
                'edges' => [path('trigger', 'second_end')],
            ]])->save();

            return app(PublishWorkflow::class)->handle($workflow, $organization->owner);
        });

        travel(61)->minutes();
        artisan('workflows:resume')->assertSuccessful();

        $run->refresh();
        expect($published->version)->toBe(2)
            ->and($run->status)->toBe(WorkflowRunStatus::Completed)
            ->and(inTenant($organization, fn () => $run->version()->value('version')))->toBe(1)
            ->and(stepsTaken($run))->toBe(['trigger', 'wait', 'first_end'])
            ->and(stepsTaken(startRun(inTenant($organization, fn () => $workflow->refresh()))))->toBe(['trigger', 'second_end']);
    });
});

describe('records', function () {
    it('creates a task assigned to the least busy member of a role, as the workflow', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $busy = memberIn($organization, Role::Operations);
        $free = memberIn($organization, Role::Operations);
        // Numbered out of the way of the organization's sequence.
        inTenant($organization, fn () => Task::factory()->count(2)->sequence(['number' => 900], ['number' => 901])->create(['organization_id' => $organization->id, 'assignee_id' => $busy->id]));

        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger', ['inputs' => [['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]]]),
            step('setup', 'create_record', [
                'record' => 'task',
                'title' => 'Prepare a desk for {{ input.name }}',
                'priority' => 'high',
                'assignee' => ['type' => 'role', 'role' => 'operations'],
                'due_in_days' => 3,
                'tags' => ['Onboarding'],
            ]),
            step('done', 'end', ['summary' => 'Created {{ steps.setup.reference }}']),
        ], [path('trigger', 'setup'), path('setup', 'done')], name: 'New starter');

        $run = startRun($workflow, ['name' => 'Amara Okafor']);
        $task = inTenant($organization, fn () => Task::query()->where('title', 'Prepare a desk for Amara Okafor')->firstOrFail());

        expect($run->status)->toBe(WorkflowRunStatus::Completed)
            ->and($task->assignee_id)->toBe($free->id)
            ->and($task->priority)->toBe(Priority::High)
            ->and($task->reporter_id)->toBeNull()
            ->and($task->tags)->toBe(['onboarding'])
            ->and($task->due_date?->toDateString())->toBe(now()->addDays(3)->toDateString())
            ->and($run->context['steps']['done']['summary'])->toBe("Created {$task->reference()}");

        $log = inTenant($organization, fn () => ActivityLog::query()->where('action', 'task.created')->firstOrFail());
        expect($log->actor_type)->toBe('workflow')
            ->and($log->properties['workflow'])->toBe('New starter')
            ->and($log->properties['workflow_run'])->toBe($run->reference());

        Notification::assertSentTo($free, TaskAssignedNotification::class, fn ($notification) => $notification->assignedBy === 'New starter');
    });

    it('updates, assigns and tags the record that started the run', function () {
        $organization = Organization::factory()->create();
        $lead = memberIn($organization, Role::Manager);

        publishedWorkflow($organization, [
            step('trigger', 'trigger'),
            step('critical', 'update_record', ['field' => 'severity', 'value' => 'critical']),
            step('owner', 'assign', ['assignee' => ['type' => 'member', 'id' => $lead->id]]),
            step('tag', 'action', ['action' => 'add_tag', 'tag' => 'Escalated']),
            step('done', 'end'),
        ], [path('trigger', 'critical'), path('critical', 'owner'), path('owner', 'tag'), path('tag', 'done')], trigger: 'issue.created');

        actingAs($organization->owner)->post(route('issues.store', $organization), ['title' => 'Line 2 conveyor stopped', 'severity' => 'medium']);

        $issue = inTenant($organization, fn () => Issue::query()->firstOrFail());
        $run = inTenant($organization, fn () => WorkflowRun::query()->firstOrFail());

        expect($run->status)->toBe(WorkflowRunStatus::Completed)
            ->and($run->subject_id)->toBe($issue->id)
            ->and($run->subject_label)->toBe("{$issue->reference()} Line 2 conveyor stopped")
            ->and($run->context['subject']['severity'])->toBe('medium')
            ->and($issue->severity)->toBe(IssueSeverity::Critical)
            ->and($issue->assignee_id)->toBe($lead->id)
            ->and($issue->tags)->toBe(['escalated']);
    });

    it('fails the step, not the request, when the record was deleted', function () {
        $organization = Organization::factory()->create();
        publishedWorkflow($organization, [
            step('trigger', 'trigger'),
            step('wait', 'delay', ['amount' => 5, 'unit' => 'minutes']),
            step('tag', 'action', ['action' => 'add_tag', 'tag' => 'late']),
        ], [path('trigger', 'wait'), path('wait', 'tag')], trigger: 'task.created');

        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'Temporary']);
        inTenant($organization, fn () => Task::query()->delete());

        travel(6)->minutes();
        artisan('workflows:resume');

        $run = inTenant($organization, fn () => WorkflowRun::query()->firstOrFail());
        $failed = inTenant($organization, fn () => $run->steps()->where('node_id', 'tag')->firstOrFail());

        expect($run->status)->toBe(WorkflowRunStatus::Failed)
            ->and($failed->status)->toBe(StepRunStatus::Failed)
            ->and($failed->attempts)->toBe(1)
            ->and($failed->error)->toBe('The record this run is about has been deleted.');
    });
});

describe('waiting', function () {
    it('parks a run during a delay and resumes it once the time has passed', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger'),
            step('wait', 'delay', ['amount' => 2, 'unit' => 'hours']),
            step('done', 'end'),
        ], [path('trigger', 'wait'), path('wait', 'done')]);

        $run = startRun($workflow);
        $wait = inTenant($organization, fn () => WorkflowStepRun::query()->where('node_id', 'wait')->firstOrFail());

        expect($run->status)->toBe(WorkflowRunStatus::Waiting)
            ->and($wait->status)->toBe(StepRunStatus::Waiting)
            ->and($wait->resume_at?->diffInMinutes(now(), true))->toBeGreaterThan(119.0);

        travel(1)->hours();
        artisan('workflows:resume');
        expect($run->refresh()->status)->toBe(WorkflowRunStatus::Waiting);

        travel(61)->minutes();
        artisan('workflows:resume');

        expect($run->refresh()->status)->toBe(WorkflowRunStatus::Completed)
            ->and($wait->refresh()->status)->toBe(StepRunStatus::Completed)
            ->and($wait->attempts)->toBe(1);
    });

    it('cancels a waiting run so it never resumes', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger'),
            step('wait', 'delay', ['amount' => 10, 'unit' => 'minutes']),
            step('done', 'end'),
        ], [path('trigger', 'wait'), path('wait', 'done')]);

        $run = startRun($workflow);

        actingAs($organization->owner)
            ->post(route('workflow-runs.cancel', [$organization, $run]))
            ->assertRedirect();

        travel(11)->minutes();
        artisan('workflows:resume');

        expect($run->refresh()->status)->toBe(WorkflowRunStatus::Cancelled)
            ->and(stepsTaken($run))->toBe(['trigger', 'wait'])
            ->and(inTenant($organization, fn () => $run->steps()->where('node_id', 'wait')->value('status')))->toBe(StepRunStatus::Cancelled);
    });

    it('re-queues runs a stopped worker left behind', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);

        $run = inTenant($organization, fn () => WorkflowRun::factory()->for($workflow)->create([
            'organization_id' => $organization->id,
            'status' => WorkflowRunStatus::Running,
            'current_node_id' => 'trigger',
            'completed_at' => null,
            'context' => ['workflow' => ['name' => $workflow->name], 'steps' => [], 'meta' => ['depth' => 0]],
        ]));

        artisan('workflows:resume');
        expect($run->refresh()->status)->toBe(WorkflowRunStatus::Running);

        travel(11)->minutes();
        artisan('workflows:resume');
        expect($run->refresh()->status)->toBe(WorkflowRunStatus::Completed);
    });
});

describe('webhooks', function () {
    function webhookWorkflow(Organization $organization, string $url = 'https://93.184.216.34/hooks/flowpilot'): Workflow
    {
        return publishedWorkflow($organization, [
            step('trigger', 'trigger', ['inputs' => [['key' => 'po', 'label' => 'PO number', 'type' => 'text', 'required' => true]]]),
            step('send', 'webhook', ['url' => $url, 'fields' => [['key' => 'po_number', 'value' => '{{ input.po }}']]]),
            step('done', 'end'),
        ], [path('trigger', 'send'), path('send', 'done')], name: 'Sync purchase orders');
    }

    it('sends signed JSON with an idempotency key and logs the delivery', function () {
        Http::fake(['93.184.216.34/*' => Http::response(['received' => true], 202)]);
        $organization = Organization::factory()->create();

        $run = startRun(webhookWorkflow($organization), ['po' => 'PO-1842']);
        $delivery = inTenant($organization, fn () => WebhookDelivery::query()->firstOrFail());
        $secret = $organization->refresh()->webhookSecret();

        expect($run->status)->toBe(WorkflowRunStatus::Completed)
            ->and($delivery->status)->toBe(WebhookDelivery::STATUS_DELIVERED)
            ->and($delivery->response_status)->toBe(202)
            ->and($delivery->attempts)->toBe(1)
            ->and($secret)->toStartWith('whsec_');

        Http::assertSent(function (HttpRequest $request) use ($delivery, $secret, $run) {
            preg_match('/^t=(\d+),v1=([a-f0-9]{64})$/', $request->header('X-FlowPilot-Signature')[0], $signature);
            $body = json_decode($request->body(), true);

            return $request->header('Idempotency-Key')[0] === $delivery->delivery_key
                && hash_equals(hash_hmac('sha256', "{$signature[1]}.{$request->body()}", $secret), $signature[2])
                && $body['data'] === ['po_number' => 'PO-1842']
                && $body['run']['reference'] === $run->reference()
                && $body['id'] === $delivery->delivery_key;
        });
    });

    it('retries server errors with backoff, keeps the same key, then fails the run and tells admins', function () {
        Http::fake(['93.184.216.34/*' => Http::response('Service unavailable', 503)]);
        Notification::fake();
        $organization = Organization::factory()->create();
        $admin = memberIn($organization, Role::Admin);
        $employee = memberIn($organization, Role::Employee);

        $run = startRun(webhookWorkflow($organization), ['po' => 'PO-7']);
        $step = inTenant($organization, fn () => WorkflowStepRun::query()->where('node_id', 'send')->firstOrFail());

        expect($run->status)->toBe(WorkflowRunStatus::Running)
            ->and($step->status)->toBe(StepRunStatus::Pending)
            ->and($step->attempts)->toBe(1)
            ->and($step->error)->toBe('The receiving system answered 503.');

        // Not due yet: nothing is sent.
        artisan('workflows:resume');
        Http::assertSentCount(1);

        travel(1)->minutes();
        artisan('workflows:resume');
        travel(3)->minutes();
        artisan('workflows:resume');

        $run->refresh();
        $delivery = inTenant($organization, fn () => WebhookDelivery::query()->firstOrFail());
        $keys = collect(Http::recorded())->map(fn (array $pair) => $pair[0]->header('Idempotency-Key')[0])->unique();

        expect($run->status)->toBe(WorkflowRunStatus::Failed)
            ->and($run->error)->toBe('The receiving system answered 503.')
            ->and($step->refresh()->status)->toBe(StepRunStatus::Failed)
            ->and($step->attempts)->toBe(3)
            ->and($delivery->attempts)->toBe(3)
            ->and($delivery->status)->toBe(WebhookDelivery::STATUS_FAILED)
            ->and($keys->all())->toBe([$delivery->delivery_key]);

        Notification::assertSentTo([$organization->owner, $admin], WorkflowFailedNotification::class);
        Notification::assertNotSentTo($employee, WorkflowFailedNotification::class);
    });

    it('does not retry client errors', function () {
        Http::fake(['93.184.216.34/*' => Http::response('Not found', 404)]);
        $organization = Organization::factory()->create();

        $run = startRun(webhookWorkflow($organization), ['po' => 'PO-8']);

        expect($run->status)->toBe(WorkflowRunStatus::Failed)
            ->and($run->error)->toBe('The receiving system answered 404.');
        Http::assertSentCount(1);
    });

    it('refuses private and local addresses without sending anything', function (string $url) {
        Http::fake();
        $organization = Organization::factory()->create();

        $run = startRun(webhookWorkflow($organization, $url), ['po' => 'PO-9']);

        expect($run->status)->toBe(WorkflowRunStatus::Failed)
            ->and($run->error)->toBe('Webhooks cannot be sent to private or local addresses.');
        Http::assertNothingSent();
    })->with([
        'loopback' => 'https://127.0.0.1/hook',
        'private network' => 'https://10.0.0.12/hook',
        'cloud metadata' => 'https://169.254.169.254/latest/meta-data',
        'IPv6 loopback' => 'https://[::1]/hook',
        'localhost' => 'https://localhost/hook',
    ]);

    it('tries a failed run again from the failed step', function () {
        Http::fake(['93.184.216.34/*' => Http::sequence()->push('Gone', 410)->push('ok', 200)]);
        $organization = Organization::factory()->create();

        $run = startRun(webhookWorkflow($organization), ['po' => 'PO-10']);
        expect($run->status)->toBe(WorkflowRunStatus::Failed);

        actingAs($organization->owner)
            ->post(route('workflow-runs.retry', [$organization, $run]))
            ->assertRedirect();

        expect($run->refresh()->status)->toBe(WorkflowRunStatus::Completed)
            ->and(stepsTaken($run))->toBe(['trigger', 'send', 'done']);
    });
});

describe('triggers', function () {
    it('starts matching workflows when a task is created, once per task', function () {
        $organization = Organization::factory()->create();
        publishedWorkflow($organization, [
            step('trigger', 'trigger'),
            step('urgent', 'condition', ['match' => 'all', 'rules' => [['field' => 'subject.priority', 'operator' => 'equals', 'value' => 'urgent']]]),
            step('flag', 'action', ['action' => 'add_tag', 'tag' => 'needs-owner']),
        ], [path('trigger', 'urgent'), path('urgent', 'flag', 'true')], trigger: 'task.created');
        $paused = publishedWorkflow($organization, [step('trigger', 'trigger')], [], trigger: 'task.created');
        $paused->forceFill(['status' => 'paused'])->save();

        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'Replace hydraulic seal', 'priority' => 'urgent']);
        $task = inTenant($organization, fn () => Task::query()->firstOrFail());

        // The same event delivered twice starts nothing new.
        StartTriggeredWorkflows::dispatch($organization->id, 'task.created', 'task', $task->id, $organization->owner_id);

        $runs = inTenant($organization, fn () => WorkflowRun::query()->get());

        expect($runs)->toHaveCount(1)
            ->and($runs->first()->started_by)->toBe($organization->owner_id)
            ->and($runs->first()->idempotency_key)->toBe("task.created:{$task->id}")
            ->and($task->refresh()->tags)->toBe(['needs-owner']);
    });

    it('starts task completed workflows each time a task is finished', function () {
        $organization = Organization::factory()->create();
        publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')], trigger: 'task.completed');
        $task = inTenant($organization, fn () => Task::factory()->create(['organization_id' => $organization->id]));

        actingAs($organization->owner)->patch(route('tasks.update', [$organization, $task]), ['status' => TaskStatus::Done->value]);
        actingAs($organization->owner)->patch(route('tasks.update', [$organization, $task]), ['status' => TaskStatus::InProgress->value]);
        travel(1)->seconds();
        actingAs($organization->owner)->patch(route('tasks.update', [$organization, $task]), ['status' => TaskStatus::Done->value]);

        expect(inTenant($organization, fn () => WorkflowRun::query()->count()))->toBe(2);
    });

    it('stops workflows from setting each other off forever', function () {
        $organization = Organization::factory()->create();

        foreach (['Ping', 'Pong'] as $name) {
            publishedWorkflow($organization, [
                step('trigger', 'trigger'),
                step('spawn', 'create_record', ['record' => 'task', 'title' => "{$name} follow-up"]),
            ], [path('trigger', 'spawn')], trigger: 'task.created', name: $name);
        }

        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'First']);

        // Two workflows at each of four levels (0–3), then the chain stops.
        expect(inTenant($organization, fn () => WorkflowRun::query()->count()))->toBe(8)
            ->and(inTenant($organization, fn () => Task::query()->count()))->toBe(9)
            ->and(inTenant($organization, fn () => WorkflowRun::query()->where('status', '!=', 'completed')->count()))->toBe(0);
    });

    it('never starts another organization\'s workflows', function () {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        publishedWorkflow($other, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')], trigger: 'task.created');

        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'Mine']);

        expect(inTenant($other, fn () => WorkflowRun::query()->count()))->toBe(0);
    });
});
