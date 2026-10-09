<?php

namespace App\Support\Reports;

use App\Enums\ReportType;
use App\Models\Project;
use App\Models\Workflow;
use App\Support\FormOptions;
use App\Support\Tenancy\Tenancy;

/**
 * One report: its filters and groupings, its headline figures and charts,
 * and the rows of its table (which are also the rows of its export).
 */
abstract class Report
{
    abstract public function type(): ReportType;

    /**
     * @return list<ReportFilter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Ways the table can be grouped, key => label. Empty for a plain list.
     *
     * @return array<string, string>
     */
    public function groups(): array
    {
        return [];
    }

    /**
     * Whether the figures depend on the date range. Snapshot reports still
     * use it for the parts that happened over time.
     */
    public function usesRange(): bool
    {
        return true;
    }

    abstract public function summarize(ReportQuery $query): ReportResult;

    /**
     * @return list<ReportColumn>
     */
    abstract public function columns(ReportQuery $query): array;

    /**
     * Table rows keyed by column, streamed so an export of any size stays
     * within memory.
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function rows(ReportQuery $query): iterable;

    public function defaultGroup(): ?string
    {
        return array_key_first($this->groups());
    }

    protected function memberFilter(string $key, string $label, string $anyLabel): ReportFilter
    {
        return new ReportFilter($key, $label, $anyLabel, array_map(
            fn (array $member): array => ['value' => (string) $member['id'], 'label' => $member['name']],
            app(FormOptions::class)->members(),
        ));
    }

    /**
     * Every project, closed ones included: reports look back.
     */
    protected function projectFilter(): ReportFilter
    {
        return new ReportFilter('project', 'Project', 'All projects', array_values(Project::query()
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name'])
            ->map(fn (Project $project): array => ['value' => $project->id, 'label' => $project->name])
            ->all()));
    }

    protected function workflowFilter(): ReportFilter
    {
        return new ReportFilter('workflow', 'Workflow', 'All workflows', array_values(Workflow::query()
            ->orderBy('name')
            ->get(['id', 'organization_id', 'name'])
            ->map(fn (Workflow $workflow): array => ['value' => $workflow->id, 'label' => $workflow->name])
            ->all()));
    }

    /**
     * A link inside the current organization. Built explicitly, because
     * exports run outside a request.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function url(string $name, array $parameters): string
    {
        return route($name, ['organization' => app(Tenancy::class)->currentOrFail()->slug, ...$parameters]);
    }

    /**
     * Share as a whole percentage, or null when there is nothing to share.
     */
    protected function percent(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
