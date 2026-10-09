<?php

use App\Enums\Role;
use App\Enums\WorkflowRunStatus;
use App\Enums\WorkflowStatus;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Task;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function draftOf(Organization $organization, Workflow $workflow): array
{
    return inTenant($organization, fn () => $workflow->refresh()->draft_definition);
}

describe('creating and editing', function () {
    it('lists workflows with their trigger, version and run counts', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')], name: 'Purchase approval');
        inTenant($organization, fn () => WorkflowRun::factory()->for($workflow)->count(2)->sequence(['number' => 1], ['number' => 2])->create(['organization_id' => $organization->id]));
        Workflow::factory()->for($organization)->create(['name' => 'Old process', 'status' => WorkflowStatus::Archived]);

        actingAs($organization->owner)
            ->get(route('workflows.index', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->component('workflows/Index')
                ->has('workflows.data', 1)
                ->where('workflows.data.0.name', 'Purchase approval')
                ->where('workflows.data.0.trigger.label', 'Started by a person')
                ->where('workflows.data.0.version', 1)
                ->where('workflows.data.0.runs_count', 2)
                ->where('stats.active', 1)
                ->has('templates', 8));
    });

    it('creates a workflow from a template and opens the builder', function () {
        $organization = Organization::factory()->create();

        $response = actingAs($organization->owner)->post(route('workflows.store', $organization), [
            'name' => 'Critical issues',
            'template' => 'critical-issue-escalation',
        ]);

        $workflow = inTenant($organization, fn () => Workflow::query()->firstOrFail());

        $response->assertRedirect(route('workflows.show', [$organization, $workflow]));
        expect($workflow->status)->toBe(WorkflowStatus::Draft)
            ->and($workflow->trigger_type)->toBe('issue.created')
            ->and($workflow->template)->toBe('critical-issue-escalation')
            ->and($workflow->created_by)->toBe($organization->owner_id)
            ->and(count($workflow->draft_definition['nodes']))->toBe(7);

        actingAs($organization->owner)
            ->get(route('workflows.show', [$organization, $workflow]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('workflows/Builder')
                ->where('draft.trigger', 'issue.created')
                ->where('issues', [])
                ->has('catalog.nodeTypes')
                ->where('can.publish', true));
    });

    it('creates a blank workflow with only a trigger', function () {
        $organization = Organization::factory()->create();

        actingAs($organization->owner)->post(route('workflows.store', $organization), ['name' => 'Supplier onboarding', 'trigger' => 'task.created']);

        $workflow = inTenant($organization, fn () => Workflow::query()->firstOrFail());
        expect($workflow->trigger_type)->toBe('task.created')
            ->and(array_column($workflow->draft_definition['nodes'], 'type'))->toBe(['trigger']);
    });

    it('validates new workflows', function () {
        $organization = Organization::factory()->create();

        actingAs($organization->owner)
            ->post(route('workflows.store', $organization), ['name' => '', 'trigger' => 'payroll.exploded', 'template' => 'nope'])
            ->assertSessionHasErrors(['name' => 'Give the workflow a name.', 'trigger', 'template']);
    });

    it('saves the draft without touching the live version', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);

        actingAs($organization->owner)
            ->put(route('workflows.draft.update', [$organization, $workflow]), [
                'trigger' => 'task.created',
                'definition' => [
                    'nodes' => [step('trigger', 'trigger'), step('wait', 'delay', ['amount' => 1, 'unit' => 'days'])],
                    'edges' => [path('trigger', 'wait')],
                ],
            ])
            ->assertRedirect();

        $workflow = inTenant($organization, fn () => $workflow->refresh()->load('currentVersion'));

        expect($workflow->draftTrigger())->toBe('task.created')
            ->and($workflow->trigger_type)->toBe('manual')
            ->and($workflow->hasUnpublishedChanges())->toBeTrue()
            ->and($workflow->currentVersion->trigger_type)->toBe('manual');
    });

    it('rejects drafts that are not graphs', function () {
        $organization = Organization::factory()->create();
        $workflow = Workflow::factory()->for($organization)->create();

        actingAs($organization->owner)
            ->put(route('workflows.draft.update', [$organization, $workflow]), [
                'trigger' => 'manual',
                'definition' => ['nodes' => [['id' => 'bad id!', 'type' => 'trigger']], 'edges' => 'none'],
            ])
            ->assertSessionHasErrors(['definition.nodes.0.id', 'definition.edges']);
    });
});

