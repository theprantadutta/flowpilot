<?php

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

describe('sending', function () {
    it('emails a link whose token is stored only as a hash', function () {
        Notification::fake();
        $organization = Organization::factory()->create();

        actingAs($organization->owner)
            ->post(route('members.invitations.store', $organization), [
                'email' => 'Priya.Nair@Northstar.test',
                'role' => 'finance',
                'department' => 'Finance',
            ])
            ->assertRedirect();

        $invitation = $organization->invitations()->sole();
        expect($invitation)
            ->email->toBe('priya.nair@northstar.test')
            ->role->toBe(Role::Finance)
            ->department->toBe('Finance');

        Notification::assertSentTo(
            new AnonymousNotifiable,
            OrganizationInvitationNotification::class,
            function (OrganizationInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
                return $notifiable->routes['mail'] === 'priya.nair@northstar.test'
                    && Invitation::hashToken($notification->token) === $invitation->token_hash
                    && $notification->token !== $invitation->token_hash;
            },
        );
    });

    it('replaces an earlier unanswered invitation to the same address', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $earlier = Invitation::factory()->for($organization)->create(['email' => 'sam@northstar.test']);

        actingAs($organization->owner)->post(route('members.invitations.store', $organization), [
            'email' => 'sam@northstar.test',
            'role' => 'employee',
        ]);

        expect($earlier->fresh()->revoked_at)->not->toBeNull()
            ->and($organization->invitations()->open()->count())->toBe(1);
    });

    it('refuses to invite someone who is already a member', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $member = User::factory()->memberOf($organization)->create();

        actingAs($organization->owner)
            ->post(route('members.invitations.store', $organization), ['email' => $member->email, 'role' => 'employee'])
            ->assertSessionHasErrors(['email' => 'That person is already a member of this organization.']);

        Notification::assertNothingSent();
    });

    it('refuses to invite anyone as owner', function () {
        $organization = Organization::factory()->create();

        actingAs($organization->owner)
            ->post(route('members.invitations.store', $organization), ['email' => 'a@b.test', 'role' => 'owner'])
            ->assertSessionHasErrors(['role' => 'Your role cannot invite someone as Owner.']);
    });

    it('forbids members without the invite permission', function () {
        $organization = Organization::factory()->create();

        actingAs(User::factory()->memberOf($organization, Role::Manager)->create())
            ->post(route('members.invitations.store', $organization), ['email' => 'a@b.test', 'role' => 'employee'])
            ->assertForbidden();
    });

    it('escapes the organization and inviter names in the email', function () {
        $organization = Organization::factory()->create(['name' => "O'Brien <script>alert(1)</script> Ltd"]);
        $invitation = Invitation::factory()->for($organization)->create();

        $html = (string) (new OrganizationInvitationNotification($invitation, 'token', '<b>Mallory</b>'))
            ->toMail(new AnonymousNotifiable)
            ->render();

        expect($html)
            ->toContain('&lt;script&gt;')
            ->not->toContain('<script>alert(1)</script>')
            ->not->toContain('<b>Mallory</b>');
    });
});

describe('managing', function () {
    it('re-sends with a new link and the old one stops working', function () {
        Notification::fake();
        $organization = Organization::factory()->create();
        $invitation = Invitation::factory()->for($organization)->withToken('old-token')->create();

        actingAs($organization->owner)
            ->patch(route('members.invitations.resend', [$organization, $invitation->id]))
            ->assertRedirect();

        get(route('invitations.show', 'old-token'))->assertNotFound();
        Notification::assertSentOnDemandTimes(OrganizationInvitationNotification::class, 1);
    });

    it('revokes an invitation', function () {
        $organization = Organization::factory()->create();
        $invitation = Invitation::factory()->for($organization)->create();

        actingAs($organization->owner)
            ->delete(route('members.invitations.destroy', [$organization, $invitation->id]))
            ->assertRedirect();

        expect($invitation->fresh()->revoked_at)->not->toBeNull();
    });

    it('returns 404 for an invitation from another organization', function () {
        $organization = Organization::factory()->create();
        $foreign = Invitation::factory()->create();

        actingAs($organization->owner)
            ->delete(route('members.invitations.destroy', [$organization, $foreign->id]))
            ->assertNotFound();

        expect($foreign->fresh()->revoked_at)->toBeNull();
    });
});

describe('accepting', function () {
    it('shows the invitation to a signed-out visitor', function () {
        $invitation = Invitation::factory()->withToken('welcome-token')->create(['role' => Role::Finance]);

        get(route('invitations.show', 'welcome-token'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invitations/Show')
                ->where('invitation.organization', $invitation->organization->name)
                ->where('invitation.role_label', 'Finance')
                ->where('invitation.state', 'open')
                ->where('viewer', null)
                ->missing('invitation.token_hash'));
    });

    it('returns 404 for an unknown link', function () {
        get(route('invitations.show', 'not-a-real-token'))->assertNotFound();
    });

    it('turns the invitation into a membership with the invited role', function () {
        $user = User::factory()->unverified()->create(['email' => 'priya@northstar.test']);
        $invitation = Invitation::factory()->withToken('join-token')->create([
            'email' => 'Priya@Northstar.test',
            'role' => Role::Finance,
            'department' => 'Finance',
        ]);

        actingAs($user)
            ->post(route('invitations.accept', 'join-token'))
            ->assertRedirect(route('overview', $invitation->organization));

        $membership = OrganizationMembership::query()->where('user_id', $user->id)->sole();
        expect($membership)
            ->organization_id->toBe($invitation->organization_id)
            ->role->toBe(Role::Finance)
            ->department->toBe('Finance')
            ->and($invitation->fresh()->accepted_at)->not->toBeNull()
            ->and($user->fresh()->hasVerifiedEmail())->toBeTrue();
    });

    it('does nothing more when the same invitation is accepted twice', function () {
        $user = User::factory()->create(['email' => 'sam@northstar.test']);
        Invitation::factory()->withToken('twice')->create(['email' => 'sam@northstar.test']);

        actingAs($user)->post(route('invitations.accept', 'twice'));
        actingAs($user)->post(route('invitations.accept', 'twice'))->assertRedirect();

        expect(OrganizationMembership::query()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('refuses a user signed in with a different email address', function () {
        $intruder = User::factory()->create(['email' => 'mallory@elsewhere.test']);
        Invitation::factory()->withToken('not-yours')->create(['email' => 'priya@northstar.test']);

        actingAs($intruder)
            ->post(route('invitations.accept', 'not-yours'))
            ->assertSessionHasErrors('invitation');

        expect(OrganizationMembership::query()->where('user_id', $intruder->id)->exists())->toBeFalse();
    });

    it('refuses an invitation that can no longer be used', function (string $state, string $message) {
        $user = User::factory()->create(['email' => 'late@northstar.test']);
        Invitation::factory()->{$state}()->withToken('stale')->create(['email' => 'late@northstar.test']);

        actingAs($user)
            ->post(route('invitations.accept', 'stale'))
            ->assertSessionHasErrors(['invitation' => $message]);

        expect(OrganizationMembership::query()->where('user_id', $user->id)->exists())->toBeFalse();
    })->with([
        'expired' => ['expired', 'This invitation has expired. Ask the person who invited you to send a new one.'],
        'revoked' => ['revoked', 'This invitation is no longer valid.'],
    ]);

    it('requires an account to accept', function () {
        Invitation::factory()->withToken('guest')->create();

        $this->post(route('invitations.accept', 'guest'))->assertRedirect(route('login'));
    });
});
