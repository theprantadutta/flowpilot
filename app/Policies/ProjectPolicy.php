<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProjectsView->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can(Permission::ProjectsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ProjectsCreate->value);
    }

    /**
     * Project editors, plus the project's own owner whatever their role.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->can(Permission::ProjectsUpdate->value)
            || ($project->owner_id === $user->id && $user->can(Permission::ProjectsView->value));
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can(Permission::ProjectsDelete->value);
    }
}
