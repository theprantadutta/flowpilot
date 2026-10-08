<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TasksView->value);
    }

    public function view(User $user, Task $task): bool
    {
        return $user->can(Permission::TasksView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TasksCreate->value);
    }

    /**
     * Task editors, plus whoever the task is assigned to.
     */
    public function update(User $user, Task $task): bool
    {
        return $user->can(Permission::TasksUpdate->value)
            || ($task->assignee_id === $user->id && $user->can(Permission::TasksView->value));
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can(Permission::TasksDelete->value)
            || ($task->reporter_id === $user->id && $user->can(Permission::TasksCreate->value) && $task->created_at?->gt(now()->subDay()));
    }
}
