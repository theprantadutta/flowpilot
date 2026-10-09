<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Support\Activity\ActivityAreas;
use App\Support\Activity\ActivityPresenter;
use App\Support\Search\Contains;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The activity log across every organization, newest first, with where
 * each change came from.
 */
class AuditController extends Controller
{
    public function __invoke(Request $request, ActivityPresenter $presenter): Response
    {
        $area = array_key_exists($request->string('area')->toString(), ActivityAreas::AREAS) ? $request->string('area')->toString() : null;
        $organization = $request->string('organization')->trim()->limit(80, '')->toString();
        $actorType = in_array($request->query('actor'), ['user', 'workflow', 'system', 'ai', 'platform'], true) ? (string) $request->query('actor') : null;

        $entries = ActivityLog::withoutOrganizationScope()
            ->with(['actor:id,name,avatar_path', 'organization:id,name,slug'])
            ->when($organization !== '', fn (Builder $query) => $query->whereIn(
                'organization_id',
                Contains::any(Organization::query(), ['name', 'slug'], $organization)->select('id'),
            ))
            ->when($area, fn (Builder $query, string $area) => $query->where('action', 'like', $area.'.%'))
            ->when($actorType, fn (Builder $query, string $type) => $query->where('actor_type', $type))
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => [
                ...$presenter->present($log),
                'organization' => $log->organization !== null ? ['name' => $log->organization->name, 'slug' => $log->organization->slug] : null,
                // Members see "FlowPilot support"; the team sees who it was.
                'staff' => $log->actor_type === 'platform' ? $log->actor?->name : null,
                'ip_address' => $log->ip_address,
            ]);

        return Inertia::render('platform/Audit', [
            'entries' => $entries,
            'filters' => ['organization' => $organization, 'area' => $area, 'actor' => $actorType],
            'areas' => collect(ActivityAreas::AREAS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
        ]);
    }
}
