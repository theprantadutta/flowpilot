<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InventoryView->value);
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->can(Permission::InventoryView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InventoryManage->value);
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->can(Permission::InventoryManage->value);
    }

    /**
     * Receiving, issuing, counting and moving stock.
     */
    public function move(User $user, InventoryItem $item): bool
    {
        return $item->is_active && $user->can(Permission::InventoryManage->value);
    }
}
