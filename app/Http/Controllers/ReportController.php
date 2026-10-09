<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\Permission;
use App\Enums\Plan;
use App\Enums\ReportBucket;
use App\Enums\ReportType;
use App\Http\Resources\ReportExportResource;
use App\Models\ReportExport;
use App\Models\User;
use App\Support\Billing\Entitlements;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportFilter;
use App\Support\Reports\ReportParameters;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Rows shown on the page; exports have them all.
     */
    private const int TABLE_ROWS = 50;

    public function __construct(private readonly ReportRegistry $reports) {}

    public function index(Request $request, Entitlements $entitlements): Response
    {
        Gate::authorize(Permission::ReportsView->value);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('reports/Index', [
            'reports' => array_map(fn (ReportType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'group' => $type->group(),
                'locked' => ! $this->reports->isIncluded($type),
                'plan' => $this->reports->isIncluded($type) ? null : Plan::lowestWith(Feature::AllReports)?->label(),
            ], $this->reports->available($user)),
            'exports' => fn () => ReportExportResource::collection($this->recentExports($user)->limit(8)->get()),
            'can' => ['export' => $user->can(Permission::ReportsExport->value) && $entitlements->allows(Feature::ReportExports)],
        ]);
    }

    public function show(Request $request, ReportType $report, ReportParameters $parameters, Entitlements $entitlements): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->reports->permits($user, $report), 403);

        if (! $this->reports->isIncluded($report)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "{$report->label()} comes with the ".Plan::lowestWith(Feature::AllReports)?->label().' plan and above.']);

            return to_route('reports.index');
        }

        $definition = $this->reports->get($report);
        $query = $parameters->query($definition, $request->query());

        return Inertia::render('reports/Show', [
            'report' => [
                'value' => $report->value,
                'label' => $report->label(),
                'description' => $report->description(),
                'icon' => $report->icon(),
                'uses_range' => $definition->usesRange(),
            ],
            'parameters' => [
                'range' => $query->range,
                'from' => $query->from->toDateString(),
                'to' => $query->to->toDateString(),
                'bucket' => $query->bucket->value,
                'group' => $query->group,
                'filters' => (object) $query->filters,
                'label' => $query->label(),
            ],
            'options' => fn () => [
                'ranges' => collect(ReportQuery::PRESETS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
                'buckets' => ReportBucket::options(),
                'groups' => collect($definition->groups())->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
                'filters' => array_map(fn (ReportFilter $filter): array => $filter->toArray(), $definition->filters()),
            ],
            'result' => Inertia::defer(fn (): array => $definition->summarize($query)->toArray()),
            'table' => Inertia::defer(fn (): array => $this->table($definition, $query)),
            'exports' => fn () => ReportExportResource::collection($this->recentExports($user)->where('report', $report->value)->limit(5)->get()),
            'can' => [
                'export' => $this->reports->canExport($user, $report),
                // Allowed by role, but the plan does not include exports.
                'exportLocked' => $user->can(Permission::ReportsExport->value) && ! $entitlements->allows(Feature::ReportExports),
            ],
        ]);
    }

    /**
     * @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, truncated: bool, limit: int}
     */
    private function table(Report $report, ReportQuery $query): array
    {
        $rows = [];

        // One more than shown, to know whether there is more to export.
        foreach ($report->rows($query) as $row) {
            $rows[] = $row;

            if (count($rows) > self::TABLE_ROWS) {
                break;
            }
        }

        return [
            'columns' => array_map(fn (ReportColumn $column): array => $column->toArray(), $report->columns($query)),
            'rows' => array_slice($rows, 0, self::TABLE_ROWS),
            'truncated' => count($rows) > self::TABLE_ROWS,
            'limit' => self::TABLE_ROWS,
        ];
    }

    /**
     * @return Builder<ReportExport>
     */
    private function recentExports(User $user): Builder
    {
        return ReportExport::query()->where('user_id', $user->id)->latest();
    }
}
