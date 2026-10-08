<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\Tenancy;

use function Pest\Laravel\actingAs;

function validOrganization(array $overrides = []): array
{
    return [
        'name' => 'Northstar Manufacturing',
        'industry' => 'manufacturing',
        'company_size' => '51-200',
        'primary_use_case' => 'purchasing',
        'timezone' => 'America/Chicago',
        'currency' => 'USD',
        ...$overrides,
    ];
}

it('shows the setup flow', function () {
    actingAs(User::factory()->create())
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/Create')
            ->where('isFirstOrganization', true)
            ->has('industries', 8));
});

it('creates the organization with the user as owner and opens it', function () {
    $user = User::factory()->create();

    $response = actingAs($user)->post(route('onboarding.store'), validOrganization());

    $organization = Organization::query()->where('name', 'Northstar Manufacturing')->sole();

    $response->assertRedirect(route('overview', $organization));

    expect($organization)
        ->owner_id->toBe($user->id)
        ->slug->toBe('northstar-manufacturing')
        ->timezone->toBe('America/Chicago')
        ->and($organization->memberships()->sole())
        ->user_id->toBe($user->id)
        ->role->toBe(Role::Owner)
        ->and($user->fresh()->last_organization_id)->toBe($organization->id);

    $logged = app(Tenancy::class)->run($organization, fn () => ActivityLog::query()->pluck('action')->all());
    expect($logged)->toBe(['organization.created']);
});

it('gives a second organization with the same name its own address', function () {
    Organization::factory()->create(['slug' => 'northstar-manufacturing']);

    actingAs(User::factory()->create())->post(route('onboarding.store'), validOrganization());

    expect(Organization::query()->pluck('slug')->all())
        ->toContain('northstar-manufacturing', 'northstar-manufacturing-2');
});

it('explains every missing answer', function () {
    actingAs(User::factory()->create())
        ->post(route('onboarding.store'), [])
        ->assertSessionHasErrors([
            'name' => 'Give your organization a name.',
            'industry' => 'Choose the industry that fits best.',
            'company_size' => 'Choose a company size.',
            'primary_use_case' => 'Choose what you want to use FlowPilot for first.',
        ]);

    expect(Organization::query()->count())->toBe(0);
});

it('rejects values outside the offered choices', function (string $field, string $value) {
    actingAs(User::factory()->create())
        ->post(route('onboarding.store'), validOrganization([$field => $value]))
        ->assertSessionHasErrors($field);
})->with([
    'unknown industry' => ['industry', 'shipbuilding'],
    'unknown size' => ['company_size', '9000'],
    'unknown use case' => ['primary_use_case', 'mining'],
    'unknown timezone' => ['timezone', 'Mars/Olympus_Mons'],
    'unsupported currency' => ['currency', 'XYZ'],
]);

it('ignores an owner or organization id sent with the form', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();

    actingAs($user)->post(route('onboarding.store'), validOrganization([
        'owner_id' => $someoneElse->id,
        'status' => 'suspended',
    ]));

    expect(Organization::query()->sole())
        ->owner_id->toBe($user->id)
        ->isActive()->toBeTrue();
});
