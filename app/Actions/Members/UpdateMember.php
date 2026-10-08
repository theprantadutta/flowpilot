<?php

namespace App\Actions\Members;

use App\Enums\MembershipStatus;
use App\Enums\Role;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Activity\ActivityLogger;

class UpdateMember
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Change a member's role, status, department or job title.
     *
     * Authorization (who may change whom, and to which role) is decided by the
     * policy and form request before this runs.
     *
     * @param  array{role?: Role, status?: MembershipStatus, department?: string|null, job_title?: string|null}  $changes
     */
    public function handle(OrganizationMembership $membership, array $changes, User $actor): OrganizationMembership
    {
        $before = [
            'role' => $membership->role->value,
            'status' => $membership->status->value,
            'department' => $membership->department,
            'job_title' => $membership->job_title,
        ];

        $membership->fill($changes)->save();

        $after = [
            'role' => $membership->role->value,
            'status' => $membership->status->value,
            'department' => $membership->department,
            'job_title' => $membership->job_title,
        ];

        $diff = ActivityLogger::diff($before, $after);

        if ($diff === []) {
            return $membership;
        }

        $action = match (true) {
            isset($diff['role']) => 'member.role_changed',
            isset($diff['status']) && $membership->status === MembershipStatus::Suspended => 'member.suspended',
            isset($diff['status']) => 'member.reactivated',
            default => 'member.updated',
        };

        $this->activity->log($action, $membership, [
            'name' => $membership->user->name,
            'changes' => $diff,
        ], actor: $actor);

        return $membership;
    }
}
