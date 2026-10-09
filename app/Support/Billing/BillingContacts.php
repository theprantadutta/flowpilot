<?php

namespace App\Support\Billing;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Active members who look after the organization's plan: the people told
 * about trials ending and limits being reached.
 */
class BillingContacts
{
    /**
     * @return Collection<int, User>
     */
    public function for(Organization $organization): Collection
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', MembershipStatus::Active)
            ->with('user')
            ->get()
            ->filter(fn (OrganizationMembership $membership): bool => $membership->allows(Permission::BillingManage)
                || $membership->allows(Permission::SettingsManage))
            ->map(fn (OrganizationMembership $membership): User => $membership->user)
            ->values()
            ->toBase();
    }
}
