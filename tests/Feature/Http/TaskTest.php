<?php

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

describe('creating', function () {
    it('numbers tasks per organization and tells the assignee', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $assignee = memberIn($organization);
        $other = Organization::factory()->create();

        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'Order steel beams', 'assignee_id' => $assignee->id]);
        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'Book the crane']);
        actingAs($other->owner)->post(route('tasks.store', $other), ['title' => 'Unrelated']);

        $numbers = inTenant($organization, fn () => Task::query()->orderBy('number')->pluck('number', 'title')->all());
        expect($numbers)->toBe(['Order steel beams' => 1, 'Book the crane' => 2])
            ->and(inTenant($other, fn () => Task::query()->value('number')))->toBe(1);

        Notification::assertSentTo($assignee, TaskAssignedNotification::class, fn ($notification) => $notification->reference === 'T-1');
    });

    it('does not notify people who assign a task to themselves', function () {
        Notification::fake();
        $organization = Organization::factory()->create();

        actingAs($organization->owner)->post(route('tasks.store', $organization), ['title' => 'Note to self', 'assignee_id' => $organization->owner_id]);

        Notification::assertNothingSent();
    });

    it('rejects assignees and projects from another organization', function () {
        $organization = Organization::factory()->create();
        $outsider = memberIn(Organization::factory()->create());
        $foreignProject = Project::factory()->create();

        actingAs($organization->owner)
            ->post(route('tasks.store', $organization), [
                'title' => 'Sneaky',
                'assignee_id' => $outsider->id,
                'project_id' => $foreignProject->id,
            ])
            ->assertSessionHasErrors([
                'assignee_id' => 'Assign the task to an active member of this organization.',
                'project_id' => 'Choose a project from this organization.',
            ]);
    });

    it('forbids members who can only read', function () {
        $organization = Organization::factory()->create();

        actingAs(memberIn($organization, Role::Auditor))
            ->post(route('tasks.store', $organization), ['title' => 'Nope'])
            ->assertForbidden();
    });
});

