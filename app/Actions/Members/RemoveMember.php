<?php

namespace App\Actions\Members;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;

class RemoveMember
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Remove a member from the organization. Their user account, and the work
     * they did here, are kept.
     */
    public function handle(OrganizationMembership $membership, User $actor): void
    {
        DB::transaction(function () use ($membership, $actor): void {
            $member = $membership->user;

            $this->activity->log('member.removed', $membership, [
                'name' => $member->name,
                'email' => $member->email,
                'role' => $membership->role->value,
            ], actor: $actor);

            $membership->delete();

            if ($member->last_organization_id === $membership->organization_id) {
                $member->forceFill(['last_organization_id' => null])->save();
            }
        });
    }
}
