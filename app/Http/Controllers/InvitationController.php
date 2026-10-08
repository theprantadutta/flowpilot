<?php

namespace App\Http\Controllers;

use App\Actions\Members\InviteMember;
use App\Actions\Members\ResendInvitation;
use App\Actions\Members\RevokeInvitation;
use App\Enums\Permission;
use App\Http\Requests\Members\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, Tenancy $tenancy, InviteMember $inviteMember): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $invitation = $inviteMember->handle(
            $tenancy->currentOrFail(),
            $user,
            $request->validated('email'),
            $request->role(),
            $request->validated('department'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invitation sent to {$invitation->email}."]);

        return back();
    }

    /**
     * Send the invitation again with a fresh link.
     */
    public function update(Request $request, Invitation $invitation, ResendInvitation $resendInvitation): RedirectResponse
    {
        Gate::authorize(Permission::MembersInvite->value);

        /** @var User $user */
        $user = $request->user();

        $resendInvitation->handle($invitation, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invitation re-sent to {$invitation->email}."]);

        return back();
    }

    public function destroy(Request $request, Invitation $invitation, RevokeInvitation $revokeInvitation): RedirectResponse
    {
        Gate::authorize(Permission::MembersInvite->value);

        /** @var User $user */
        $user = $request->user();

        $revokeInvitation->handle($invitation, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Invitation to {$invitation->email} revoked."]);

        return back();
    }
}
