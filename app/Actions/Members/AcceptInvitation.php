<?php

namespace App\Actions\Members;

use App\Enums\MembershipStatus;
use App\Enums\Permission;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\MemberJoinedNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function __construct(
        private readonly Tenancy $tenancy,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Turn an invitation into a membership for the signed-in user.
     *
     * Safe to call twice: a second acceptance returns the existing membership.
     */
    public function handle(Invitation $invitation, User $user): OrganizationMembership
    {
        if (Str::lower($invitation->email) !== Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'invitation' => "This invitation was sent to {$invitation->email}. Sign in with that email address to accept it.",
            ]);
        }

        return DB::transaction(function () use ($invitation, $user): OrganizationMembership {
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($invitation->id);

            $existing = OrganizationMembership::query()
                ->where('organization_id', $invitation->organization_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                if ($invitation->accepted_at === null && $invitation->revoked_at === null) {
                    $invitation->update(['accepted_at' => now()]);
                }

                return $existing;
            }

            if (! $invitation->isOpen()) {
                throw ValidationException::withMessages([
                    'invitation' => $invitation->isExpired()
                        ? 'This invitation has expired. Ask the person who invited you to send a new one.'
                        : 'This invitation is no longer valid.',
                ]);
            }

            $membership = OrganizationMembership::query()->create([
                'organization_id' => $invitation->organization_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'status' => MembershipStatus::Active,
                'department' => $invitation->department,
                'invited_by' => $invitation->invited_by,
                'invited_at' => $invitation->created_at,
                'joined_at' => now(),
            ]);

            $invitation->update(['accepted_at' => now()]);

            // Accepting proves the user controls the invited mailbox.
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->forceFill(['last_organization_id' => $invitation->organization_id])->save();
            $user->unsetRelation('memberships');

            $this->tenancy->run($invitation->organization, function () use ($membership, $user): void {
                $this->activity->log(
                    'member.joined',
                    $membership,
                    ['role' => $membership->role->value, 'name' => $user->name],
                    actor: $user,
                );

                $this->notifyManagers($membership);
            });

            return $membership;
        });
    }

    /**
     * Let the people who manage members know someone new is in.
     */
    private function notifyManagers(OrganizationMembership $membership): void
    {
        $managers = OrganizationMembership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('status', MembershipStatus::Active)
            ->where('user_id', '!=', $membership->user_id)
            ->with('user')
            ->get()
            ->filter(fn (OrganizationMembership $candidate): bool => $candidate->allows(Permission::MembersManage))
            ->map(fn (OrganizationMembership $candidate) => $candidate->user);

        if ($managers->isNotEmpty()) {
            Notification::send($managers, MemberJoinedNotification::for($membership));
        }
    }
}
