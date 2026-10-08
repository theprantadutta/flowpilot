<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\Project;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;

class ProjectSearchSource implements SearchSource
{
    public function permission(): Permission
    {
        return Permission::ProjectsView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        return array_values(Contains::any(Project::query(), ['name', 'description'], $term)
            ->orderByRaw("case status when 'active' then 0 when 'planning' then 1 when 'on_hold' then 2 else 3 end")
            ->limit($limit)
            ->get()
            ->map(fn (Project $project): SearchResult => new SearchResult(
                group: 'Projects',
                id: $project->id,
                title: $project->name,
                subtitle: $project->status->label(),
                url: route('projects.show', ['organization' => $organization->slug, 'project' => $project->id]),
                icon: 'folder',
            ))
            ->all());
    }
}
