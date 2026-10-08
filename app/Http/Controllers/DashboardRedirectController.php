<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    /**
     * Send a signed-in user to the organization they last worked in, or to
     * onboarding when they do not belong to one yet.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $memberships = $user->usableMemberships();

        if ($memberships->isEmpty()) {
            return to_route('onboarding.show');
        }

        $membership = $memberships->first(
            fn (OrganizationMembership $membership): bool => $membership->organization_id === $user->last_organization_id,
        ) ?? $memberships->first();

        return to_route('overview', ['organization' => $membership->organization->slug]);
    }
}
