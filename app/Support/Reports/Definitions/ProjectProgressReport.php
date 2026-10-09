<?php

namespace App\Support\Reports\Definitions;

use App\Enums\ProjectStatus;
use App\Enums\ReportType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
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

class ProjectProgressReport extends Report
{
    public function type(): ReportType
    {
        return ReportType::ProjectProgress;
    }

    public function filters(): array
    {
        return [
            new ReportFilter('status', 'Status', 'Open projects', array_map(
                fn (ProjectStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                ProjectStatus::cases(),
            )),
            $this->memberFilter('owner', 'Owner', 'Any owner'),
        ];
    }

    public function summarize(ReportQuery $query): ReportResult
    {
        $dates = SqlDates::for($query);
        $today = CarbonImmutable::now($query->timezone)->toDateString();

        $projects = $this->projects($query)
            ->withProgress()
            ->get(['id', 'organization_id', 'name', 'status', 'due_date']);

        $open = $projects->filter(fn (Project $project): bool => in_array($project->status, ProjectStatus::open(), true));
        $late = $open->filter(fn (Project $project): bool => $project->due_date !== null && $project->due_date->toDateString() < $today);

        $completed = $this->completedTasks($query)
            ->selectRaw($dates->bucket('completed_at').' as bucket, count(*) as total', $dates->bindings())
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $chartable = $open
            ->sortBy([fn (Project $a, Project $b): int => $b->progress() <=> $a->progress(), fn (Project $a, Project $b): int => strcmp($a->name, $b->name)])
            ->take(12)
            ->values();

        return (new ReportResult)
            ->tile('active', 'Active projects', $open->where('status', ProjectStatus::Active)->count())
            ->tile('progress', 'Average progress', $open->isEmpty() ? null : round((float) $open->avg(fn (Project $project): int => $project->progress()), 1), 'percent', 'Across open projects')
            ->tile('late', 'Past their due date', $late->count(), hint: $late->isEmpty() ? 'Everything is on schedule' : 'Still open after the due date', tone: $late->isEmpty() ? 'success' : 'danger')
            ->tile('completed', 'Tasks completed', array_sum($completed), hint: $query->label())
            ->chart(Chart::bars('progress', 'Progress by project')
                ->describe('Share of each open project\'s tasks that are done.')
                ->format('percent')
                ->labels($chartable->map(fn (Project $project): string => $project->name)->values()->all())
                ->series('progress', 'Progress', 'chart-1', $chartable->map(fn (Project $project): int => $project->progress())->values()->all()))
            ->chart(Chart::columns('completed', 'Tasks completed')
                ->describe('Tasks finished in these projects over the period.')
                ->labels(Timeline::labels($query))
                ->series('completed', 'Tasks completed', 'chart-1', Timeline::fill($query, $completed)));
    }

    public function columns(ReportQuery $query): array
    {
        return [
            ReportColumn::make('project', 'Project', 'link'),
            ReportColumn::make('status', 'Status', 'status'),
            ReportColumn::make('owner', 'Owner'),
            ReportColumn::make('progress', 'Progress', 'percent'),
            ReportColumn::make('tasks_done', 'Tasks done', 'number'),
            ReportColumn::make('tasks_total', 'Tasks', 'number'),
            ReportColumn::make('completed_in_period', 'Completed in period', 'number'),
            ReportColumn::make('overdue_tasks', 'Overdue tasks', 'number'),
            ReportColumn::make('open_issues', 'Open issues', 'number'),
            ReportColumn::make('due_date', 'Due', 'date'),
            ReportColumn::make('budget', 'Budget', 'money'),
        ];
    }

    public function rows(ReportQuery $query): iterable
    {
        $today = CarbonImmutable::now($query->timezone)->toDateString();
        $between = $query->between();

        $projects = $this->projects($query)
            ->withProgress()
            ->withCount([
                'tasks as completed_in_period_count' => fn (Builder $tasks) => $tasks->whereBetween('completed_at', $between),
                // Task::scopeOverdue, spelled out because the count runs on a plain builder.
                'tasks as overdue_tasks_count' => fn (Builder $tasks) => $tasks
                    ->where('status', '!=', TaskStatus::Done->value)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $today),
            ])
            ->with('owner:id,name')
            ->orderByRaw("case status when 'active' then 0 when 'planning' then 1 when 'on_hold' then 2 when 'completed' then 3 else 4 end")
            ->orderBy('due_date')
            ->orderBy('name')
            ->lazy(200);

        foreach ($projects as $project) {
            yield [
                'project' => ['label' => $project->name, 'url' => $this->url('projects.show', ['project' => $project->id])],
                'status' => $project->status->toOption(),
                'owner' => $project->owner?->name,
                'progress' => $project->progress(),
                'tasks_done' => (int) $project->getAttribute('done_tasks_count'),
                'tasks_total' => (int) $project->getAttribute('tasks_count'),
                'completed_in_period' => (int) $project->getAttribute('completed_in_period_count'),
                'overdue_tasks' => (int) $project->getAttribute('overdue_tasks_count'),
                'open_issues' => (int) $project->getAttribute('open_issues_count'),
                'due_date' => $project->due_date?->toDateString(),
                'budget' => $project->budget_amount !== null && $project->budget_currency !== null
                    ? ['amount' => $project->budget_amount, 'currency' => $project->budget_currency]
                    : null,
            ];
        }
    }

    /**
     * @return Builder<Project>
     */
    private function projects(ReportQuery $query): Builder
    {
        $status = ProjectStatus::tryFrom((string) $query->filter('status'));

        return Project::query()
            ->when(
                $status,
                fn (Builder $projects, ProjectStatus $status) => $projects->where('status', $status->value),
                fn (Builder $projects) => $projects->whereIn('status', array_map(fn (ProjectStatus $status): string => $status->value, ProjectStatus::open())),
            )
            ->when($query->filter('owner'), fn (Builder $projects, string $owner) => $projects->where('owner_id', (int) $owner));
    }

    /**
     * @return Builder<Task>
     */
    private function completedTasks(ReportQuery $query): Builder
    {
        return Task::query()
            ->whereBetween('completed_at', $query->between())
            ->whereIn('project_id', $this->projects($query)->select('id'));
    }
}
