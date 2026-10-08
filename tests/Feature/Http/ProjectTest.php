<?php

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\ProjectUpdatedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

describe('creating', function () {
    it('creates a project owned by its creator with the budget in minor units', function () {
        $organization = Organization::factory()->create(['currency' => 'USD']);
        $manager = memberIn($organization, Role::Manager);
        $colleague = memberIn($organization);

        actingAs($manager)->post(route('projects.store', $organization), [
            'name' => 'Factory expansion',
            'status' => 'active',
            'budget' => '250,000.50',
            'start_date' => '2026-10-01',
            'due_date' => '2027-03-31',
            'member_ids' => [$colleague->id],
            'tags' => ['  Capital ', 'capital', 'Phase 2'],
        ])->assertRedirect();

        $project = inTenant($organization, fn () => Project::query()->with('members')->sole());

        expect($project)
            ->owner_id->toBe($manager->id)
            ->budget_amount->toBe(25000050)
            ->budget_currency->toBe('USD')
            ->tags->toBe(['capital', 'phase 2'])
            ->and($project->members->pluck('id')->sort()->values()->all())->toBe(collect([$manager->id, $colleague->id])->sort()->values()->all());

        expect(inTenant($organization, fn () => ActivityLog::query()->where('action', 'project.created')->exists()))->toBeTrue();
    });

    it('rejects invalid input with clear messages', function (array $input, string $field, string $message) {
        $organization = Organization::factory()->create(['currency' => 'USD']);

        actingAs($organization->owner)
            ->post(route('projects.store', $organization), ['name' => 'Valid name', ...$input])
            ->assertSessionHasErrors([$field => $message]);
    })->with([
        'missing name' => [['name' => ''], 'name', 'Give the project a name.'],
        'due before start' => [['start_date' => '2026-10-10', 'due_date' => '2026-10-01'], 'due_date', 'The due date cannot be before the start date.'],
        'budget with letters' => [['budget' => '12k'], 'budget', 'Enter the budget as a number, for example 25000 or 25,000.00.'],
        'too many decimals' => [['budget' => '10.555'], 'budget', 'USD amounts have at most 2 decimal places.'],
    ]);

    it('refuses members from another organization', function () {
        $organization = Organization::factory()->create();
        $outsider = memberIn(Organization::factory()->create());

        actingAs($organization->owner)
            ->post(route('projects.store', $organization), ['name' => 'Secret', 'member_ids' => [$outsider->id]])
            ->assertSessionHasErrors('member_ids.0');
    });

    it('forbids members who cannot create projects', function () {
        $organization = Organization::factory()->create();

        actingAs(memberIn($organization, Role::Employee))
            ->post(route('projects.store', $organization), ['name' => 'Not allowed'])
            ->assertForbidden();
    });
});

describe('viewing', function () {
    it('lists projects with their progress', function () {
        $organization = Organization::factory()->create();
        $project = Project::factory()->for($organization)->create(['name' => 'Warehouse upgrade']);
        Task::factory()->for($organization)->for($project)->count(3)->create();
        Task::factory()->for($organization)->for($project)->status(TaskStatus::Done)->create();

        actingAs($organization->owner)
            ->get(route('projects.index', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Index')
                ->has('projects.data', 1)
                ->where('projects.data.0.name', 'Warehouse upgrade')
                ->where('projects.data.0.progress', 25)
                ->where('projects.data.0.tasks_count', 4));
    });

    it('loads a tab only when it is asked for', function () {
        $organization = Organization::factory()->create();
        $project = Project::factory()->for($organization)->create();
        Task::factory()->for($organization)->for($project)->create(['title' => 'Pour the foundations']);

        actingAs($organization->owner)
            ->get(route('projects.show', [$organization, $project]))
            ->assertInertia(fn (Assert $page) => $page->component('projects/Show')->missing('tasks'));

        actingAs($organization->owner)
            ->get(route('projects.show', [$organization, $project, 'tab' => 'tasks']), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => Inertia::getVersion(),
                'X-Inertia-Partial-Component' => 'projects/Show',
                'X-Inertia-Partial-Data' => 'tasks',
            ])
            ->assertJsonPath('props.tasks.data.0.title', 'Pour the foundations');
    });

    it('returns 404 for a project in another organization', function () {
        $organization = Organization::factory()->create();
        $foreign = Project::factory()->create();

        actingAs($organization->owner)->get(route('projects.show', [$organization, $foreign]))->assertNotFound();
        actingAs($organization->owner)->patch(route('projects.update', [$organization, $foreign]), ['name' => 'Taken'])->assertNotFound();

        expect($foreign->fresh()->name)->not->toBe('Taken');
    });
});

describe('changing', function () {
    it('tells project members when the status changes and records completion', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $member = memberIn($organization);
        $project = Project::factory()->for($organization)->create(['status' => ProjectStatus::Active, 'owner_id' => $organization->owner_id]);
        $project->members()->attach([$member->id, $organization->owner_id]);

        actingAs($organization->owner)
            ->patch(route('projects.update', [$organization, $project]), ['status' => 'completed'])
            ->assertRedirect();

        expect($project->fresh())->status->toBe(ProjectStatus::Completed)->completed_at->not->toBeNull();
        Notification::assertSentTo($member, ProjectUpdatedNotification::class, fn ($notification) => $notification->toStatus === 'Completed');
        Notification::assertNotSentTo($organization->owner, ProjectUpdatedNotification::class);
    });

    it('lets the owner of a project edit it even without the general permission', function () {
        $organization = Organization::factory()->create();
        $employee = memberIn($organization, Role::Employee);
        $project = Project::factory()->for($organization)->create(['owner_id' => $employee->id]);

        actingAs($employee)->patch(route('projects.update', [$organization, $project]), ['name' => 'Renamed'])->assertRedirect();
        expect($project->fresh()->name)->toBe('Renamed');
    });

    it('deletes the project and its tasks but keeps issues, after the name is typed', function () {
        $organization = Organization::factory()->create();
        $project = Project::factory()->for($organization)->create(['name' => 'Old line']);
        $task = Task::factory()->for($organization)->for($project)->create();
        $issue = Issue::factory()->for($organization)->for($project)->create();

        actingAs($organization->owner)
            ->delete(route('projects.destroy', [$organization, $project]), ['confirm_name' => 'Wrong'])
            ->assertSessionHasErrors(['confirm_name' => 'Type the project name exactly to confirm.']);
        expect(Project::withoutOrganizationScope()->whereKey($project->id)->exists())->toBeTrue();

        actingAs($organization->owner)
            ->delete(route('projects.destroy', [$organization, $project]), ['confirm_name' => 'Old line'])
            ->assertRedirect(route('projects.index', $organization));

        expect(Project::withoutOrganizationScope()->whereKey($project->id)->exists())->toBeFalse()
            ->and(Task::withoutOrganizationScope()->whereKey($task->id)->exists())->toBeFalse()
            ->and(Issue::withoutOrganizationScope()->find($issue->id))->not->toBeNull()
            ->project_id->toBeNull();
    });
});
