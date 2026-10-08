<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\Tenancy;

class OrganizationMembershipPolicy
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MembersView->value);
    }

    /**
     * Change a member's role, status or details.
     */
    public function update(User $user, OrganizationMembership $target): bool
    {
        return $this->canManage($user, $target);
    }

    /**
     * Remove a member from the organization.
     */
    public function delete(User $user, OrganizationMembership $target): bool
    {
        return $this->canManage($user, $target);
    }

    /**
     * Managing a member needs the permission, and three guards on top of it:
     * nobody edits themselves here, nobody touches the owner, and you can only
     * manage people you outrank.
     */
    private function canManage(User $user, OrganizationMembership $target): bool
    {
        $organization = $this->tenancy->current();
        $actor = $this->tenancy->membershipFor($user);

        if ($organization === null || $actor === null || $target->organization_id !== $organization->id) {
            return false;
        }

        if (! $actor->allows(Permission::MembersManage)) {
            return false;
        }

        if ($target->user_id === $user->id || $target->user_id === $organization->owner_id) {
            return false;
        }

        return $actor->role->outranks($target->role);
    }
}
