<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;

use function Pest\Laravel\actingAs;

it('finds members of the current organization by name or email', function () {
    $organization = Organization::factory()->create();
    User::factory()->memberOf($organization, Role::Finance)->create(['name' => 'Priya Nair', 'email' => 'priya@northstar.test']);

    actingAs($organization->owner)
        ->getJson(route('search', ['organization' => $organization, 'q' => 'priya']))
        ->assertOk()
        ->assertJsonPath('results.0.title', 'Priya Nair')
        ->assertJsonPath('results.0.group', 'Members')
        ->assertJsonPath('results.0.subtitle', 'Finance · priya@northstar.test');
});

it('never returns people from another organization', function () {
    $organization = Organization::factory()->create();
    User::factory()->memberOf(Organization::factory()->create())->create(['name' => 'Priya Elsewhere']);

    actingAs($organization->owner)
        ->getJson(route('search', ['organization' => $organization, 'q' => 'priya']))
        ->assertOk()
        ->assertJsonCount(0, 'results');
});

it('ignores terms shorter than two characters', function () {
    $organization = Organization::factory()->create();

    actingAs($organization->owner)
        ->getJson(route('search', ['organization' => $organization, 'q' => 'p']))
        ->assertJsonCount(0, 'results');
});

it('matches wildcard characters literally', function () {
    $organization = Organization::factory()->create();
    User::factory()->memberOf($organization)->create(['name' => 'Sam Lee', 'email' => 'sam.lee@northstar.test']);

    actingAs($organization->owner)
        ->getJson(route('search', ['organization' => $organization, 'q' => '%%']))
        ->assertJsonCount(0, 'results');

    actingAs($organization->owner)
        ->getJson(route('search', ['organization' => $organization, 'q' => 'sam_lee']))
        ->assertJsonCount(0, 'results');
});

it('finds workflows with their status and trigger', function () {
    $organization = Organization::factory()->create();
    Workflow::factory()->for($organization)->create(['name' => 'Purchase approval', 'trigger_type' => 'manual']);
    Workflow::factory()->for(Organization::factory())->create(['name' => 'Purchase approval elsewhere']);

    actingAs($organization->owner)
        ->getJson(route('search', ['organization' => $organization, 'q' => 'purchase']))
        ->assertOk()
        ->assertJsonCount(1, 'results')
        ->assertJsonPath('results.0.group', 'Workflows')
        ->assertJsonPath('results.0.subtitle', 'Draft · Started by a person');
});
