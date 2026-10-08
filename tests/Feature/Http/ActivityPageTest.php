<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Task;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function changeTaskTitle(Organization $organization, Task $task): void
{
    actingAs($organization->owner)->patch(route('tasks.update', [$organization, $task]), ['title' => 'Renamed task']);
}

it('lists what happened with readable sentences', function () {
    $organization = Organization::factory()->create();
    $task = Task::factory()->for($organization)->create(['number' => 3, 'title' => 'Old title']);
    changeTaskTitle($organization, $task);

    actingAs(memberIn($organization))
        ->get(route('activity.index', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->component('activity/Index')
            ->where('entries.data.0.summary', 'updated T-3 Renamed task')
            ->where('entries.data.0.audit', null)
            ->where('canAudit', false));
});

it('shows audit details only to members who can see the audit log', function () {
    $organization = Organization::factory()->create();
    $task = Task::factory()->for($organization)->create(['title' => 'Old title']);
    changeTaskTitle($organization, $task);

    actingAs(memberIn($organization, Role::Auditor))
        ->get(route('activity.index', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canAudit', true)
            ->where('entries.data.0.changes.title', ['from' => 'Old title', 'to' => 'Renamed task'])
            ->has('entries.data.0.audit.ip_address'));
});

it('filters by area', function () {
    $organization = Organization::factory()->create();
    changeTaskTitle($organization, Task::factory()->for($organization)->create());
    actingAs($organization->owner)->patch(route('organization-settings.update', [$organization, 'members']), ['default_role' => 'finance', 'allow_member_invites' => false]);

    actingAs($organization->owner)
        ->get(route('activity.index', ['organization' => $organization, 'area' => 'settings']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'settings.updated'));
});

it('never shows activity from another organization', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    changeTaskTitle($other, Task::factory()->for($other)->create());

    actingAs($organization->owner)
        ->get(route('activity.index', $organization))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 0));
});
