<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Activity\ActivityAreas;
use App\Support\Activity\ActivityPresenter;
use App\Support\FormOptions;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everything that happened in the organization. Members with the audit
 * permission also see where each change came from (IP address, browser) and
 * the exact before/after values.
 */
class ActivityController extends Controller
{
    public function __invoke(Request $request, ActivityPresenter $presenter, FormOptions $options, Tenancy $tenancy): Response
    {
        Gate::authorize(Permission::DashboardView->value);

        /** @var User $user */
        $user = $request->user();
        $canAudit = $user->can(Permission::AuditView->value);
        $timezone = $tenancy->currentOrFail()->timezone;

        $filters = [
            'area' => array_key_exists($request->string('area')->toString(), ActivityAreas::AREAS) ? $request->string('area')->toString() : null,
            'actor' => ctype_digit($request->string('actor')->toString()) ? (int) $request->string('actor')->toString() : null,
            'from' => $this->date($request->string('from')->toString()),
            'to' => $this->date($request->string('to')->toString()),
        ];

        $entries = ActivityLog::query()
            ->with('actor:id,name,avatar_path')
            ->when($filters['area'], fn (Builder $query, string $area) => $query->where('action', 'like', $area.'.%'))
            ->when($filters['actor'], fn (Builder $query, int $actor) => $query->where('actor_id', $actor))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from, $timezone)->startOfDay()->utc()))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to, $timezone)->endOfDay()->utc()))
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => [
                ...$presenter->present($log),
                'audit' => $canAudit ? [
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                ] : null,
            ]);

        return Inertia::render('activity/Index', [
            'entries' => $entries,
            'filters' => $filters,
            'areas' => collect(ActivityAreas::AREAS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'members' => fn () => $options->members(),
            'canAudit' => $canAudit,
        ]);
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? $value : null;
    }
}
