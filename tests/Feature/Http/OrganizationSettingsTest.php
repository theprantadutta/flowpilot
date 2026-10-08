<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('shows each settings section to members who manage settings', function (string $section, string $component) {
    $organization = Organization::factory()->create();

    actingAs($organization->owner)
        ->get(route('organization-settings.show', [$organization, $section]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component)->where('section', $section));
})->with([
    ['general', 'organization-settings/General'],
    ['regional', 'organization-settings/Regional'],
    ['notifications', 'organization-settings/Notifications'],
    ['security', 'organization-settings/Security'],
    ['members', 'organization-settings/Members'],
]);

it('forbids members who cannot manage settings', function (Role $role) {
    $organization = Organization::factory()->create();

    actingAs(User::factory()->memberOf($organization, $role)->create())
        ->patch(route('organization-settings.update', [$organization, 'regional']), ['timezone' => 'UTC', 'currency' => 'USD', 'date_format' => 'Y-m-d'])
        ->assertForbidden();
})->with([Role::Manager, Role::Finance, Role::Employee, Role::Auditor]);

it('saves the regional settings and records the change', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);

    actingAs($organization->owner)
        ->patch(route('organization-settings.update', [$organization, 'regional']), [
            'timezone' => 'Asia/Dhaka',
            'currency' => 'BDT',
            'date_format' => 'j M Y',
        ])
        ->assertRedirect();

    expect($organization->fresh())
        ->timezone->toBe('Asia/Dhaka')
        ->currency->toBe('BDT')
        ->date_format->toBe('j M Y');

    $log = app(Tenancy::class)->run($organization, fn () => ActivityLog::query()->where('action', 'settings.updated')->sole());
    expect($log->properties['section'])->toBe('regional')
        ->and($log->properties['changes']['timezone'])->toBe(['from' => 'UTC', 'to' => 'Asia/Dhaka']);
});

it('only accepts the fields of the section being saved', function () {
    $organization = Organization::factory()->create(['name' => 'Northstar']);

    actingAs($organization->owner)->patch(route('organization-settings.update', [$organization, 'regional']), [
        'timezone' => 'UTC', 'currency' => 'USD', 'date_format' => 'Y-m-d',
        'name' => 'Hijacked', 'owner_id' => 999, 'status' => 'suspended',
    ]);

    expect($organization->fresh())->name->toBe('Northstar')->isActive()->toBeTrue();
});

it('rejects an invalid website and phone number', function () {
    $organization = Organization::factory()->create();

    actingAs($organization->owner)
        ->patch(route('organization-settings.update', [$organization, 'general']), [
            'name' => 'Northstar',
            'website' => 'javascript:alert(1)',
            'contact_phone' => '<script>',
        ])
        ->assertSessionHasErrors([
            'website' => 'Enter a full web address, starting with https://.',
            'contact_phone' => 'Use digits, spaces and + ( ) - only.',
        ]);
});

it('refuses to require two-factor while the person saving does not use it', function () {
    $organization = Organization::factory()->create();

    actingAs($organization->owner)
        ->patch(route('organization-settings.update', [$organization, 'security']), ['require_two_factor' => true, 'idle_timeout_minutes' => 0])
        ->assertSessionHasErrors(['require_two_factor' => 'Turn on two-factor authentication for your own account first, so you are not locked out.']);
});

it('stores only email defaults that differ from each type', function () {
    $organization = Organization::factory()->create();

    actingAs($organization->owner)->patch(route('organization-settings.update', [$organization, 'notifications']), [
        'email' => ['approval_required' => true, 'member_joined' => true, 'task_assigned' => false],
    ]);

    expect($organization->fresh()->settings['notifications']['email'])->toBe([
        'task_assigned' => false,
        'member_joined' => true,
    ]);
});

describe('logo', function () {
    it('stores an uploaded logo under a random name and replaces the old one', function () {
        Storage::fake('public');
        $organization = Organization::factory()->create();

        actingAs($organization->owner)->post(route('organization-settings.logo.update', $organization), [
            'logo' => UploadedFile::fake()->image('first.png', 200, 200),
        ])->assertRedirect();
        $first = $organization->fresh()->logo_path;

        actingAs($organization->owner)->post(route('organization-settings.logo.update', $organization), [
            'logo' => UploadedFile::fake()->image('second.webp', 200, 200),
        ]);
        $second = $organization->fresh()->logo_path;

        expect($first)->toStartWith("organizations/{$organization->id}/logo-")->not->toContain('first')
            ->and($second)->not->toBe($first);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    });

    it('rejects files that are not raster images', function (UploadedFile $file) {
        Storage::fake('public');
        $organization = Organization::factory()->create();

        actingAs($organization->owner)
            ->post(route('organization-settings.logo.update', $organization), ['logo' => $file])
            ->assertSessionHasErrors('logo');

        expect($organization->fresh()->logo_path)->toBeNull();
    })->with([
        'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'php disguised as png' => fn () => UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1;'),
        'too small' => fn () => UploadedFile::fake()->image('tiny.png', 16, 16),
        'too large' => fn () => UploadedFile::fake()->image('huge.png', 300, 300)->size(5000),
    ]);
});

describe('enforcement', function () {
    it('sends members without two-factor to set it up when the organization requires it', function () {
        $organization = Organization::factory()->create(['settings' => ['security' => ['require_two_factor' => true]]]);
        $member = User::factory()->memberOf($organization)->create();

        actingAs($member)->get(route('overview', $organization))->assertRedirect(route('security.edit'));
        actingAs(User::factory()->withTwoFactor()->memberOf($organization)->create())
            ->get(route('overview', $organization))
            ->assertOk();
    });

    it('signs members out after the idle timeout', function () {
        $organization = Organization::factory()->create(['settings' => ['security' => ['idle_timeout_minutes' => 15]]]);
        $member = User::factory()->memberOf($organization)->create();

        actingAs($member)
            ->withSession(["organization_activity.{$organization->id}" => now()->subMinutes(16)->timestamp])
            ->get(route('overview', $organization))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });

    it('keeps an active member signed in within the idle timeout', function () {
        $organization = Organization::factory()->create(['settings' => ['security' => ['idle_timeout_minutes' => 15]]]);
        $member = User::factory()->memberOf($organization)->create();

        actingAs($member)
            ->withSession(["organization_activity.{$organization->id}" => now()->subMinutes(10)->timestamp])
            ->get(route('overview', $organization))
            ->assertOk();
    });

    it('lets any member invite colleagues as Employee when the organization allows it', function () {
        Notification::fake();
        $organization = Organization::factory()->create(['settings' => ['members' => ['allow_member_invites' => true]]]);
        $employee = User::factory()->memberOf($organization, Role::Employee)->create();

        actingAs($employee)
            ->post(route('members.invitations.store', $organization), ['email' => 'friend@northstar.test', 'role' => 'employee'])
            ->assertRedirect();
        actingAs($employee)
            ->post(route('members.invitations.store', $organization), ['email' => 'boss@northstar.test', 'role' => 'manager'])
            ->assertSessionHasErrors(['role' => 'You can invite colleagues as Employee. Ask an admin to give them a different role.']);

        expect($organization->invitations()->pluck('email')->all())->toBe(['friend@northstar.test']);
    });
});
