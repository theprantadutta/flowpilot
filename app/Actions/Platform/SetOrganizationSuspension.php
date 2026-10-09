<?php

namespace App\Actions\Platform;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use Illuminate\Validation\ValidationException;

/**
 * Suspends an organization (members can no longer open it; nothing is
 * deleted) or lifts a suspension.
 */
class SetOrganizationSuspension
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(Organization $organization, User $admin, bool $suspended, ?string $reason = null): Organization
    {
        $target = $suspended ? OrganizationStatus::Suspended : OrganizationStatus::Active;

        if ($organization->status === $target) {
            throw ValidationException::withMessages(['status' => $suspended ? 'The organization is already suspended.' : 'The organization is not suspended.']);
        }

        $organization->forceFill(['status' => $target])->save();

        $this->activity->log($suspended ? 'platform.organization_suspended' : 'platform.organization_reactivated', $organization, [
            'reason' => $reason,
        ], actor: $admin, actorType: 'platform', organization: $organization);

        return $organization;
    }
}
