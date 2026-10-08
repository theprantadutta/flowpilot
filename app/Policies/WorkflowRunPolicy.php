<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\WorkflowRunStatus;
use App\Models\User;
use App\Models\WorkflowRun;

class WorkflowRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WorkflowsView->value);
    }

    public function view(User $user, WorkflowRun $run): bool
    {
        return $user->can(Permission::WorkflowsView->value);
    }

    /**
     * Whoever started a run may stop it; so may the people who publish workflows.
     */
    public function cancel(User $user, WorkflowRun $run): bool
    {
        if ($run->status->isFinished()) {
            return false;
        }

        return $user->can(Permission::WorkflowsPublish->value)
            || ($run->started_by === $user->id && $user->can(Permission::WorkflowsExecute->value));
    }

    public function retry(User $user, WorkflowRun $run): bool
    {
        return $run->status === WorkflowRunStatus::Failed && $user->can(Permission::WorkflowsPublish->value);
    }
}
