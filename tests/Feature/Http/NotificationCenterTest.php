<?php

use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Notification as NotificationRecord;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberJoinedNotification;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function notifyIn(Organization $organization, User $user, string $name = 'Priya Nair'): NotificationRecord
{
    app(Tenancy::class)->run($organization, fn () => $user->notifyNow(new MemberJoinedNotification($name, 'Finance'), ['database']));

    return $user->notifications()->where('organization_id', $organization->id)->latest()->firstOrFail();
}

it('lists only the notifications of the current organization', function () {
    $northstar = Organization::factory()->create();
    $harbor = Organization::factory()->create();
    $user = User::factory()->memberOf($northstar)->memberOf($harbor)->create();

    notifyIn($northstar, $user, 'Northstar Person');
    notifyIn($harbor, $user, 'Harbor Person');

    actingAs($user)
        ->get(route('notifications.index', $northstar))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Northstar Person joined as Finance')
            ->where('unreadCount', 1));
});

it('shares the unread count of the current organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->memberOf($organization)->create();
    notifyIn($organization, $user);
    notifyIn($organization, $user);

    actingAs($user)
        ->get(route('overview', $organization))
        ->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 2));
});

it('marks one notification read and then all of them', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->memberOf($organization)->create();
    $first = notifyIn($organization, $user);
    notifyIn($organization, $user);

    actingAs($user)->post(route('notifications.read', [$organization, $first->id]))->assertRedirect();
    expect($first->fresh()->read_at)->not->toBeNull()
        ->and($user->unreadNotifications()->count())->toBe(1);

    actingAs($user)->post(route('notifications.read-all', $organization))->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('opens a notification by marking it read and following its link', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->memberOf($organization)->create();
    $notification = notifyIn($organization, $user);

    actingAs($user)
        ->get(route('notifications.open', [$organization, $notification->id]))
        ->assertRedirect(route('members.index', $organization));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('does not follow a link that leaves the application', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->memberOf($organization)->create();
    $notification = notifyIn($organization, $user);
    $notification->forceFill(['data' => [...$notification->data, 'url' => 'https://evil.example/phish']])->save();

    actingAs($user)
        ->get(route('notifications.open', [$organization, $notification->id]))
        ->assertRedirect(route('notifications.index', $organization));
});

it("returns 404 for someone else's notification", function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->memberOf($organization)->create();
    $snoop = User::factory()->memberOf($organization)->create();
    $notification = notifyIn($organization, $owner);

    actingAs($snoop)->post(route('notifications.read', [$organization, $notification->id]))->assertNotFound();
    actingAs($snoop)->get(route('notifications.open', [$organization, $notification->id]))->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('returns 404 for a notification from another organization', function () {
    $northstar = Organization::factory()->create();
    $harbor = Organization::factory()->create();
    $user = User::factory()->memberOf($northstar)->memberOf($harbor)->create();
    $harborNotification = notifyIn($harbor, $user);

    actingAs($user)->post(route('notifications.read', [$northstar, $harborNotification->id]))->assertNotFound();
});

it('tells managers when someone accepts an invitation', function () {
    Notification::fake();
    $organization = Organization::factory()->create();
    $admin = User::factory()->memberOf($organization, Role::Admin)->create();
    $employee = User::factory()->memberOf($organization, Role::Employee)->create();
    $newcomer = User::factory()->create(['email' => 'new@northstar.test']);
    Invitation::factory()->for($organization)->withToken('join')->create(['email' => 'new@northstar.test', 'role' => Role::Finance]);

    actingAs($newcomer)->post(route('invitations.accept', 'join'));

    Notification::assertSentTo([$organization->owner, $admin], MemberJoinedNotification::class,
        fn (MemberJoinedNotification $notification) => $notification->title() === "{$newcomer->name} joined as Finance"
            && $notification->organizationId === $organization->id);
    Notification::assertNotSentTo([$employee, $newcomer], MemberJoinedNotification::class);
});

describe('email preferences', function () {
    it('follows the type default when nobody has chosen', function () {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $preferences = app(NotificationPreferences::class);

        expect($preferences->wantsEmail($user, NotificationType::ApprovalRequired, $organization))->toBeTrue()
            ->and($preferences->wantsEmail($user, NotificationType::MemberJoined, $organization))->toBeFalse();
    });

    it('lets the organization default override the type default', function () {
        $organization = Organization::factory()->create(['settings' => ['notifications' => ['email' => ['member_joined' => true]]]]);

        expect(app(NotificationPreferences::class)->wantsEmail(User::factory()->create(), NotificationType::MemberJoined, $organization))->toBeTrue();
    });

    it("lets the member's own choice override the organization", function () {
        $organization = Organization::factory()->create(['settings' => ['notifications' => ['email' => ['member_joined' => true]]]]);
        $user = User::factory()->create(['notification_preferences' => ['email' => ['member_joined' => false]]]);

        expect(app(NotificationPreferences::class)->wantsEmail($user, NotificationType::MemberJoined, $organization))->toBeFalse();
    });

    it('emails only when the preference says so', function () {
        $organization = Organization::factory()->create();
        $quiet = User::factory()->create(['notification_preferences' => ['email' => ['member_joined' => false]]]);
        $keen = User::factory()->create(['notification_preferences' => ['email' => ['member_joined' => true]]]);

        $notification = app(Tenancy::class)->run($organization, fn () => new MemberJoinedNotification('Priya', 'Finance'));

        expect($notification->via($quiet))->toBe(['database'])
            ->and($notification->via($keen))->toBe(['database', 'mail']);
    });

    it('saves the member choices and ignores unknown types', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->put(route('notification-preferences.update'), ['email' => ['task_assigned' => false, 'made_up' => true]])
            ->assertRedirect();

        expect($user->fresh()->notification_preferences['email'])->toBe(['task_assigned' => false]);
    });
});
