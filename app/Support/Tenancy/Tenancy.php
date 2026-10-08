<?php

namespace App\Support\Tenancy;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Context;

/**
 * Holds the organization the current request, job or command is working for.
 *
 * Tenant-owned models read this to scope every query, so there is exactly one
 * place that decides "whose data is this".
 */
class Tenancy
{
    /**
     * The key used to carry the organization into queued jobs and log entries.
     */
    public const string CONTEXT_KEY = 'organization_id';

    private ?Organization $organization = null;

    private ?OrganizationMembership $membership = null;

    /**
     * @var array<string, OrganizationMembership|null>
     */
    private array $otherMemberships = [];

    public function current(): ?Organization
    {
        return $this->organization;
    }

    public function currentOrFail(): Organization
    {
        return $this->organization ?? throw new MissingTenantContext;
    }

    public function id(): ?string
    {
        return $this->organization?->id;
    }

    public function check(): bool
    {
        return $this->organization !== null;
    }

    /**
     * The signed-in user's membership of the current organization, when there is one.
     */
    public function membership(): ?OrganizationMembership
    {
        return $this->membership;
    }

    public function allows(Permission $permission): bool
    {
        return $this->membership?->allows($permission) ?? false;
    }

    /**
     * Any user's membership of the current organization. The signed-in user's
     * is already loaded; others (an approver being checked, say) are looked up once.
     */
    public function membershipFor(User $user): ?OrganizationMembership
    {
        if ($this->organization === null) {
            return null;
        }

        if ($this->membership?->user_id === $user->id) {
            return $this->membership;
        }

        return $this->otherMemberships[$this->organization->id.':'.$user->id] ??= OrganizationMembership::query()
            ->where('organization_id', $this->organization->id)
            ->where('user_id', $user->id)
            ->first();
    }

    public function set(Organization $organization, ?OrganizationMembership $membership = null): void
    {
        $this->organization = $organization;
        $this->membership = $membership;

        Context::add(self::CONTEXT_KEY, $organization->id);
    }

    public function forget(): void
    {
        $this->organization = null;
        $this->membership = null;

        Context::forget(self::CONTEXT_KEY);
    }

    /**
     * Run a callback for the given organization, then restore whatever was current before.
     *
     * @template TReturn
     *
     * @param  Closure(Organization): TReturn  $callback
     * @return TReturn
     */
    public function run(Organization $organization, Closure $callback, ?OrganizationMembership $membership = null): mixed
    {
        $previousOrganization = $this->organization;
        $previousMembership = $this->membership;

        $this->set($organization, $membership);

        try {
            return $callback($organization);
        } finally {
            if ($previousOrganization) {
                $this->set($previousOrganization, $previousMembership);
            } else {
                $this->forget();
            }
        }
    }
}