describe('publishing', function () {
    it('publishes the draft as a new immutable version and switches it on', function () {
        $organization = Organization::factory()->create();
        $workflow = Workflow::factory()->for($organization)->create(['name' => 'Escalations']);

        actingAs($organization->owner)
            ->post(route('workflows.publish', [$organization, $workflow]), ['notes' => 'First cut'])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Version 1 published. New runs use it from now on.');

        // Publishing again without changes keeps version 1.
        actingAs($organization->owner)->post(route('workflows.publish', [$organization, $workflow]));

        $workflow = inTenant($organization, fn () => $workflow->refresh());
        $versions = inTenant($organization, fn () => WorkflowVersion::query()->where('workflow_id', $workflow->id)->get());

        expect($workflow->status)->toBe(WorkflowStatus::Active)
            ->and($versions)->toHaveCount(1)
            ->and($versions->first()->notes)->toBe('First cut')
            ->and($versions->first()->published_by)->toBe($organization->owner_id)
            ->and($workflow->current_version_id)->toBe($versions->first()->id)
            ->and(inTenant($organization, fn () => ActivityLog::query()->where('action', 'workflow.published')->count()))->toBe(2);
    });

    it('refuses to publish a draft with problems and says what they are', function () {
        $organization = Organization::factory()->create();
        $workflow = Workflow::factory()->for($organization)->withGraph(['nodes' => [step('trigger', 'trigger'), step('loose', 'end')], 'edges' => []])->create();

        actingAs($organization->owner)
            ->post(route('workflows.publish', [$organization, $workflow]))
            ->assertSessionHasErrors(['definition']);

        expect(inTenant($organization, fn () => WorkflowVersion::query()->count()))->toBe(0)
            ->and(inTenant($organization, fn () => $workflow->refresh()->status))->toBe(WorkflowStatus::Draft);
    });

    it('stops new runs while paused and lets runs under way finish', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')], trigger: 'task.created');

        actingAs($organization->owner)
            ->patch(route('workflows.status.update', [$organization, $workflow]), ['status' => 'paused'])
            ->assertRedirect();
        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'While paused']);

        actingAs($organization->owner)->patch(route('workflows.status.update', [$organization, $workflow]), ['status' => 'active']);
        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'After resuming']);

        $runs = inTenant($organization, fn () => WorkflowRun::query()->get());
        expect($runs)->toHaveCount(1)
            ->and($runs->first()->subject_label)->toContain('After resuming');
    });

    it('cannot switch on a workflow that was never published', function () {
        $organization = Organization::factory()->create();
        $workflow = Workflow::factory()->for($organization)->create();

        actingAs($organization->owner)
            ->patch(route('workflows.status.update', [$organization, $workflow]), ['status' => 'active'])
            ->assertSessionHasErrors(['status' => 'Publish the workflow before turning it on.']);
    });

    it('copies an earlier version back into the draft', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end', ['summary' => 'Version one'])], [path('trigger', 'done')]);
        $version = inTenant($organization, fn () => WorkflowVersion::query()->firstOrFail());
        inTenant($organization, fn () => $workflow->forceFill(['draft_definition' => ['trigger' => 'manual', 'nodes' => [step('trigger', 'trigger')], 'edges' => []]])->save());

        actingAs($organization->owner)
            ->post(route('workflows.versions.restore', [$organization, $workflow, $version]))
            ->assertRedirect();

        expect(draftOf($organization, $workflow)['nodes'][1]['data']['config']['summary'])->toBe('Version one');
    });
});

