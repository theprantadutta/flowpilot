<?php

namespace App\Support\Reports\Definitions;

use App\Enums\ApprovalStatus;
use App\Enums\Priority;
use App\Enums\ReportType;
use App\Models\Approval;
use App\Models\User;
use App\Support\Reports\Chart;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportFilter;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportResult;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use Illuminate\Database\Eloquent\Builder;

class ApprovalTurnaroundReport extends Report
{
    private const array OPEN = [ApprovalStatus::Pending->value, ApprovalStatus::ChangesRequested->value];

    private const array DECIDED = [ApprovalStatus::Approved->value, ApprovalStatus::Rejected->value];

    public function type(): ReportType
    {
        return ReportType::ApprovalTurnaround;
    }

    public function filters(): array
    {
        return [
            new ReportFilter('source', 'Raised', 'By people and workflows', [
                ['value' => 'manual', 'label' => 'By people'],
                ['value' => 'workflow', 'label' => 'By workflows'],
            ]),
            $this->memberFilter('requester', 'Requested by', 'Anyone'),
        ];
    }

    public function groups(): array
    {
        return [
            'decider' => 'By approver',
            'requester' => 'By requester',
            'priority' => 'By priority',
        ];
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $dates = SqlDates::for($query);
        $between = $query->between();
        $hours = '('.$dates->secondsBetween('created_at', 'decided_at').') / 3600.0';

        $outcomes = $this->approvals($query)
            ->whereBetween('decided_at', $between)
            ->whereIn('status', [...self::DECIDED, ApprovalStatus::Expired->value])
            ->selectRaw($dates->bucket('decided_at').' as bucket, status, count(*) as total', $dates->bindings())
            ->groupBy('bucket', 'status')
            ->toBase()
            ->get();

        $turnaround = $this->approvals($query)
            ->whereBetween('decided_at', $between)
            ->whereIn('status', self::DECIDED)
            ->selectRaw($dates->bucket('decided_at')." as bucket, avg({$hours}) as hours", $dates->bindings())
            ->groupBy('bucket')
            ->pluck('hours', 'bucket')
            ->map(fn (mixed $value): float => round((float) $value, 1))
            ->all();

        $overall = $this->approvals($query)
            ->whereBetween('decided_at', $between)
            ->whereIn('status', self::DECIDED)
            ->selectRaw("avg({$hours}) as hours")
            ->value('hours');

        $count = fn (string $status): int => (int) $outcomes->where('status', $status)->sum('total');
        $byBucket = fn (string $status): array => $outcomes->where('status', $status)->pluck('total', 'bucket')->map(fn (mixed $total): int => (int) $total)->all();

        $approved = $count(ApprovalStatus::Approved->value);
        $rejected = $count(ApprovalStatus::Rejected->value);
        $rate = $this->percent($approved, $approved + $rejected);
        $waiting = $this->approvals($query)->whereIn('status', self::OPEN)->count();
        $overdue = $this->approvals($query)->where('status', ApprovalStatus::Pending->value)->where('due_at', '<', now())->count();
        $labels = Timeline::labels($query);

        return (new ReportResult)
            ->tile('raised', 'Requests raised', $this->approvals($query)->whereBetween('created_at', $between)->count(), hint: $query->label())
            ->tile('decided', 'Decided', $approved + $rejected, hint: $count(ApprovalStatus::Expired->value) > 0 ? $count(ApprovalStatus::Expired->value).' more expired undecided' : null)
            ->tile('turnaround', 'Average turnaround', $overall !== null ? (float) $overall : null, 'hours', 'From request to decision')
            ->tile('approved', 'Approved', $rate, 'percent', $approved + $rejected > 0 ? "{$approved} of ".($approved + $rejected) : null)
            ->tile('waiting', 'Waiting now', $waiting, hint: $overdue > 0 ? "{$overdue} past their due time" : null, tone: $overdue > 0 ? 'warning' : null)
            ->chart(Chart::columns('decisions', 'Decisions')
                ->describe('Requests decided in each period, by outcome.')
                ->stacked()
                ->labels($labels)
                ->series('approved', 'Approved', 'success', Timeline::fill($query, $byBucket(ApprovalStatus::Approved->value)))
                ->series('rejected', 'Rejected', 'danger', Timeline::fill($query, $byBucket(ApprovalStatus::Rejected->value)))
                ->series('expired', 'Expired', 'neutral', Timeline::fill($query, $byBucket(ApprovalStatus::Expired->value))))
            ->chart(Chart::line('turnaround', 'Average turnaround')
                ->describe('Hours from request to decision, for requests decided in each period.')
                ->format('hours')
                ->labels($labels)
                ->series('turnaround', 'Average turnaround', 'chart-1', Timeline::fill($query, $turnaround, null)));
    }

