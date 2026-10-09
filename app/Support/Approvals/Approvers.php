<?php

namespace App\Support\Approvals;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Models\Approval;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The people an approval is waiting on: the named approver, or every active
 * member of the approver role who is allowed to approve. Never the requester.
 */
class Approvers
{
    /**
     * @return Collection<int, User>
     */
    public function for(Approval $approval): Collection
    {
        $memberships = OrganizationMembership::query()
            ->where('organization_id', $approval->organization_id)
            ->where('status', MembershipStatus::Active)
            ->when(
                $approval->approver_id !== null,
                fn ($query) => $query->where('user_id', $approval->approver_id),
                fn ($query) => $query->where('role', $approval->approver_role?->value),
            )
            ->with('user')
            ->get();

        return $memberships
            ->filter(fn (OrganizationMembership $membership): bool => $membership->allows(Permission::ApprovalsApprove)
                || $membership->allows(Permission::ApprovalsReject))
            ->map(fn (OrganizationMembership $membership): User => $membership->user)
            ->reject(fn (User $user): bool => $user->id === $approval->requester_id)
            ->values()
            ->toBase();
    }

    /**
     * Who the request is with, in words: "Priya Nair" or "anyone in Finance".
     */
    public function describe(Approval $approval): string
    {
        if ($approval->approver_id !== null) {
            return $approval->approver->name ?? 'a former member';
        }

        return 'anyone in '.($approval->approver_role?->label() ?? 'the approver role');
    }
}