describe('deleting', function () {
    it('deletes workflows that never ran', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);

        actingAs($organization->owner)
            ->delete(route('workflows.destroy', [$organization, $workflow]))
            ->assertRedirect(route('workflows.index', $organization));

        expect(inTenant($organization, fn () => Workflow::query()->count()))->toBe(0)
            ->and(inTenant($organization, fn () => WorkflowVersion::query()->count()))->toBe(0);
    });

    it('keeps workflows with history', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);
        inTenant($organization, fn () => WorkflowRun::factory()->for($workflow)->create(['organization_id' => $organization->id]));

        actingAs($organization->owner)
            ->delete(route('workflows.destroy', [$organization, $workflow]))
            ->assertSessionHasErrors(['workflow' => 'This workflow has runs. Archive it instead, so its history is kept.']);
    });
});

describe('runs', function () {
    it('starts a run with the inputs the workflow asks for, once per submission', function () {
        $organization = Organization::factory()->create(['currency' => 'EUR']);
        $employee = memberIn($organization, Role::Employee);
        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger', ['inputs' => [
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'required' => true],
                ['key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => false],
            ]]),
            step('done', 'end'),
        ], [path('trigger', 'done')]);
        $key = (string) Str::uuid();

        actingAs($employee)
            ->post(route('workflows.runs.store', [$organization, $workflow]), ['input' => ['amount' => '1,250.50'], 'request_key' => $key])
            ->assertRedirect();
        actingAs($employee)
            ->post(route('workflows.runs.store', [$organization, $workflow]), ['input' => ['amount' => '1,250.50'], 'request_key' => $key]);

        $run = inTenant($organization, fn () => WorkflowRun::query()->sole());

        expect($run->input)->toBe(['amount' => 125050, 'reason' => null])
            ->and($run->started_by)->toBe($employee->id)
            ->and($run->status)->toBe(WorkflowRunStatus::Completed);

        actingAs($employee)
            ->get(route('workflow-runs.show', [$organization, $run]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('workflow-runs/Show')
                ->where('run.reference', $run->reference())
                ->has('steps', 2)
                ->where('input.0.value', '€1,250.50')
                ->where('can.retry', false));
    });

    it('explains which inputs are missing or wrong', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [
            step('trigger', 'trigger', ['inputs' => [
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money', 'required' => true],
                ['key' => 'needed_by', 'label' => 'Needed by', 'type' => 'date', 'required' => false],
            ]]),
            step('done', 'end'),
        ], [path('trigger', 'done')]);

        actingAs($organization->owner)
            ->post(route('workflows.runs.store', [$organization, $workflow]), ['input' => ['needed_by' => 'soon'], 'request_key' => (string) Str::uuid()])
            ->assertSessionHasErrors(['input.amount' => 'Amount is required.', 'input.needed_by' => 'Needed by must be a date.']);
    });

    it('only starts published manual workflows', function () {
        $organization = Organization::factory()->create();
        $draft = Workflow::factory()->for($organization)->create();
        $eventDriven = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')], trigger: 'task.created');

        foreach ([$draft, $eventDriven] as $workflow) {
            actingAs($organization->owner)
                ->post(route('workflows.runs.store', [$organization, $workflow]), ['input' => [], 'request_key' => (string) Str::uuid()])
                ->assertForbidden();
        }
    });

    it('lists runs and filters them by status', function () {
        $organization = Organization::factory()->create();
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);
        inTenant($organization, function () use ($workflow, $organization) {
            WorkflowRun::factory()->for($workflow)->create(['organization_id' => $organization->id, 'number' => 1]);
            WorkflowRun::factory()->for($workflow)->status(WorkflowRunStatus::Failed)->create(['organization_id' => $organization->id, 'number' => 2]);
        });

        actingAs($organization->owner)
            ->get(route('workflow-runs.index', [$organization, 'status' => 'failed']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('workflow-runs/Index')
                ->has('runs.data', 1)
                ->where('runs.data.0.reference', 'R-2')
                ->where('runs.data.0.error', 'The receiving system answered 500.'));
    });
});

