<?php

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Context;

function logFor(Organization $organization, string $action): ActivityLog
{
    return app(Tenancy::class)->run($organization, fn () => ActivityLog::query()->create(['action' => $action]));
}

it('only returns records that belong to the current organization', function () {
    $northstar = Organization::factory()->create();
    $harbor = Organization::factory()->create();

    logFor($northstar, 'northstar.event');
    logFor($harbor, 'harbor.event');

    $actions = app(Tenancy::class)->run($northstar, fn () => ActivityLog::query()->pluck('action')->all());

    expect($actions)->toBe(['northstar.event']);
});

it('stamps new records with the current organization', function () {
    $organization = Organization::factory()->create();

    $log = logFor($organization, 'something.happened');

    expect($log->organization_id)->toBe($organization->id);
});

it('refuses to query tenant-owned records when no organization is set', function () {
    ActivityLog::query()->count();
})->throws(MissingTenantContext::class);

it('refuses to create tenant-owned records when no organization is set', function () {
    ActivityLog::query()->create(['action' => 'orphan.event']);
})->throws(MissingTenantContext::class);

it('can read across organizations only when the scope is removed explicitly', function () {
    logFor(Organization::factory()->create(), 'first.event');
    logFor(Organization::factory()->create(), 'second.event');

    expect(ActivityLog::withoutOrganizationScope()->count())->toBe(2);
});

it('restores the previous organization after running work for another one', function () {
    $tenancy = app(Tenancy::class);
    $outer = Organization::factory()->create();
    $inner = Organization::factory()->create();

    $tenancy->set($outer);
    $seenInside = $tenancy->run($inner, fn () => $tenancy->id());

    expect($seenInside)->toBe($inner->id)
        ->and($tenancy->id())->toBe($outer->id);
});

it('clears the organization after running work when none was set before', function () {
    $tenancy = app(Tenancy::class);

    $tenancy->run(Organization::factory()->create(), fn () => null);

    expect($tenancy->check())->toBeFalse();
});

it('restores the organization in queued work from the job context', function () {
    $tenancy = app(Tenancy::class);
    $organization = Organization::factory()->create();

    $tenancy->set($organization);
    $payload = Context::dehydrate();

    // A queue worker starts with no tenant, then hydrates the job's context.
    $tenancy->forget();
    Context::hydrate($payload);

    expect($tenancy->id())->toBe($organization->id);
});
