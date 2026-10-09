<?php

namespace App\Policies;

use App\Actions\Inventory\SubmitPurchaseRequest;
use App\Enums\Permission;
use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InventoryView->value) || $user->can(Permission::InventoryRequest->value);
    }

    public function view(User $user, PurchaseRequest $request): bool
    {
        return $user->can(Permission::InventoryView->value) || $request->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InventoryRequest->value);
    }

    /**
     * Deciding by hand is only for organizations without an approval workflow
     * for purchase requests; otherwise the workflow decides. Never one's own.
     */
    public function decide(User $user, PurchaseRequest $request): bool
    {
        return $request->status === PurchaseRequestStatus::Submitted
            && $request->requester_id !== $user->id
            && $user->can(Permission::InventoryManage->value)
            && $user->can(Permission::ApprovalsApprove->value)
            && ! app(SubmitPurchaseRequest::class)->hasApprovalWorkflow();
    }

    public function order(User $user, PurchaseRequest $request): bool
    {
        return $request->status === PurchaseRequestStatus::Approved && $user->can(Permission::InventoryManage->value);
    }

    public function receive(User $user, PurchaseRequest $request): bool
    {
        return in_array($request->status, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::Ordered], true)
            && $user->can(Permission::InventoryManage->value);
    }

    public function cancel(User $user, PurchaseRequest $request): bool
    {
        if (! $request->status->isOpen() || $request->received_quantity > 0) {
            return false;
        }

        return $user->can(Permission::InventoryManage->value)
            || ($request->requester_id === $user->id && $request->status === PurchaseRequestStatus::Submitted);
    }
}
