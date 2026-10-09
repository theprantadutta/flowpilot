<?php

namespace App\Support\Reports\Definitions;

use App\Enums\NodeType;
use App\Enums\ReportType;
use App\Enums\StepRunStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Support\Reports\Chart;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportResult;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowFailuresReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::WorkflowFailures;
    }

    public function filters(): array
    {
        return [$this->workflowFilter()];
    }

    public function groups(): array
    {
        return [
            'run' => 'Each failed run',
            'workflow' => 'By workflow',
            'step' => 'By failing step',
        ];
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $dates = SqlDates::for($query);
        $between = $query->between();

        $perBucket = $this->failed($query)
            ->selectRaw($dates->bucket('failed_at').' as bucket, count(*) as total', $dates->bindings())
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $failed = array_sum($perBucket);
        $completed = WorkflowRun::query()
            ->when($query->filter('workflow'), fn (Builder $runs, string $workflow) => $runs->where('workflow_id', $workflow))
            ->where('status', WorkflowRunStatus::Completed->value)
            ->whereBetween('completed_at', $between)
            ->count();
        $rate = $this->percent($failed, $failed + $completed);

        $commonStep = $this->failedSteps($query)
            ->selectRaw('node_type, count(*) as total')
            ->groupBy('node_type')
            ->orderByDesc('total')
            ->toBase()
            ->first();

        return (new ReportResult)
            ->tile('failed', 'Failed runs', $failed, hint: $query->label(), tone: $failed > 0 ? 'danger' : 'success')
            ->tile('rate', 'Failure rate', $rate, 'percent', 'Of runs that finished',
                tone: $rate === null ? null : ($rate <= 5 ? 'success' : ($rate <= 20 ? 'warning' : 'danger')))
            ->tile('workflows', 'Workflows affected', $this->failed($query)->distinct()->count('workflow_id'))
            ->tile('step', 'Fails most at',
                $commonStep !== null ? (NodeType::tryFrom((string) $commonStep->node_type)?->label() ?? 'A step').' steps' : null,
                'text',
                $commonStep !== null ? (int) $commonStep->total.' failed steps' : 'No failed steps')
            ->chart(Chart::columns('failures', 'Failed runs')
                ->describe('Runs that stopped with an error in each period.')
                ->labels(Timeline::labels($query))
                ->series('failed', 'Failed runs', 'danger', Timeline::fill($query, $perBucket)));
    }

    public function columns(ReportQuery $query): array
    {
        return match ($query->group) {
            'workflow' => [
                ReportColumn::make('name', 'Workflow', 'link'),
                ReportColumn::make('failed', 'Failed runs', 'number'),
                ReportColumn::make('last_error', 'Most recent error'),
                ReportColumn::make('last_failed', 'Last failed', 'datetime'),
            ],
            'step' => [
                ReportColumn::make('name', 'Step type'),
                ReportColumn::make('failed', 'Failed steps', 'number'),
                ReportColumn::make('last_error', 'Most recent error'),
                ReportColumn::make('last_failed', 'Last failed', 'datetime'),
            ],
            default => [
                ReportColumn::make('run', 'Run', 'link'),
                ReportColumn::make('workflow', 'Workflow'),
                ReportColumn::make('step', 'Failed at step'),
                ReportColumn::make('error', 'Error'),
                ReportColumn::make('subject', 'About'),
                ReportColumn::make('failed_at', 'Failed', 'datetime'),
            ],
        };
    }

    public function rows(ReportQuery $query): iterable
    {
        if ($query->group === 'workflow' || $query->group === 'step') {
            yield from $this->grouped($query);

            return;
        }

        $runs = $this->failed($query)
            ->with([
                'workflow:id,organization_id,name',
                'steps' => fn (Relation $steps) => $steps->where('status', StepRunStatus::Failed->value)->select(['id', 'organization_id', 'workflow_run_id', 'label', 'node_type', 'error']),
            ])
            ->latest('failed_at')
            ->lazy(200);

        foreach ($runs as $run) {
            /** @var WorkflowStepRun|null $step */
            $step = $run->steps->first();

            yield [
                'run' => ['label' => $run->reference(), 'url' => $this->url('workflow-runs.show', ['run' => $run->id])],
                'workflow' => $run->workflow->name ?? 'A deleted workflow',
                'step' => $step?->label,
                'error' => Str::limit((string) ($step->error ?? $run->error ?? ''), 300),
                'subject' => $run->subject_label,
                'failed_at' => $run->failed_at?->toIso8601String(),
            ];
        }
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function grouped(ReportQuery $query): iterable
    {
        if ($query->group === 'workflow') {
            $groups = $this->failed($query)
                ->selectRaw('workflow_id, count(*) as failed, max(failed_at) as last_failed')
                ->groupBy('workflow_id')
                ->orderByDesc('failed')
                ->toBase()
                ->get();
            $names = Workflow::query()->whereKey($groups->pluck('workflow_id')->all())->pluck('name', 'id')->all();
            $errors = $this->latest($this->failed($query), 'workflow_id', 'failed_at');

            foreach ($groups as $row) {
                $latest = $errors[(string) $row->workflow_id] ?? null;

                yield [
                    'name' => ['label' => $names[$row->workflow_id] ?? 'A deleted workflow', 'url' => isset($names[$row->workflow_id]) ? $this->url('workflows.show', ['workflow' => $row->workflow_id]) : null],
                    'failed' => (int) $row->failed,
                    'last_error' => Str::limit((string) $latest, 300),
                    'last_failed' => $this->iso($row->last_failed),
                ];
            }

            return;
        }

        $groups = $this->failedSteps($query)
            ->selectRaw('node_type, count(*) as failed, max(updated_at) as last_failed')
            ->groupBy('node_type')
            ->orderByDesc('failed')
            ->toBase()
            ->get();

        $errors = $this->latest($this->failedSteps($query), 'node_type', 'updated_at');

        foreach ($groups as $row) {
            $latest = $errors[(string) $row->node_type] ?? null;

            yield [
                'name' => NodeType::tryFrom((string) $row->node_type)?->label() ?? (string) $row->node_type,
                'failed' => (int) $row->failed,
                'last_error' => Str::limit((string) $latest, 300),
                'last_failed' => $this->iso($row->last_failed),
            ];
        }
    }

    /**
     * @return Builder<WorkflowRun>
     */
    private function failed(ReportQuery $query): Builder
    {
        return WorkflowRun::query()
            ->when($query->filter('workflow'), fn (Builder $runs, string $workflow) => $runs->where('workflow_id', $workflow))
            ->where('status', WorkflowRunStatus::Failed->value)
            ->whereBetween('failed_at', $query->between());
    }

    /**
     * Failed steps of runs that failed in the range.
     *
     * @return Builder<WorkflowStepRun>
     */
    private function failedSteps(ReportQuery $query): Builder
    {
        return WorkflowStepRun::query()
            ->where('status', StepRunStatus::Failed->value)
            ->whereIn('workflow_run_id', $this->failed($query)->select('id'));
    }

    /**
     * The most recent error per group, in one query.
     *
     * @param  Builder<WorkflowRun>|Builder<WorkflowStepRun>  $records
     * @param  literal-string  $groupColumn
     * @param  literal-string  $timeColumn
     * @return array<string, string|null>
     */
    private function latest(Builder $records, string $groupColumn, string $timeColumn): array
    {
        $ranked = $records->select([$groupColumn, 'error'])
            ->selectRaw("row_number() over (partition by {$groupColumn} order by {$timeColumn} desc) as position");

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('position', 1)
            ->pluck('error', $groupColumn)
            ->mapWithKeys(fn (mixed $error, mixed $key): array => [(string) $key => is_string($error) ? $error : null])
            ->all();
    }

    private function iso(mixed $timestamp): ?string
    {
        return $timestamp !== null ? CarbonImmutable::parse((string) $timestamp, 'UTC')->toIso8601String() : null;
    }
}
