<?php

namespace App\Http\Controllers;

use App\Actions\Members\AcceptInvitation;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of an invitation: the page the emailed link opens, and the
 * action that accepts it. The token in the URL is the only credential, so the
 * routes are rate limited and reveal nothing for an unknown token.
 */
class InvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findByToken($token);
        $user = $request->user();

        if ($user === null) {
            // Come back here after signing in or registering.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('invitations/Show', [
            'token' => $token,
            'invitation' => [
                'organization' => $invitation->organization->name,
                'email' => $invitation->email,
                'role_label' => $invitation->role->label(),
                'role_description' => $invitation->role->description(),
                'invited_by' => $invitation->inviter?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'state' => match (true) {
                    $invitation->accepted_at !== null => 'accepted',
                    $invitation->revoked_at !== null => 'revoked',
                    $invitation->isExpired() => 'expired',
                    default => 'open',
                },
            ],
            'viewer' => $user instanceof User ? [
                'email' => $user->email,
                'matches' => Str::lower($user->email) === Str::lower($invitation->email),
            ] : null,
        ]);
    }

    public function store(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->findByToken($token);

        /** @var User $user */
        $user = $request->user();

        $acceptInvitation->handle($invitation, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "You joined {$invitation->organization->name}."]);

        return to_route('overview', ['organization' => $invitation->organization->slug]);
    }

    private function findByToken(string $token): Invitation
    {
        return Invitation::query()
            ->with(['organization', 'inviter:id,name'])
            ->where('token_hash', Invitation::hashToken($token))
            ->firstOrFail();
    }
}