describe('access', function () {
    it('lets employees view and start workflows but not build or publish them', function () {
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);
        $workflow = Workflow::factory()->for($organization)->create();

        actingAs($employee)->get(route('workflows.index', $organization))->assertOk();
        actingAs($employee)->post(route('workflows.store', $organization), ['name' => 'Mine', 'trigger' => 'manual'])->assertForbidden();
        actingAs($employee)->put(route('workflows.draft.update', [$organization, $workflow]), ['trigger' => 'manual', 'definition' => ['nodes' => [], 'edges' => []]])->assertForbidden();
        actingAs($employee)->post(route('workflows.publish', [$organization, $workflow]))->assertForbidden();
        actingAs($employee)->delete(route('workflows.destroy', [$organization, $workflow]))->assertForbidden();
    });

    it('lets managers run but not publish, and only publishers retry runs', function () {
        $organization = Organization::factory()->create();
        $manager = memberIn($organization, Role::Manager);
        $workflow = publishedWorkflow($organization, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);
        $failed = inTenant($organization, fn () => WorkflowRun::factory()->for($workflow)->status(WorkflowRunStatus::Failed)->create(['organization_id' => $organization->id]));

        actingAs($manager)->post(route('workflows.publish', [$organization, $workflow]))->assertForbidden();
        actingAs($manager)->patch(route('workflows.status.update', [$organization, $workflow]), ['status' => 'paused'])->assertForbidden();
        actingAs($manager)->post(route('workflow-runs.retry', [$organization, $failed]))->assertForbidden();
    });

    it('hides other organizations\' workflows and runs', function () {
        $organization = Organization::factory()->create();
        $other = Organization::factory()->create();
        $theirs = publishedWorkflow($other, [step('trigger', 'trigger'), step('done', 'end')], [path('trigger', 'done')]);
        $theirRun = inTenant($other, fn () => WorkflowRun::factory()->for($theirs)->create(['organization_id' => $other->id]));

        actingAs($organization->owner)->get(route('workflows.show', [$organization, $theirs]))->assertNotFound();
        actingAs($organization->owner)->put(route('workflows.draft.update', [$organization, $theirs]), ['trigger' => 'manual', 'definition' => ['nodes' => [], 'edges' => []]])->assertNotFound();
        actingAs($organization->owner)->post(route('workflows.publish', [$organization, $theirs]))->assertNotFound();
        actingAs($organization->owner)->post(route('workflows.runs.store', [$organization, $theirs]), ['input' => [], 'request_key' => (string) Str::uuid()])->assertNotFound();
        actingAs($organization->owner)->get(route('workflow-runs.show', [$organization, $theirRun]))->assertNotFound();
        actingAs($organization->owner)->post(route('workflow-runs.cancel', [$organization, $theirRun]))->assertNotFound();
    });

    it('answers malformed ids with not found', function () {
        $organization = Organization::factory()->create();

        actingAs($organization->owner)->get("/app/{$organization->slug}/workflows/not-a-uuid")->assertNotFound();
        actingAs($organization->owner)->get("/app/{$organization->slug}/tasks/42")->assertNotFound();
    });

    it('rejects people and projects from another organization in a step', function () {
        $organization = Organization::factory()->create();
        $outsider = memberIn(Organization::factory()->create());
        $workflow = Workflow::factory()->for($organization)->withGraph([
            'nodes' => [
                step('trigger', 'trigger'),
                step('assign', 'create_record', ['record' => 'task', 'title' => 'Hello', 'assignee' => ['type' => 'member', 'id' => $outsider->id]]),
            ],
            'edges' => [path('trigger', 'assign')],
        ])->create();

        actingAs($organization->owner)
            ->post(route('workflows.publish', [$organization, $workflow]))
            ->assertSessionHasErrors(['definition' => 'A chosen person is no longer a member.']);

        expect(inTenant($organization, fn () => Task::query()->count()))->toBe(0);
    });
});
