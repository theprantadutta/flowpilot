<?php

namespace App\Actions\Members;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Activity\ActivityLogger;

class RevokeInvitation
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function handle(Invitation $invitation, User $actor): void
    {
        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            return;
        }

        $invitation->update(['revoked_at' => now()]);

        $this->activity->log('member.invitation_revoked', $invitation, ['email' => $invitation->email], actor: $actor);
    }
}
