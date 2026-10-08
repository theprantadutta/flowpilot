<?php

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
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
