<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('redirects guests to the login page', function () {
    $organization = Organization::factory()->create();

    get(route('overview', $organization))->assertRedirect(route('login'));
});

it('opens the overview for an active member and shares their permissions', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->memberOf($organization, Role::Auditor)->create();

    actingAs($member)
        ->get(route('overview', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Overview')
            ->where('organization.slug', $organization->slug)
            ->where('organization.role', 'auditor')
            ->where('organization.permissions', fn ($permissions) => collect($permissions)->contains('audit.view')
                && ! collect($permissions)->contains('members.invite')));
});

it('returns 404 for an organization the user does not belong to', function () {
    $theirs = Organization::factory()->create();
    $outsider = User::factory()->memberOf(Organization::factory()->create())->create();

    actingAs($outsider)->get(route('overview', $theirs))->assertNotFound();
});

it('returns 404 for an organization that does not exist', function () {
    $user = User::factory()->memberOf(Organization::factory()->create())->create();

    actingAs($user)->get('/app/no-such-organization')->assertNotFound();
});

it('forbids a member whose access is suspended', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    OrganizationMembership::factory()->for($organization)->for($member)->suspended()->create();

    actingAs($member)->get(route('overview', $organization))->assertForbidden();
});

it('forbids every member of a suspended organization', function () {
    $organization = Organization::factory()->suspended()->create();

    actingAs($organization->owner)->get(route('overview', $organization))->assertForbidden();
});

it('remembers the organization the user last opened', function () {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    $user = User::factory()->memberOf($first)->memberOf($second)->create();

    actingAs($user)->get(route('overview', $second))->assertOk();

    expect($user->fresh()->last_organization_id)->toBe($second->id);
});

it('sends a user without an organization to onboarding', function () {
    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

it('sends a user back to the organization they last opened', function () {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();
    $user = User::factory()->memberOf($first)->memberOf($second)->create();
    $user->forceFill(['last_organization_id' => $second->id])->save();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('overview', $second));
});

it('skips organizations the user can no longer open when choosing where to land', function () {
    $suspended = Organization::factory()->suspended()->create();
    $active = Organization::factory()->create();
    $user = User::factory()->memberOf($suspended)->memberOf($active)->create();
    $user->forceFill(['last_organization_id' => $suspended->id])->save();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('overview', $active));
});
