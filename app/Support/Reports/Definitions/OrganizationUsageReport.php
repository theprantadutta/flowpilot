<?php

namespace App\Support\Reports\Definitions;

use App\Enums\MembershipStatus;
use App\Enums\ReportType;
use App\Models\ActivityLog;
use App\Models\Approval;
use App\Models\Attachment;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Issue;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Task;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Support\Reports\Chart;
use App\Support\Reports\Report;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\ReportResult;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use App\Support\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationUsageReport extends Report
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function type(): ReportType
    {
        return ReportType::OrganizationUsage;
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $dates = SqlDates::for($query);
        $between = $query->between();
        $organization = $this->tenancy->currentOrFail();

        $activeMembers = ActivityLog::query()
            ->whereBetween('created_at', $between)
            ->where('actor_type', 'user')
            ->selectRaw($dates->bucket('created_at').' as bucket, count(distinct actor_id) as people', $dates->bindings())
            ->groupBy('bucket')
            ->pluck('people', 'bucket')
            ->map(fn (mixed $people): int => (int) $people)
            ->all();

        $runs = WorkflowRun::query()
            ->whereBetween('created_at', $between)
            ->selectRaw($dates->bucket('created_at').' as bucket, count(*) as total', $dates->bindings())
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $records = 0;

        foreach ([Project::class, Task::class, Issue::class, Approval::class, PurchaseRequest::class] as $model) {
            $records += $model::query()->whereBetween('created_at', $between)->count();
        }

        $labels = Timeline::labels($query);

        return (new ReportResult)
            ->tile('members', 'Members', $organization->memberships()->where('status', MembershipStatus::Active)->count())
            ->tile('people', 'Active members', ActivityLog::query()->whereBetween('created_at', $between)->where('actor_type', 'user')->distinct()->count('actor_id'), hint: $query->label())
            ->tile('records', 'Records created', $records, hint: 'Projects, tasks, issues, approvals and purchases')
            ->tile('runs', 'Workflow runs', array_sum($runs), hint: $query->label())
            ->tile('storage', 'File storage', (int) Attachment::query()->sum('size'), 'bytes', 'Across all attachments')
            ->chart(Chart::line('people', 'Members making changes')
                ->describe('How many people changed something in each period.')
                ->labels($labels)
                ->series('people', 'Members', 'chart-1', Timeline::fill($query, $activeMembers)))
            ->chart(Chart::columns('runs', 'Workflow runs')
                ->describe('Runs started in each period, by any trigger.')
                ->labels($labels)
                ->series('runs', 'Workflow runs', 'chart-1', Timeline::fill($query, $runs)));
    }

    public function columns(ReportQuery $query): array
    {
        return [
            ReportColumn::make('metric', 'What'),
            ReportColumn::make('in_period', 'Added in period', 'number'),
            ReportColumn::make('total', 'Total now', 'number'),
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $between = $query->between();
        $organization = $this->tenancy->currentOrFail();

        $members = $organization->memberships()
            ->where('status', MembershipStatus::Active)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when joined_at between ? and ? then 1 else 0 end) as in_period', $between)
            ->toBase()
            ->first();

        yield ['metric' => 'Members', 'in_period' => (int) ($members->in_period ?? 0), 'total' => (int) ($members->total ?? 0)];

        $counted = [
            'Projects' => [Project::query(), 'created_at'],
            'Tasks' => [Task::query(), 'created_at'],
            'Issues' => [Issue::query(), 'created_at'],
            'Approval requests' => [Approval::query(), 'created_at'],
            'Purchase requests' => [PurchaseRequest::query(), 'created_at'],
            'Workflows' => [Workflow::query(), 'created_at'],
            'Workflow runs' => [WorkflowRun::query(), 'created_at'],
            'Stock items' => [InventoryItem::query(), 'created_at'],
            'Stock movements' => [InventoryMovement::query(), 'occurred_at'],
            'Files' => [Attachment::query(), 'created_at'],
        ];

        foreach ($counted as $metric => [$records, $column]) {
            yield ['metric' => $metric, ...$this->counts($records, $column, $between)];
        }
    }

    /**
     * @param  Builder<covariant Model>  $records
     * @param  literal-string  $column
     * @param  array{0: mixed, 1: mixed}  $between
     * @return array{in_period: int, total: int}
     */
    private function counts(Builder $records, string $column, array $between): array
    {
        $row = $records
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when {$column} between ? and ? then 1 else 0 end) as in_period", $between)
            ->toBase()
            ->first();

        return ['in_period' => (int) ($row->in_period ?? 0), 'total' => (int) ($row->total ?? 0)];
    }
}
