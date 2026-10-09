<?php

namespace App\Support\Inventory;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Active members who look after stock: the people told about low stock and
 * new purchase requests.
 */
class InventoryManagers
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
            ->filter(fn (OrganizationMembership $membership): bool => $membership->allows(Permission::InventoryManage))
            ->map(fn (OrganizationMembership $membership): User => $membership->user)
            ->values()
            ->toBase();
    }
}
