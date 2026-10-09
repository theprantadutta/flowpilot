<?php

namespace App\Support\Overview;

use App\Enums\ApprovalStatus;
use App\Enums\IssueSeverity;
use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowRunStatus;
use App\Models\Approval;
use App\Models\InventoryItem;
use App\Models\Issue;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Support\Reports\Chart;
use App\Support\Reports\ReportQuery;
use App\Support\Reports\SqlDates;
use App\Support\Reports\Timeline;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Number;

/**
 * The overview answers "what needs my attention?": things waiting on the
 * member or going wrong, a few headline numbers, their own work, and two
 * weeks of trend. Every part only includes what the member may see.
 */
class OverviewData
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * Problems and waiting work, most urgent first. Empty when all is well.
     *
     * @return list<array{key: string, tone: string, icon: string, title: string, description: string, href: string, action: string}>
     */
    public function attention(User $user): array
    {
        $today = $this->today();
        $items = [];

        if ($user->can(Permission::TasksView->value)) {
            $overdue = Task::query()->overdue($today)->where('assignee_id', $user->id)->count();

            if ($overdue > 0) {
                $items[] = $this->item('overdue_tasks', 'danger', 'calendar-clock',
                    $overdue === 1 ? 'One of your tasks is overdue' : "{$overdue} of your tasks are overdue",
                    'Finish them, or move the due date so the plan stays honest.',
                    route('tasks.index', ['assignee' => 'me', 'due' => 'overdue']), 'Review tasks');
            }
        }

        if ($user->can(Permission::IssuesView->value)) {
            $critical = Issue::query()->open()->where('severity', IssueSeverity::Critical->value)->count();

            if ($critical > 0) {
                $items[] = $this->item('critical_issues', 'danger', 'circle-alert',
                    $critical === 1 ? 'A critical issue is open' : "{$critical} critical issues are open",
                    'Critical issues stop work. Someone should own each one.',
                    route('issues.index', ['severity' => IssueSeverity::Critical->value]), 'Open issues');
            }
        }

        if ($user->can(Permission::WorkflowsView->value)) {
            $failed = WorkflowRun::query()
                ->where('status', WorkflowRunStatus::Failed->value)
                ->where('failed_at', '>=', now()->subDays(7))
                ->count();

            if ($failed > 0) {
                $items[] = $this->item('failed_runs', 'danger', 'git-branch',
                    $failed === 1 ? 'A workflow run failed this week' : "{$failed} workflow runs failed this week",
                    'Each failed run stopped part way. Fix the cause and try it again.',
                    route('workflow-runs.index', ['status' => WorkflowRunStatus::Failed->value]), 'See failed runs');
            }
        }

        if ($user->can(Permission::ApprovalsView->value)) {
            $waiting = Approval::query()->waitingOn($user, $this->tenancy->membershipFor($user));
            $count = (clone $waiting)->count();

            if ($count > 0) {
                $oldest = (clone $waiting)->min('created_at');
                $hours = $oldest !== null ? (int) CarbonImmutable::parse((string) $oldest, 'UTC')->diffInHours(now()) : 0;

                $items[] = $this->item('approvals', 'warning', 'stamp',
                    $count === 1 ? 'A request is waiting for your decision' : "{$count} requests are waiting for your decision",
                    $hours >= 1 ? 'The oldest has waited '.$this->hours($hours).'.' : 'They arrived in the last hour.',
                    route('approvals.index', ['view' => 'waiting']), 'Decide now');
            }
        }

        if ($user->can(Permission::InventoryView->value)) {
            $stock = InventoryItem::query()
                ->where('is_active', true)
                ->selectRaw('sum(case when current_stock <= 0 then 1 else 0 end) as out_of_stock')
                ->selectRaw('sum(case when current_stock > 0 and current_stock <= reorder_point then 1 else 0 end) as low')
                ->toBase()
                ->first();
            $out = (int) ($stock->out_of_stock ?? 0);
            $low = (int) ($stock->low ?? 0);

            if ($out + $low > 0) {
                $items[] = $this->item('stock', $out > 0 ? 'danger' : 'warning', 'boxes',
                    $out > 0
                        ? ($out === 1 ? 'An item is out of stock' : "{$out} items are out of stock")
                        : ($low === 1 ? 'An item is running low' : "{$low} items are running low"),
                    $out > 0 && $low > 0 ? "{$low} more ".($low === 1 ? 'is' : 'are').' at or below the reorder point.' : 'Reorder before work has to wait.',
                    route('inventory.items.index', ['stock' => $out > 0 ? 'out' : 'low']), 'Check stock');
            }
        }

        if ($user->can(Permission::InventoryManage->value)) {
            $submitted = PurchaseRequest::query()->where('status', PurchaseRequestStatus::Submitted->value)->count();

            if ($submitted > 0) {
                $items[] = $this->item('purchases', 'warning', 'shopping-cart',
                    $submitted === 1 ? 'A purchase request is waiting' : "{$submitted} purchase requests are waiting",
                    'They need a decision before anything is ordered.',
                    route('purchase-requests.index', ['status' => PurchaseRequestStatus::Submitted->value]), 'Review requests');
            }
        }

        if ($user->can(Permission::ProjectsView->value)) {
            $late = Project::query()
                ->whereIn('status', array_map(fn (ProjectStatus $status): string => $status->value, ProjectStatus::open()))
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today)
                ->count();

            if ($late > 0) {
                $items[] = $this->item('late_projects', 'warning', 'folder-kanban',
                    $late === 1 ? 'A project is past its due date' : "{$late} projects are past their due date",
                    'Close them out or agree a new date with the team.',
                    route('projects.index'), 'Open projects');
            }
        }

        return $items;
    }

    /**
     * Headline numbers, each linking to the list behind it.
     *
     * @return list<array{key: string, label: string, value: int, hint: string|null, tone: string|null, href: string}>
     */
    public function stats(User $user): array
    {
        $today = $this->today();
        $stats = [];

        if ($user->can(Permission::ProjectsView->value)) {
            $stats[] = $this->stat('projects', 'Active projects', Project::query()->where('status', ProjectStatus::Active->value)->count(), null, null, route('projects.index', ['status' => ProjectStatus::Active->value]));
        }

        if ($user->can(Permission::TasksView->value)) {
            $dueDate = (new SqlDates($this->tenancy->currentOrFail()->timezone))->date('due_date');
            $tasks = Task::query()->open()
                ->selectRaw("sum(case when due_date is not null and {$dueDate} = ? then 1 else 0 end) as due_today", [$today])
                ->selectRaw("sum(case when due_date is not null and {$dueDate} < ? then 1 else 0 end) as overdue", [$today])
                ->toBase()
                ->first();
            $overdue = (int) ($tasks->overdue ?? 0);

            $stats[] = $this->stat('due_today', 'Tasks due today', (int) ($tasks->due_today ?? 0), null, null, route('tasks.index', ['due' => 'today']));
            $stats[] = $this->stat('overdue', 'Overdue tasks', $overdue, null, $overdue > 0 ? 'danger' : 'success', route('tasks.index', ['due' => 'overdue']));
        }

        if ($user->can(Permission::ApprovalsView->value)) {
            $pending = Approval::query()->whereIn('status', [ApprovalStatus::Pending->value, ApprovalStatus::ChangesRequested->value])->count();
            $overdue = Approval::query()->where('status', ApprovalStatus::Pending->value)->where('due_at', '<', now())->count();

            $stats[] = $this->stat('approvals', 'Open approvals', $pending, $overdue > 0 ? "{$overdue} overdue" : null, $overdue > 0 ? 'warning' : null, route('approvals.index', ['view' => 'all']));
        }

        if ($user->can(Permission::IssuesView->value)) {
            $issues = Issue::query()->open()->whereIn('severity', [IssueSeverity::Critical->value, IssueSeverity::High->value])->count();

            $stats[] = $this->stat('issues', 'Serious issues', $issues, 'Critical or high, still open', $issues > 0 ? 'danger' : 'success', route('issues.index'));
        }

        if ($user->can(Permission::WorkflowsView->value)) {
            $runs = WorkflowRun::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed', [WorkflowRunStatus::Completed->value])
                ->selectRaw('sum(case when status = ? then 1 else 0 end) as failed', [WorkflowRunStatus::Failed->value])
                ->toBase()
                ->first();
            $completed = (int) ($runs->completed ?? 0);
            $finished = $completed + (int) ($runs->failed ?? 0);

            $stats[] = $this->stat('runs', 'Runs this week', (int) ($runs->total ?? 0),
                $finished > 0 ? Number::percentage($completed / $finished * 100).' finished successfully' : null,
                null, route('workflow-runs.index'));
        }

        return $stats;
    }

    /**
     * The member's open tasks, soonest due first.
     *
     * @return list<array{id: string, reference: string, title: string, status: array<string, string>, priority: array<string, string>, project: string|null, due_date: string|null, is_overdue: bool, url: string}>
     */
    public function myWork(User $user): array
    {
        if (! $user->can(Permission::TasksView->value)) {
            return [];
        }

        $today = $this->today();

        return array_values(Task::query()
            ->open()
            ->where('assignee_id', $user->id)
            ->with('project:id,organization_id,name')
            ->orderByRaw('case when due_date is null then 1 else 0 end')
            ->orderBy('due_date')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get()
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'reference' => $task->reference(),
                'title' => $task->title,
                'status' => $task->status->toOption(),
                'priority' => $task->priority->toOption(),
                'project' => $task->project?->name,
                'due_date' => $task->due_date?->toDateString(),
                'is_overdue' => $task->isOverdue($today),
                'url' => route('tasks.show', ['task' => $task->id]),
            ])
            ->all());
    }

    /**
     * Two weeks of completed work and automation, as report charts.
     *
     * @return list<array<string, mixed>>
     */
    public function trends(User $user): array
    {
        $query = ReportQuery::make($this->tenancy->currentOrFail()->timezone, 'custom', CarbonImmutable::now($this->tenancy->currentOrFail()->timezone)->subDays(13)->toDateString(), $this->today());
        $dates = SqlDates::for($query);
        $labels = Timeline::labels($query);
        $charts = [];

        if ($user->can(Permission::TasksView->value)) {
            $completed = Task::query()
                ->whereBetween('completed_at', $query->between())
                ->selectRaw($dates->bucket('completed_at').' as bucket, count(*) as total', $dates->bindings())
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->map(fn (mixed $total): int => (int) $total)
                ->all();

            $charts[] = Chart::columns('completed', 'Tasks completed')
                ->describe('Last 14 days')
                ->labels($labels)
                ->series('completed', 'Tasks completed', 'chart-1', Timeline::fill($query, $completed))
                ->toArray();
        }

        if ($user->can(Permission::WorkflowsView->value)) {
            $runs = WorkflowRun::query()
                ->whereBetween('created_at', $query->between())
                ->selectRaw($dates->bucket('created_at').' as bucket, status, count(*) as total', $dates->bindings())
                ->groupBy('bucket', 'status')
                ->toBase()
                ->get();

            $series = fn (array $statuses): array => Timeline::fill($query, $runs->whereIn('status', $statuses)
                ->groupBy('bucket')
                ->map(fn ($rows): int => (int) $rows->sum('total'))
                ->all());

            $charts[] = Chart::columns('runs', 'Workflow runs')
                ->describe('Last 14 days, by outcome')
                ->stacked()
                ->labels($labels)
                ->series('completed', 'Completed', 'success', $series([WorkflowRunStatus::Completed->value]))
                ->series('failed', 'Failed', 'danger', $series([WorkflowRunStatus::Failed->value]))
                ->series('active', 'Still running', 'flow', $series([WorkflowRunStatus::Pending->value, WorkflowRunStatus::Running->value, WorkflowRunStatus::Waiting->value]))
                ->toArray();
        }

        return $charts;
    }

    private function today(): string
    {
        return CarbonImmutable::now($this->tenancy->currentOrFail()->timezone)->toDateString();
    }

    private function hours(int $hours): string
    {
        return $hours < 48 ? $hours.' '.($hours === 1 ? 'hour' : 'hours') : intdiv($hours, 24).' days';
    }

    /**
     * @return array{key: string, tone: string, icon: string, title: string, description: string, href: string, action: string}
     */
    private function item(string $key, string $tone, string $icon, string $title, string $description, string $href, string $action): array
    {
        return compact('key', 'tone', 'icon', 'title', 'description', 'href', 'action');
    }

    /**
     * @return array{key: string, label: string, value: int, hint: string|null, tone: string|null, href: string}
     */
    private function stat(string $key, string $label, int $value, ?string $hint, ?string $tone, string $href): array
    {
        return compact('key', 'label', 'value', 'hint', 'tone', 'href');
    }
}
