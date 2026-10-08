<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Issue;
use App\Models\User;

class IssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::IssuesView->value);
    }

    public function view(User $user, Issue $issue): bool
    {
        return $user->can(Permission::IssuesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::IssuesCreate->value);
    }

    /**
     * Issue editors, plus the person it is assigned to.
     */
    public function update(User $user, Issue $issue): bool
    {
        return $user->can(Permission::IssuesUpdate->value)
            || ($issue->assignee_id === $user->id && $user->can(Permission::IssuesView->value));
    }

    public function delete(User $user, Issue $issue): bool
    {
        return $user->can(Permission::IssuesDelete->value);
    }
}
