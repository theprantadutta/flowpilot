<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\Workflow;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;
use App\Workflows\Triggers\TriggerRegistry;

class WorkflowSearchSource implements SearchSource
{
    public function __construct(private readonly TriggerRegistry $triggers) {}

    public function permission(): Permission
    {
        return Permission::WorkflowsView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        return array_values(Workflow::query()
            ->where(fn ($query) => Contains::any($query, ['name', 'description'], $term))
            ->orderByRaw("case status when 'active' then 0 when 'draft' then 1 when 'paused' then 2 else 3 end")
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'organization_id', 'name', 'status', 'trigger_type'])
            ->map(fn (Workflow $workflow): SearchResult => new SearchResult(
                group: 'Workflows',
                id: $workflow->id,
                title: $workflow->name,
                subtitle: "{$workflow->status->label()} · ".($this->triggers->find($workflow->trigger_type)?->label() ?? $workflow->trigger_type),
                url: route('workflows.show', ['organization' => $organization->slug, 'workflow' => $workflow->id]),
                icon: 'git-branch',
            ))
            ->all());
    }
}
