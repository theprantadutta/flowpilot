<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\Task;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;
use Illuminate\Database\Eloquent\Builder;

class TaskSearchSource implements SearchSource
{
    public function permission(): Permission
    {
        return Permission::TasksView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        $number = ltrim(strtoupper(trim($term)), 'T-');

        return array_values(Task::query()
            ->with('project:id,organization_id,name')
            ->where(fn (Builder $query) => ctype_digit($number)
                ? $query->where('number', (int) $number)->orWhere(fn (Builder $q) => Contains::any($q, ['title'], $term))
                : Contains::any($query, ['title'], $term))
            ->orderByRaw("case when status = 'done' then 1 else 0 end")
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Task $task): SearchResult => new SearchResult(
                group: 'Tasks',
                id: $task->id,
                title: "{$task->reference()} {$task->title}",
                subtitle: $task->status->label().($task->project ? " · {$task->project->name}" : ''),
                url: route('tasks.show', ['organization' => $organization->slug, 'task' => $task->id]),
                icon: 'check-square',
            ))
            ->all());
    }
}
