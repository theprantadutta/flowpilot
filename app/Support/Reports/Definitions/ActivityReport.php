<?php

namespace App\Support\Reports\Definitions;

use App\Enums\ReportType;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Activity\ActivityAreas;
use App\Support\Reports\Chart;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportFilter;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportResult;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

class ActivityReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::Activity;
    }

    public function filters(): array
    {
        return [
            $this->memberFilter('member', 'Member', 'Everyone'),
            new ReportFilter('area', 'Area', 'All areas', array_map(
                fn (string $key, array $group): array => ['value' => $key, 'label' => $group['label']],
                array_keys(ActivityAreas::GROUPS),
                ActivityAreas::GROUPS,
            )),
        ];
    }

    public function groups(): array
    {
        return [
            'area' => 'By area',
            'member' => 'By member',
        ];
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $dates = SqlDates::for($query);
        $area = $this->area();

        $events = $this->entries($query)
            ->selectRaw($dates->bucket('created_at')." as bucket, {$area} as area, count(*) as total", $dates->bindings())
            ->groupBy('bucket', 'area')
            ->toBase()
            ->get();

        $people = $this->entries($query)->where('actor_type', 'user')->distinct()->count('actor_id');
        $automated = $this->entries($query)->whereIn('actor_type', ['workflow', 'system', 'ai'])->count();

        $perBucket = $events->groupBy('bucket')->map(fn ($rows): int => (int) $rows->sum('total'));
        $busiest = $perBucket->sortDesc()->keys()->first();

        $chart = Chart::columns('activity', 'Changes by area')
            ->describe('Everything recorded in the activity log, by the part of FlowPilot it happened in.')
            ->stacked()
            ->labels(Timeline::labels($query));

        $slot = 1;

        foreach (ActivityAreas::GROUPS as $key => $group) {
            $chart->series($key, $group['label'], 'chart-'.$slot++, Timeline::fill(
                $query,
                $events->where('area', $key)->pluck('total', 'bucket')->map(fn (mixed $total): int => (int) $total)->all(),
            ));
        }

        return (new ReportResult)
            ->tile('events', 'Changes recorded', (int) $events->sum('total'), hint: $query->label())
            ->tile('people', 'Active members', $people, hint: 'Made at least one change')
            ->tile('automated', 'Made by automation', $automated, hint: 'Workflows and FlowPilot itself')
            ->tile('busiest', 'Busiest '.($query->bucket->value), $busiest !== null ? $this->bucketLabel($query, (string) $busiest) : null, 'text',
                $busiest !== null ? $perBucket->get($busiest).' changes' : 'Nothing recorded')
            ->chart($chart);
    }

    public function columns(ReportQuery $query): array
    {
        if ($query->group === 'member') {
            return [
                ReportColumn::make('name', 'Member'),
                ReportColumn::make('events', 'Changes', 'number'),
                ReportColumn::make('areas', 'Areas'),
                ReportColumn::make('last', 'Most recent', 'datetime'),
            ];
        }

        return [
            ReportColumn::make('name', 'Area'),
            ReportColumn::make('events', 'Changes', 'number'),
            ReportColumn::make('people', 'Members', 'number'),
            ReportColumn::make('automated', 'By automation', 'number'),
            ReportColumn::make('last', 'Most recent', 'datetime'),
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $area = $this->area();

        if ($query->group === 'member') {
            $rows = $this->entries($query)
                ->where('actor_type', 'user')
                ->whereNotNull('actor_id')
                ->select('actor_id')
                ->selectRaw('count(*) as events, max(created_at) as last')
                ->groupBy('actor_id')
                ->orderByDesc('events')
                ->toBase()
                ->get();

            $names = User::query()->whereKey($rows->pluck('actor_id')->all())->pluck('name', 'id');
            $areas = $this->entries($query)
                ->where('actor_type', 'user')
                ->whereIn('actor_id', $rows->pluck('actor_id')->all())
                ->selectRaw("actor_id, {$area} as area")
                ->distinct()
                ->toBase()
                ->get()
                ->groupBy('actor_id');

            foreach ($rows as $row) {
                yield [
                    'name' => $names[$row->actor_id] ?? 'A former member',
                    'events' => (int) $row->events,
                    'areas' => $areas->get($row->actor_id, collect())
                        ->map(fn (stdClass $entry): string => ActivityAreas::GROUPS[$entry->area]['label'] ?? 'Other')
                        ->sort()
                        ->implode(', '),
                    'last' => $this->iso($row->last),
                ];
            }

            return;
        }

        $rows = $this->entries($query)
            ->selectRaw("{$area} as area, count(*) as events")
            ->selectRaw("count(distinct case when actor_type = 'user' then actor_id end) as people")
            ->selectRaw("sum(case when actor_type in ('workflow', 'system', 'ai') then 1 else 0 end) as automated")
            ->selectRaw('max(created_at) as last')
            ->groupBy('area')
            ->toBase()
            ->get()
            ->keyBy('area');

        foreach (ActivityAreas::GROUPS as $key => $group) {
            $row = $rows->get($key);

            if ($row === null) {
                continue;
            }

            yield [
                'name' => $group['label'],
                'events' => (int) $row->events,
                'people' => (int) $row->people,
                'automated' => (int) $row->automated,
                'last' => $this->iso($row->last),
            ];
        }
    }

    /**
     * @return Builder<ActivityLog>
     */
    private function entries(ReportQuery $query): Builder
    {
        $area = $query->filter('area');
        $prefixes = $area !== null ? (ActivityAreas::GROUPS[$area]['prefixes'] ?? []) : [];

        return ActivityLog::query()
            ->whereBetween('created_at', $query->between())
            ->when($query->filter('member'), fn (Builder $entries, string $member) => $entries->where('actor_id', (int) $member))
            ->when($area !== null, fn (Builder $entries) => $area === 'team'
                ? $entries->whereRaw($this->area()." = 'team'")
                : $entries->where(function (Builder $entries) use ($prefixes): void {
                    foreach ($prefixes as $prefix) {
                        $entries->orWhere('action', 'like', $prefix.'.%');
                    }
                }));
    }

    /**
     * @return literal-string
     */
    private function area(): string
    {
        return ActivityAreas::groupExpression('action');
    }

    private function bucketLabel(ReportQuery $query, string $key): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $key, $query->timezone);

        return match ($query->bucket->value) {
            'month' => $date?->format('F Y'),
            'week' => 'Week of '.$date?->format('M j'),
            default => $date?->format('D, M j'),
        } ?? $key;
    }

    private function iso(mixed $timestamp): ?string
    {
        return $timestamp !== null ? CarbonImmutable::parse((string) $timestamp, 'UTC')->toIso8601String() : null;
    }
}
