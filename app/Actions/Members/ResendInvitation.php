<?php

namespace App\Actions\Members;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResendInvitation
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Issue a fresh link for an unanswered invitation. The previous link stops working.
     */
    public function handle(Invitation $invitation, User $sender): Invitation
    {
        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation has already been used or revoked.',
            ]);
        }

        $token = Str::random(48);

        $invitation->update([
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays((int) config('flowpilot.invitations.expires_after_days', 7)),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation, $token, $sender->name));

        $this->activity->log('member.invitation_resent', $invitation, ['email' => $invitation->email], actor: $sender);

        return $invitation;
    }
}