    public function columns(ReportQuery $query): array
    {
        return [
            ReportColumn::make('name', match ($query->group) {
                'requester' => 'Requester',
                'priority' => 'Priority',
                default => 'Approver',
            }),
            ReportColumn::make('decided', 'Decided', 'number'),
            ReportColumn::make('approved', 'Approved', 'number'),
            ReportColumn::make('rejected', 'Rejected', 'number'),
            ReportColumn::make('rate', 'Approval rate', 'percent'),
            ReportColumn::make('average_hours', 'Average turnaround', 'hours'),
            ReportColumn::make('longest_hours', 'Longest turnaround', 'hours'),
            ReportColumn::make('waiting', 'Waiting now', 'number'),
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $dates = SqlDates::for($query);
        $hours = '('.$dates->secondsBetween('created_at', 'decided_at').') / 3600.0';
        [$decidedColumn, $openColumn] = match ($query->group) {
            'requester' => ['requester_id', 'requester_id'],
            'priority' => ['priority', 'priority'],
            default => ['decided_by', 'approver_id'],
        };

        $decided = [];

        foreach ($this->approvals($query)
            ->whereBetween('decided_at', $query->between())
            ->whereIn('status', self::DECIDED)
            ->select($decidedColumn.' as group_key')
            ->selectRaw('count(*) as decided')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as approved', [ApprovalStatus::Approved->value])
            ->selectRaw("avg({$hours}) as average_hours")
            ->selectRaw("max({$hours}) as longest_hours")
            ->groupBy($decidedColumn)
            ->toBase()
            ->get() as $row) {
            $decided[(string) $row->group_key] = [
                'decided' => (int) $row->decided,
                'approved' => (int) $row->approved,
                'average_hours' => $row->average_hours !== null ? round((float) $row->average_hours, 1) : null,
                'longest_hours' => $row->longest_hours !== null ? round((float) $row->longest_hours, 1) : null,
            ];
        }

        $waiting = [];

        foreach ($this->approvals($query)
            ->whereIn('status', self::OPEN)
            ->select($openColumn.' as group_key')
            ->selectRaw('count(*) as waiting')
            ->groupBy($openColumn)
            ->toBase()
            ->get() as $row) {
            $waiting[(string) $row->group_key] = (int) $row->waiting;
        }

        /** @var list<string> $keys */
        $keys = array_values(array_unique([...array_map('strval', array_keys($decided)), ...array_map('strval', array_keys($waiting))]));
        $names = $this->names($query->group, array_values(array_filter($keys, fn (string $key): bool => $key !== '')));
        $rows = [];

        foreach ($keys as $key) {
            $row = $decided[$key] ?? ['decided' => 0, 'approved' => 0, 'average_hours' => null, 'longest_hours' => null];

            $rows[] = [
                'name' => $key === '' ? ($query->group === 'requester' ? 'A workflow' : 'Anyone in the role') : ($names[$key] ?? 'A former member'),
                'decided' => $row['decided'],
                'approved' => $row['approved'],
                'rejected' => $row['decided'] - $row['approved'],
                'rate' => $this->percent($row['approved'], $row['decided']),
                'average_hours' => $row['average_hours'],
                'longest_hours' => $row['longest_hours'],
                'waiting' => $waiting[$key] ?? 0,
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($b['decided'] * 1000 + $b['waiting']) <=> ($a['decided'] * 1000 + $a['waiting']));

        yield from $rows;
    }

    /**
     * @return Builder<Approval>
     */
    private function approvals(ReportQuery $query): Builder
    {
        return Approval::query()
            ->when($query->filter('source') === 'manual', fn (Builder $approvals) => $approvals->whereNull('workflow_run_id'))
            ->when($query->filter('source') === 'workflow', fn (Builder $approvals) => $approvals->whereNotNull('workflow_run_id'))
            ->when($query->filter('requester'), fn (Builder $approvals, string $requester) => $approvals->where('requester_id', (int) $requester));
    }

    /**
     * Display names by group key. Numeric keys become integers in PHP arrays.
     *
     * @param  list<string>  $keys
     * @return array<array-key, string>
     */
    private function names(?string $group, array $keys): array
    {
        if ($group === 'priority') {
            return collect(Priority::cases())->mapWithKeys(fn (Priority $priority): array => [$priority->value => $priority->label()])->all();
        }

        return User::query()->whereKey(array_map('intval', $keys))->pluck('name', 'id')->all();
    }
}
