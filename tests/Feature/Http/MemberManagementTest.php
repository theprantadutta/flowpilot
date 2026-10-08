<?php

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function membershipOf(User $user, Organization $organization): OrganizationMembership
{
    return OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->sole();
}

function memberWithRole(Organization $organization, Role $role): User
{
    return User::factory()->memberOf($organization, $role)->create();
}

describe('index', function () {
    it('lists only the members of the current organization', function () {
        $organization = Organization::factory()->create();
        $colleague = memberWithRole($organization, Role::Finance);
        $stranger = memberWithRole(Organization::factory()->create(), Role::Finance);

        actingAs($organization->owner)
            ->get(route('members.index', $organization))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('members/Index')
                ->has('members', 2)
                ->where('members', fn ($members) => collect($members)->pluck('email')->contains($colleague->email)
                    && ! collect($members)->pluck('email')->contains($stranger->email)));
    });

    it('hides pending invitations from members who cannot invite', function () {
        $organization = Organization::factory()->create();
        $organization->invitations()->create([
            'email' => 'new.hire@northstar.test',
            'role' => Role::Employee,
            'token_hash' => str_repeat('a', 64),
            'expires_at' => now()->addDay(),
        ]);

        actingAs(memberWithRole($organization, Role::Employee))
            ->get(route('members.index', $organization))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations', [])
                ->where('can.invite', false));
    });
});

describe('update', function () {
    it('lets an admin change a member role and records who did it', function () {
        $organization = Organization::factory()->create();
        $admin = memberWithRole($organization, Role::Admin);
        $member = memberWithRole($organization, Role::Employee);
        $membership = membershipOf($member, $organization);

        actingAs($admin)
            ->patch(route('members.update', [$organization, $membership->id]), ['role' => 'finance'])
            ->assertRedirect();

        expect($membership->fresh()->role)->toBe(Role::Finance);

        $log = app(Tenancy::class)->run($organization, fn () => ActivityLog::query()->where('action', 'member.role_changed')->sole());
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->properties['changes']['role'])->toBe(['from' => 'employee', 'to' => 'finance']);
    });

    it('lets an admin suspend a member, who then loses access', function () {
        $organization = Organization::factory()->create();
        $member = memberWithRole($organization, Role::Employee);
        $membership = membershipOf($member, $organization);

        actingAs(memberWithRole($organization, Role::Admin))
            ->patch(route('members.update', [$organization, $membership->id]), ['status' => 'suspended']);

        expect($membership->fresh()->status)->toBe(MembershipStatus::Suspended);
        actingAs($member)->get(route('overview', $organization))->assertForbidden();
    });

    it('forbids members without the manage permission', function (Role $role) {
        $organization = Organization::factory()->create();
        $target = membershipOf(memberWithRole($organization, Role::Employee), $organization);

        actingAs(memberWithRole($organization, $role))
            ->patch(route('members.update', [$organization, $target->id]), ['role' => 'auditor'])
            ->assertForbidden();

        expect($target->fresh()->role)->toBe(Role::Employee);
    })->with([Role::Manager, Role::Finance, Role::Procurement, Role::Operations, Role::Employee, Role::Auditor]);

    it('rejects giving a role equal to or above your own', function () {
        $organization = Organization::factory()->create();
        $target = membershipOf(memberWithRole($organization, Role::Employee), $organization);

        actingAs(memberWithRole($organization, Role::Admin))
            ->patch(route('members.update', [$organization, $target->id]), ['role' => 'admin'])
            ->assertSessionHasErrors(['role' => 'Your role cannot assign the Admin role.']);

        expect($target->fresh()->role)->toBe(Role::Employee);
    });

    it('forbids changing the owner', function () {
        $organization = Organization::factory()->create();
        $ownerMembership = membershipOf($organization->owner, $organization);

        actingAs(memberWithRole($organization, Role::Admin))
            ->patch(route('members.update', [$organization, $ownerMembership->id]), ['status' => 'suspended'])
            ->assertForbidden();
    });

    it('forbids an admin from changing another admin', function () {
        $organization = Organization::factory()->create();
        $otherAdmin = membershipOf(memberWithRole($organization, Role::Admin), $organization);

        actingAs(memberWithRole($organization, Role::Admin))
            ->patch(route('members.update', [$organization, $otherAdmin->id]), ['role' => 'employee'])
            ->assertForbidden();
    });

    it('forbids changing your own membership', function () {
        $organization = Organization::factory()->create();
        $admin = memberWithRole($organization, Role::Admin);

        actingAs($admin)
            ->patch(route('members.update', [$organization, membershipOf($admin, $organization)->id]), ['role' => 'employee'])
            ->assertForbidden();
    });

    it('returns 404 for a member of another organization', function () {
        $organization = Organization::factory()->create();
        $elsewhere = Organization::factory()->create();
        $foreign = membershipOf(memberWithRole($elsewhere, Role::Employee), $elsewhere);

        actingAs($organization->owner)
            ->patch(route('members.update', [$organization, $foreign->id]), ['role' => 'auditor'])
            ->assertNotFound();

        expect($foreign->fresh()->role)->toBe(Role::Employee);
    });
});

describe('destroy', function () {
    it('removes the member but keeps their account', function () {
        $organization = Organization::factory()->create();
        $member = memberWithRole($organization, Role::Employee);
        $member->forceFill(['last_organization_id' => $organization->id])->save();

        actingAs($organization->owner)
            ->delete(route('members.destroy', [$organization, membershipOf($member, $organization)->id]))
            ->assertRedirect();

        expect(OrganizationMembership::query()->where('user_id', $member->id)->exists())->toBeFalse()
            ->and($member->fresh())->not->toBeNull()
            ->last_organization_id->toBeNull();
        actingAs($member)->get(route('overview', $organization))->assertNotFound();
    });

    it('forbids removing the owner', function () {
        $organization = Organization::factory()->create();

        actingAs(memberWithRole($organization, Role::Admin))
            ->delete(route('members.destroy', [$organization, membershipOf($organization->owner, $organization)->id]))
            ->assertForbidden();
    });
});
