<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workflow;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A new user who is an active member of the organization with the given role.
 */
function memberIn(Organization $organization, Role $role = Role::Employee, array $attributes = []): User
{
    return User::factory()->memberOf($organization, $role)->create($attributes);
}

/**
 * Run code as if inside a request for the organization, e.g. to create or read
 * tenant-owned records in a test.
 *
 * @template TReturn
 *
 * @param  Closure(Organization): TReturn  $callback
 * @return TReturn
 */
function inTenant(Organization $organization, Closure $callback): mixed
{
    return app(Tenancy::class)->run($organization, $callback);
}

/**
 * A workflow step for test graphs.
 *
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function step(string $id, string $type, array $config = [], ?string $label = null): array
{
    return ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => 0], 'data' => ['label' => $label ?? ucfirst(str_replace('_', ' ', $id)), 'config' => $config]];
}

/**
 * A connection between two steps, leaving through a path ("next", "true"…).
 *
 * @return array<string, string>
 */
function path(string $source, string $target, string $handle = 'next'): array
{
    return ['id' => "{$source}-{$handle}-{$target}", 'source' => $source, 'target' => $target, 'sourceHandle' => $handle];
}

/**
 * A published, active workflow with the given steps and connections.
 *
 * @param  list<array<string, mixed>>  $steps
 * @param  list<array<string, string>>  $paths
 */
function publishedWorkflow(Organization $organization, array $steps, array $paths, string $trigger = 'manual', string $name = 'Test workflow'): Workflow
{
    return Workflow::factory()
        ->for($organization)
        ->withGraph(['nodes' => $steps, 'edges' => $paths], $trigger)
        ->published()
        ->create(['name' => $name]);
}
