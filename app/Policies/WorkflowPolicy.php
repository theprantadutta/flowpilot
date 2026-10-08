<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workflow;

class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WorkflowsView->value);
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $user->can(Permission::WorkflowsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WorkflowsCreate->value);
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $user->can(Permission::WorkflowsCreate->value);
    }

    /**
     * Publishing (and pausing or resuming) changes what runs for everyone.
     */
    public function publish(User $user, Workflow $workflow): bool
    {
        return $user->can(Permission::WorkflowsPublish->value);
    }

    /**
     * Starting a run by hand: only for published, active workflows started by a person.
     */
    public function execute(User $user, Workflow $workflow): bool
    {
        return $user->can(Permission::WorkflowsExecute->value)
            && $workflow->isActive()
            && $workflow->trigger_type === 'manual';
    }

    public function delete(User $user, Workflow $workflow): bool
    {
        return $user->can(Permission::WorkflowsDelete->value);
    }
}
