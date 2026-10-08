<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Support\Activity\ActivityPresenter;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /**
     * The organization's home screen: what needs attention right now.
     */
    public function __invoke(Tenancy $tenancy, ActivityPresenter $presenter): Response
    {
        Gate::authorize(Permission::DashboardView->value);

        $organization = $tenancy->currentOrFail();

        return Inertia::render('Overview', [
            'team' => [
                'members' => $organization->activeMemberships()->count(),
                'pendingInvitations' => $organization->invitations()->open()->count(),
            ],
            'recentActivity' => Inertia::defer(fn (): array => ActivityLog::query()
                ->with('actor:id,name,avatar_path')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))
                ->all()),
        ]);
    }
}
