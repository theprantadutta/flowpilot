<?php

namespace App\Support\Reports\Definitions;

use App\Enums\Priority;
use App\Enums\ReportType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Reports\Chart;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportResult;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

class TaskCompletionReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::TaskCompletion;
    }

    public function filters(): array
    {
        return [
            $this->projectFilter(),
            $this->memberFilter('assignee', 'Assignee', 'Anyone'),
        ];
    }

    public function groups(): array
    {
        return [
            'assignee' => 'By assignee',
            'project' => 'By project',
            'priority' => 'By priority',
        ];
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $dates = SqlDates::for($query);
        $between = $query->between();

        $created = $this->tasks($query)
            ->whereBetween('created_at', $between)
            ->selectRaw($dates->bucket('created_at').' as bucket, count(*) as total', $dates->bindings())
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $completed = $this->tasks($query)
            ->whereBetween('completed_at', $between)
            ->selectRaw($dates->bucket('completed_at').' as bucket, count(*) as total', $dates->bindings())
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $finished = $this->tasks($query)
            ->whereBetween('completed_at', $between)
            ->selectRaw('count(due_date) as with_due')
            ->selectRaw("sum(case when due_date is not null and {$dates->localDate('completed_at')} <= {$dates->date('due_date')} then 1 else 0 end) as on_time", $dates->bindings())
            ->selectRaw("avg({$dates->secondsBetween('created_at', 'completed_at')}) as average_seconds")
            ->toBase()
            ->first();

        $withDue = (int) ($finished->with_due ?? 0);
        $onTime = $this->percent((int) ($finished->on_time ?? 0), $withDue);
        $averageDays = isset($finished->average_seconds) ? (float) $finished->average_seconds / 86400 : null;
        $overdue = $this->tasks($query)->overdue(CarbonImmutable::now($query->timezone)->toDateString())->count();

        return (new ReportResult)
            ->tile('created', 'Tasks created', array_sum($created), hint: $query->label())
            ->tile('completed', 'Tasks completed', array_sum($completed), hint: $query->label())
            ->tile('on_time', 'Finished on time', $onTime, 'percent',
                hint: $withDue > 0 ? "Of {$withDue} with a due date" : 'No finished task had a due date',
                tone: $onTime === null ? null : ($onTime >= 80 ? 'success' : ($onTime >= 50 ? 'warning' : 'danger')))
            ->tile('cycle', 'Average time to finish', $averageDays, 'days', 'From creation to done')
            ->tile('overdue', 'Overdue now', $overdue, tone: $overdue > 0 ? 'danger' : 'success')
            ->chart(Chart::line('flow', 'Created and completed')
                ->describe('When completed keeps up with created, the backlog is not growing.')
                ->labels(Timeline::labels($query))
                ->series('created', 'Created', 'chart-1', Timeline::fill($query, $created))
                ->series('completed', 'Completed', 'chart-2', Timeline::fill($query, $completed)));
    }

    public function columns(ReportQuery $query): array
    {
        return [
            ReportColumn::make('name', match ($query->group) {
                'project' => 'Project',
                'priority' => 'Priority',
                default => 'Assignee',
            }),
            ReportColumn::make('created', 'Created', 'number'),
            ReportColumn::make('completed', 'Completed', 'number'),
            ReportColumn::make('on_time', 'On time', 'percent'),
            ReportColumn::make('average_days', 'Average days to finish', 'days'),
            ReportColumn::make('open', 'Open now', 'number'),
            ReportColumn::make('overdue', 'Overdue now', 'number'),
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $dates = SqlDates::for($query);
        [$from, $to] = $query->between();
        $today = CarbonImmutable::now($query->timezone)->toDateString();
        $column = match ($query->group) {
            'project' => 'project_id',
            'priority' => 'priority',
            default => 'assignee_id',
        };
        $completedInRange = 'completed_at between ? and ?';

        $groups = $this->tasks($query)
            ->select($column.' as group_key')
            ->selectRaw('sum(case when created_at between ? and ? then 1 else 0 end) as created', [$from, $to])
            ->selectRaw("sum(case when {$completedInRange} then 1 else 0 end) as completed", [$from, $to])
            ->selectRaw("sum(case when {$completedInRange} and due_date is not null then 1 else 0 end) as with_due", [$from, $to])
            ->selectRaw("sum(case when {$completedInRange} and due_date is not null and {$dates->localDate('completed_at')} <= {$dates->date('due_date')} then 1 else 0 end) as on_time", [...[$from, $to], ...$dates->bindings()])
            ->selectRaw("avg(case when {$completedInRange} then {$dates->secondsBetween('created_at', 'completed_at')} end) as average_seconds", [$from, $to])
            ->selectRaw('sum(case when status <> ? then 1 else 0 end) as open_now', [TaskStatus::Done->value])
            ->selectRaw("sum(case when status <> ? and due_date is not null and {$dates->date('due_date')} < ? then 1 else 0 end) as overdue_now", [TaskStatus::Done->value, $today])
            ->groupBy($column)
            ->toBase()
            ->get()
            ->filter(fn (stdClass $row): bool => (int) $row->created + (int) $row->completed + (int) $row->open_now > 0)
            ->sortByDesc(fn (stdClass $row): int => (int) $row->completed * 100000 + (int) $row->created);

        $names = $this->names($column, $groups->pluck('group_key')->filter()->all());

        foreach ($groups as $row) {
            $withDue = (int) $row->with_due;

            yield [
                'name' => $row->group_key === null ? ($column === 'project_id' ? 'No project' : 'Unassigned') : ($names[(string) $row->group_key] ?? 'A former member'),
                'created' => (int) $row->created,
                'completed' => (int) $row->completed,
                'on_time' => $this->percent((int) $row->on_time, $withDue),
                'average_days' => $row->average_seconds !== null ? round((float) $row->average_seconds / 86400, 1) : null,
                'open' => (int) $row->open_now,
                'overdue' => (int) $row->overdue_now,
            ];
        }
    }

    /**
     * @return Builder<Task>
     */
    private function tasks(ReportQuery $query): Builder
    {
        return Task::query()
            ->when($query->filter('project'), fn (Builder $tasks, string $project) => $tasks->where('project_id', $project))
            ->when($query->filter('assignee'), fn (Builder $tasks, string $assignee) => $tasks->where('assignee_id', (int) $assignee));
    }

    /**
     * Display names for the grouped ids.
     *
     * @param  array<int, mixed>  $keys
     * @return array<string, string>
     */
    private function names(string $column, array $keys): array
    {
        return match ($column) {
            'project_id' => Project::query()->whereKey($keys)->pluck('name', 'id')->all(),
            'priority' => collect(Priority::cases())->mapWithKeys(fn (Priority $priority): array => [$priority->value => $priority->label()])->all(),
            default => User::query()->whereKey($keys)->pluck('name', 'id')->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])->all(),
        };
    }
}
