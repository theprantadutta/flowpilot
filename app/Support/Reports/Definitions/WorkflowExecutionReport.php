<?php

namespace App\Support\Reports\Definitions;

use App\Enums\ReportType;
use App\Enums\WorkflowRunStatus;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Support\Reports\Chart;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportResult;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use App\Workflows\Triggers\TriggerRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class WorkflowExecutionReport extends Report
{
    public function __construct(private readonly TriggerRegistry $triggers) {}

    public function type(): ReportType
    {
        return ReportType::WorkflowExecution;
    }

    public function filters(): array
    {
        return [$this->workflowFilter()];
    }

    public function groups(): array
    {
        return [
            'workflow' => 'By workflow',
            'trigger' => 'By trigger',
        ];
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $dates = SqlDates::for($query);
        $between = $query->between();

        $runs = $this->runs($query)
            ->whereBetween('created_at', $between)
            ->selectRaw($dates->bucket('created_at').' as bucket, status, count(*) as total', $dates->bindings())
            ->groupBy('bucket', 'status')
            ->toBase()
            ->get();

        $series = function (array $statuses) use ($runs): array {
            return $runs->whereIn('status', $statuses)
                ->groupBy('bucket')
                ->map(fn ($rows): int => (int) $rows->sum('total'))
                ->all();
        };

        $inProgress = [WorkflowRunStatus::Pending->value, WorkflowRunStatus::Running->value, WorkflowRunStatus::Waiting->value];
        $completed = (int) $runs->where('status', WorkflowRunStatus::Completed->value)->sum('total');
        $failed = (int) $runs->where('status', WorkflowRunStatus::Failed->value)->sum('total');
        $cancelled = (int) $runs->where('status', WorkflowRunStatus::Cancelled->value)->sum('total');
        $finished = $completed + $failed;
        $successRate = $this->percent($completed, $finished);

        $duration = $this->runs($query)
            ->whereBetween('created_at', $between)
            ->where('status', WorkflowRunStatus::Completed->value)
            ->whereNotNull('started_at')
            ->selectRaw('avg('.$dates->secondsBetween('started_at', 'completed_at').') as seconds')
            ->value('seconds');

        $active = $this->runs($query)->whereIn('status', $inProgress)->count();
        $labels = Timeline::labels($query);

        return (new ReportResult)
            ->tile('runs', 'Runs started', (int) $runs->sum('total'), hint: $query->label())
            ->tile('success', 'Finished successfully', $successRate, 'percent',
                hint: $finished > 0 ? "{$completed} of {$finished} finished runs" : 'No run has finished yet',
                tone: $successRate === null ? null : ($successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger')))
            ->tile('failed', 'Failed', $failed, hint: $cancelled > 0 ? "{$cancelled} cancelled" : null, tone: $failed > 0 ? 'danger' : 'success')
            ->tile('duration', 'Average duration', $duration !== null ? (float) $duration : null, 'duration', 'Of completed runs, waits included')
            ->tile('active', 'Running or waiting now', $active)
            ->chart(Chart::columns('runs', 'Runs by outcome')
                ->describe('Runs started in each period and how they ended.')
                ->stacked()
                ->labels($labels)
                ->series('completed', 'Completed', 'success', Timeline::fill($query, $series([WorkflowRunStatus::Completed->value])))
                ->series('failed', 'Failed', 'danger', Timeline::fill($query, $series([WorkflowRunStatus::Failed->value])))
                ->series('cancelled', 'Cancelled', 'neutral', Timeline::fill($query, $series([WorkflowRunStatus::Cancelled->value])))
                ->series('in_progress', 'Still running', 'flow', Timeline::fill($query, $series($inProgress))));
    }

    public function columns(ReportQuery $query): array
    {
        return [
            ReportColumn::make('name', $query->group === 'trigger' ? 'Trigger' : 'Workflow', $query->group === 'trigger' ? 'text' : 'link'),
            ReportColumn::make('runs', 'Runs', 'number'),
            ReportColumn::make('completed', 'Completed', 'number'),
            ReportColumn::make('failed', 'Failed', 'number'),
            ReportColumn::make('cancelled', 'Cancelled', 'number'),
            ReportColumn::make('success_rate', 'Success rate', 'percent'),
            ReportColumn::make('average_duration', 'Average duration', 'duration'),
            ReportColumn::make('last_run', 'Last run', 'datetime'),
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $dates = SqlDates::for($query);
        $column = $query->group === 'trigger' ? 'trigger_type' : 'workflow_id';

        $groups = $this->runs($query)
            ->whereBetween('created_at', $query->between())
            ->select($column.' as group_key')
            ->selectRaw('count(*) as runs')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed', [WorkflowRunStatus::Completed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [WorkflowRunStatus::Failed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as cancelled', [WorkflowRunStatus::Cancelled->value])
            ->selectRaw('avg(case when status = ? and started_at is not null then '.$dates->secondsBetween('started_at', 'completed_at').' end) as average_seconds', [WorkflowRunStatus::Completed->value])
            ->selectRaw('max(created_at) as last_run')
            ->groupBy($column)
            ->orderByDesc('runs')
            ->toBase()
            ->get();

        $workflows = $column === 'workflow_id'
            ? Workflow::query()->whereKey($groups->pluck('group_key')->all())->pluck('name', 'id')->all()
            : [];
        $triggers = collect($this->triggers->options())->pluck('label', 'value')->all();

        foreach ($groups as $row) {
            $key = (string) $row->group_key;
            $completed = (int) $row->completed;
            $failed = (int) $row->failed;

            yield [
                'name' => $column === 'workflow_id'
                    ? ['label' => $workflows[$key] ?? 'A deleted workflow', 'url' => isset($workflows[$key]) ? $this->url('workflows.show', ['workflow' => $key]) : null]
                    : ($triggers[$key] ?? $key),
                'runs' => (int) $row->runs,
                'completed' => $completed,
                'failed' => $failed,
                'cancelled' => (int) $row->cancelled,
                'success_rate' => $this->percent($completed, $completed + $failed),
                'average_duration' => $row->average_seconds !== null ? (int) round((float) $row->average_seconds) : null,
                'last_run' => $row->last_run !== null ? CarbonImmutable::parse((string) $row->last_run, 'UTC')->toIso8601String() : null,
            ];
        }
    }

    /**
     * @return Builder<WorkflowRun>
     */
    private function runs(ReportQuery $query): Builder
    {
        return WorkflowRun::query()
            ->when($query->filter('workflow'), fn (Builder $runs, string $workflow) => $runs->where('workflow_id', $workflow));
    }
}
