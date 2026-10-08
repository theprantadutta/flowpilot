<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /**
     * The organization's home screen: what needs attention right now.
     */
    public function __invoke(Tenancy $tenancy): Response
    {
        Gate::authorize(Permission::DashboardView->value);

        $organization = $tenancy->currentOrFail();

        return Inertia::render('Overview', [
            'team' => [
                'members' => $organization->activeMemberships()->count(),
                'pendingInvitations' => $organization->invitations()->open()->count(),
            ],
        ]);
    }
}