describe('listing', function () {
    it('filters to my overdue tasks', function () {
        $organization = Organization::factory()->create();
        $me = memberIn($organization);
        Task::factory()->for($organization)->overdue()->create(['assignee_id' => $me->id, 'title' => 'Mine and late']);
        Task::factory()->for($organization)->create(['assignee_id' => $me->id, 'title' => 'Mine, on time', 'due_date' => now()->addWeek()]);
        Task::factory()->for($organization)->overdue()->create(['title' => 'Someone else, late']);

        actingAs($me)
            ->get(route('tasks.index', ['organization' => $organization, 'assignee' => 'me', 'due' => 'overdue']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tasks/Index')
                ->has('list.data', 1)
                ->where('list.data.0.title', 'Mine and late')
                ->where('list.data.0.is_overdue', true));
    });

    it('finds a task by its number', function () {
        $organization = Organization::factory()->create();
        Task::factory()->for($organization)->create(['number' => 42, 'title' => 'The answer']);
        Task::factory()->for($organization)->create(['number' => 7, 'title' => 'Something else']);

        actingAs($organization->owner)
            ->get(route('tasks.index', ['organization' => $organization, 'q' => 'T-42']))
            ->assertInertia(fn (Assert $page) => $page->has('list.data', 1)->where('list.data.0.reference', 'T-42'));
    });

    it('groups the board by status in board order', function () {
        $organization = Organization::factory()->create();
        Task::factory()->for($organization)->create(['title' => 'Second', 'position' => 2048]);
        Task::factory()->for($organization)->create(['title' => 'First', 'position' => 1024]);

        actingAs($organization->owner)
            ->get(route('tasks.index', ['organization' => $organization, 'view' => 'board']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('view', 'board')
                ->where('board.data.0.title', 'First')
                ->where('board.data.1.title', 'Second'));
    });
});

describe('the board', function () {
    it('places a dropped card between its neighbours and completes it in Done', function () {
        $organization = Organization::factory()->create();
        $above = Task::factory()->for($organization)->status(TaskStatus::Done)->create(['position' => 1000]);
        $below = Task::factory()->for($organization)->status(TaskStatus::Done)->create(['position' => 2000]);
        $moving = Task::factory()->for($organization)->create(['status' => TaskStatus::InProgress]);

        actingAs($organization->owner)
            ->patchJson(route('tasks.move', [$organization, $moving]), [
                'status' => 'done',
                'after_id' => $above->id,
                'before_id' => $below->id,
            ])
            ->assertOk()
            ->assertJsonPath('position', 1500);

        expect($moving->fresh())
            ->status->toBe(TaskStatus::Done)
            ->completed_at->not->toBeNull();
    });

    it('renumbers a column when there is no room left between two cards', function () {
        $organization = Organization::factory()->create();
        $above = Task::factory()->for($organization)->create(['position' => 1.0]);
        $below = Task::factory()->for($organization)->create(['position' => 1.00001]);
        $moving = Task::factory()->for($organization)->create(['status' => TaskStatus::Backlog]);

        actingAs($organization->owner)->patchJson(route('tasks.move', [$organization, $moving]), [
            'status' => 'todo',
            'after_id' => $above->id,
            'before_id' => $below->id,
        ])->assertOk();

        expect($moving->fresh()->position)
            ->toBeGreaterThan($above->fresh()->position)
            ->toBeLessThan($below->fresh()->position);
    });

    it('rejects neighbours from another organization', function () {
        $organization = Organization::factory()->create();
        $moving = Task::factory()->for($organization)->create();
        $foreign = Task::factory()->create();

        actingAs($organization->owner)
            ->patchJson(route('tasks.move', [$organization, $moving]), ['status' => 'todo', 'after_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('after_id');
    });
});

describe('editing', function () {
    it('tells the new assignee and records the change', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $first = memberIn($organization);
        $second = memberIn($organization);
        $task = Task::factory()->for($organization)->create(['assignee_id' => $first->id]);

        actingAs($organization->owner)
            ->patch(route('tasks.update', [$organization, $task]), ['assignee_id' => $second->id])
            ->assertRedirect();

        Notification::assertSentTo($second, TaskAssignedNotification::class);
        Notification::assertNotSentTo($first, TaskAssignedNotification::class);
    });

    it('lets an assignee update their own task without the general permission', function () {
        $organization = Organization::factory()->create();
        $auditor = memberIn($organization, Role::Auditor);
        $theirs = Task::factory()->for($organization)->create(['assignee_id' => $auditor->id]);
        $notTheirs = Task::factory()->for($organization)->create();

        actingAs($auditor)->patch(route('tasks.update', [$organization, $theirs]), ['status' => 'done'])->assertRedirect();
        actingAs($auditor)->patch(route('tasks.update', [$organization, $notTheirs]), ['status' => 'done'])->assertForbidden();
    });

    it('returns 404 for a task in another organization', function () {
        $organization = Organization::factory()->create();
        $foreign = Task::factory()->create(['title' => 'Theirs']);

        actingAs($organization->owner)->get(route('tasks.show', [$organization, $foreign]))->assertNotFound();
        actingAs($organization->owner)->patch(route('tasks.update', [$organization, $foreign]), ['title' => 'Mine now'])->assertNotFound();
        actingAs($organization->owner)->delete(route('tasks.destroy', [$organization, $foreign]))->assertNotFound();

        expect($foreign->fresh()->title)->toBe('Theirs');
    });
});

describe('checklists and dependencies', function () {
    it('adds and ticks checklist items', function () {
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();

        actingAs($organization->owner)->post(route('tasks.checklist.store', [$organization, $task]), ['body' => 'Measure the doorway']);
        $item = $task->checklistItems()->sole();

        actingAs($organization->owner)
            ->patch(route('tasks.checklist.update', [$organization, $task, $item]), ['is_done' => true])
            ->assertRedirect();

        expect($item->fresh())->is_done->toBeTrue()->completed_by->toBe($organization->owner_id);
    });

    it('only finds checklist items through their own task', function () {
        $organization = Organization::factory()->create();
        $task = Task::factory()->for($organization)->create();
        $otherTask = Task::factory()->for($organization)->create();
        $item = $otherTask->checklistItems()->create(['body' => 'Not on this task']);

        actingAs($organization->owner)
            ->patch(route('tasks.checklist.update', [$organization, $task, $item]), ['is_done' => true])
            ->assertNotFound();
    });

    it('refuses a dependency that would create a loop', function () {
        $organization = Organization::factory()->create();
        [$a, $b, $c] = Task::factory()->for($organization)->count(3)->create()->all();

        actingAs($organization->owner)->post(route('tasks.dependencies.store', [$organization, $a]), ['depends_on_id' => $b->id]);
        actingAs($organization->owner)->post(route('tasks.dependencies.store', [$organization, $b]), ['depends_on_id' => $c->id]);

        actingAs($organization->owner)
            ->post(route('tasks.dependencies.store', [$organization, $c]), ['depends_on_id' => $a->id])
            ->assertSessionHasErrors(['depends_on_id' => "{$a->reference()} already waits on {$c->reference()}, so this would create a loop."]);

        expect($c->dependencies()->count())->toBe(0);
    });
});
