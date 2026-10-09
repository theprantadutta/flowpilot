<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Resources\AiBriefResource;
use App\Models\ActivityLog;
use App\Models\AiBrief;
use App\Models\User;
use App\Support\Activity\ActivityPresenter;
use App\Support\Ai\AiOperationsService;
use App\Support\Overview\OverviewData;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /**
     * The organization's home screen: what needs attention right now.
     */
    public function __invoke(Request $request, Tenancy $tenancy, ActivityPresenter $presenter, OverviewData $overview, AiOperationsService $ai): Response
    {
        Gate::authorize(Permission::DashboardView->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $tenancy->currentOrFail();
        $usesAi = $ai->isEnabled() && $user->can(Permission::AiUse->value);

        return Inertia::render('Overview', [
            'team' => [
                'members' => $organization->activeMemberships()->count(),
                'pendingInvitations' => $organization->invitations()->open()->count(),
            ],
            'stats' => $overview->stats($user),
            'ai' => ['enabled' => $usesAi],
            'brief' => fn () => $usesAi ? $this->latestBrief($user, $request) : null,
            'attention' => Inertia::defer(fn (): array => $overview->attention($user)),
            'myWork' => Inertia::defer(fn (): array => $overview->myWork($user)),
            'trends' => Inertia::defer(fn (): array => $overview->trends($user), 'trends'),
            'recentActivity' => Inertia::defer(fn (): array => ActivityLog::query()
                ->with('actor:id,name,avatar_path')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (ActivityLog $log): array => $presenter->present($log))
                ->all(), 'trends'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestBrief(User $user, Request $request): ?array
    {
        $brief = AiBrief::query()->where('user_id', $user->id)->latest()->first();

        return $brief !== null ? (new AiBriefResource($brief))->resolve($request) : null;
    }
}
