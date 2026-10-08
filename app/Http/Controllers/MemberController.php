<?php

namespace App\Http\Controllers;

use App\Actions\Members\RemoveMember;
use App\Actions\Members\UpdateMember;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\Members\UpdateMemberRequest;
use App\Models\Invitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request, Tenancy $tenancy): Response
    {
        Gate::authorize('viewAny', OrganizationMembership::class);

        /** @var User $user */
        $user = $request->user();
        $organization = $tenancy->currentOrFail();
        $actorRole = $tenancy->membership()?->role;
        $canInviteFreely = $user->can(Permission::MembersInvite->value);
        // With member invites switched on, everyone may invite, but only as Employee.
        $canInviteAsEmployee = ! $canInviteFreely && (bool) $organization->setting('members.allow_member_invites');
        $canInvite = $canInviteFreely || $canInviteAsEmployee;

        $members = $organization->memberships()
            ->with('user:id,name,email,avatar_path')
            ->orderBy('joined_at')
            ->get()
            ->map(fn (OrganizationMembership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'avatar' => $membership->user->avatar,
                'role' => $membership->role->value,
                'role_label' => $membership->role->label(),
                'status' => $membership->status->value,
                'department' => $membership->department,
                'job_title' => $membership->job_title,
                'joined_at' => $membership->joined_at?->toIso8601String(),
                'last_active_at' => $membership->last_active_at?->toIso8601String(),
                'is_owner' => $membership->user_id === $organization->owner_id,
                'is_you' => $membership->user_id === $user->id,
                'can' => [
                    'update' => $user->can('update', $membership),
                    'delete' => $user->can('delete', $membership),
                ],
            ]);

        return Inertia::render('members/Index', [
            'members' => $members,
            'invitations' => $canInviteFreely
                ? $organization->invitations()
                    ->unanswered()
                    ->with('inviter:id,name')
                    ->latest()
                    ->get()
                    ->map(fn (Invitation $invitation): array => [
                        'id' => $invitation->id,
                        'email' => $invitation->email,
                        'role' => $invitation->role->value,
                        'role_label' => $invitation->role->label(),
                        'department' => $invitation->department,
                        'invited_by' => $invitation->inviter?->name,
                        'sent_at' => $invitation->updated_at?->toIso8601String(),
                        'expires_at' => $invitation->expires_at->toIso8601String(),
                        'is_expired' => $invitation->isExpired(),
                    ])
                : [],
            'roles' => collect(Role::cases())->map(fn (Role $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
                'assignable' => $canInviteAsEmployee ? $role === Role::Employee : ($actorRole?->canAssign($role) ?? false),
            ]),
            'defaultRole' => (string) $organization->setting('members.default_role', Role::Employee->value),
            'can' => [
                'invite' => $canInvite,
                'seeInvitations' => $canInviteFreely,
                'manage' => $user->can(Permission::MembersManage->value),
            ],
        ]);
    }

    public function update(UpdateMemberRequest $request, OrganizationMembership $member, UpdateMember $updateMember): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateMember->handle($member, $request->changes(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->user->name} updated."]);

        return back();
    }

    public function destroy(Request $request, OrganizationMembership $member, RemoveMember $removeMember): RedirectResponse
    {
        Gate::authorize('delete', $member);

        /** @var User $user */
        $user = $request->user();
        $name = $member->user->name;

        $removeMember->handle($member, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$name} removed from the organization."]);

        return back();
    }
}
