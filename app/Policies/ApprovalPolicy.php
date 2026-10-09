<?php

namespace App\Policies;

use App\Enums\ApprovalStatus;
use App\Enums\Permission;
use App\Models\Approval;
use App\Models\User;
use App\Support\Tenancy\Tenancy;

/**
 * Who may see, decide and change approval requests.
 *
 * Nobody decides their own request, whatever their role: separation of
 * duties holds even for owners.
 */
class ApprovalPolicy
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ApprovalsView->value);
    }

    public function view(User $user, Approval $approval): bool
    {
        return $user->can(Permission::ApprovalsView->value) || $approval->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ApprovalsRequest->value);
    }

    /**
     * Adding files: the requester and the people the request waits on, while it is open.
     */
    public function update(User $user, Approval $approval): bool
    {
        return $approval->status->isOpen()
            && ($approval->requester_id === $user->id || $this->isDecider($user, $approval));
    }

    public function approve(User $user, Approval $approval): bool
    {
        return $approval->status === ApprovalStatus::Pending
            && $user->can(Permission::ApprovalsApprove->value)
            && $this->isDecider($user, $approval);
    }

    public function reject(User $user, Approval $approval): bool
    {
        return $approval->status === ApprovalStatus::Pending
            && $user->can(Permission::ApprovalsReject->value)
            && $this->isDecider($user, $approval);
    }

    /**
     * Asking for changes is a kind of "not yet", so it needs the right to reject.
     */
    public function requestChanges(User $user, Approval $approval): bool
    {
        return $this->reject($user, $approval);
    }

    public function resubmit(User $user, Approval $approval): bool
    {
        return $approval->status === ApprovalStatus::ChangesRequested && $approval->requester_id === $user->id;
    }

    /**
     * Requests raised by hand can be withdrawn; ones a workflow raised end with the run.
     */
    public function withdraw(User $user, Approval $approval): bool
    {
        return $approval->status->isOpen()
            && $approval->workflow_run_id === null
            && $approval->requester_id === $user->id;
    }

    private function isDecider(User $user, Approval $approval): bool
    {
        if ($approval->requester_id === $user->id) {
            return false;
        }

        return $approval->isWaitingOn($user, $this->tenancy->membershipFor($user))
            || $user->can(Permission::ApprovalsOverride->value);
    }
}
