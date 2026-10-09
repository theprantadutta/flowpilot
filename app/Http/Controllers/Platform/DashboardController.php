<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PlanChangeRequest;
use App\Support\Platform\PlatformStats;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(PlatformStats $stats): Response
    {
        return Inertia::render('platform/Dashboard', [
            'tiles' => $stats->tiles(),
            'plans' => $stats->plans(),
            'charts' => Inertia::defer(fn (): array => $stats->charts()),
            'requests' => PlanChangeRequest::withoutOrganizationScope()
                ->where('status', PlanChangeRequest::PENDING)
                ->with(['requester:id,name', 'organization:id,name,slug'])
                ->oldest()
                ->limit(10)
                ->get()
                ->map(fn (PlanChangeRequest $request): array => [
                    'id' => $request->id,
                    'organization' => ['name' => $request->organization->name, 'slug' => $request->organization->slug],
                    'from' => $request->from_plan->label(),
                    'to' => $request->to_plan->label(),
                    'requester' => $request->requester?->name,
                    'message' => $request->message,
                    'created_at' => $request->created_at?->toIso8601String(),
                ]),
            'newest' => Organization::query()
                ->with('owner:id,name,email')
                ->latest()
                ->limit(6)
                ->get(['id', 'name', 'slug', 'owner_id', 'created_at'])
                ->map(fn (Organization $organization): array => [
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'owner' => $organization->owner->name,
                    'created_at' => $organization->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
