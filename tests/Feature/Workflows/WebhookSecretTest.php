<?php

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Organization;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('reveals the signing secret on request and records who saw it', function () {
    $organization = Organization::factory()->create();

    actingAs($organization->owner)
        ->get(route('organization-settings.show', [$organization, 'section' => 'security']))
        ->assertInertia(fn (Assert $page) => $page->where('settings.webhook_secret_hint', null));

    $secret = actingAs($organization->owner)
        ->getJson(route('organization-settings.webhook-secret.show', $organization))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->json('secret');

    expect($secret)->toStartWith('whsec_')
        ->and($organization->refresh()->webhookSecret())->toBe($secret)
        ->and(inTenant($organization, fn () => ActivityLog::query()->where('action', 'settings.webhook_secret_viewed')->value('actor_id')))->toBe($organization->owner_id);

    // The settings page only ever carries the last characters.
    actingAs($organization->owner)
        ->get(route('organization-settings.show', [$organization, 'section' => 'security']))
        ->assertInertia(fn (Assert $page) => $page->where('settings.webhook_secret_hint', substr($secret, -4)));
});

it('replaces the signing secret', function () {
    $organization = Organization::factory()->create();
    $old = $organization->webhookSecret();

    actingAs($organization->owner)
        ->post(route('organization-settings.webhook-secret.rotate', $organization))
        ->assertRedirect();

    expect($organization->refresh()->webhookSecret())->not->toBe($old)
        ->and(inTenant($organization, fn () => ActivityLog::query()->where('action', 'settings.webhook_secret_rotated')->exists()))->toBeTrue();
});

it('keeps the secret from members who do not manage settings and from other organizations', function () {
    $organization = Organization::factory()->create();
    $manager = memberIn($organization, Role::Manager);
    $outsider = Organization::factory()->create()->owner;

    actingAs($manager)->getJson(route('organization-settings.webhook-secret.show', $organization))->assertForbidden();
    actingAs($manager)->post(route('organization-settings.webhook-secret.rotate', $organization))->assertForbidden();
    actingAs($outsider)->getJson(route('organization-settings.webhook-secret.show', $organization))->assertNotFound();
});
