<?php

namespace App\Actions\Members;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteMember
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * Invite someone to the organization by email. Any earlier unanswered
     * invitation for the same address is replaced, so only the newest link works.
     */
    public function handle(Organization $organization, User $inviter, string $email, Role $role, ?string $department = null): Invitation
    {
        $email = Str::lower(trim($email));

        $alreadyMember = $organization->memberships()
            ->whereHas('user', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => 'That person is already a member of this organization.',
            ]);
        }

        $token = Str::random(48);

        $invitation = DB::transaction(function () use ($organization, $inviter, $email, $role, $department, $token): Invitation {
            $organization->invitations()
                ->unanswered()
                ->whereRaw('lower(email) = ?', [$email])
                ->update(['revoked_at' => now()]);

            return $organization->invitations()->create([
                'email' => $email,
                'role' => $role,
                'department' => $department,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays((int) config('flowpilot.invitations.expires_after_days', 7)),
            ]);
        });

        Notification::route('mail', $email)
            ->notify(new OrganizationInvitationNotification($invitation, $token, $inviter->name));

        $this->activity->log('member.invited', $invitation, [
            'email' => $email,
            'role' => $role->value,
        ], actor: $inviter);

        return $invitation;
    }
}
