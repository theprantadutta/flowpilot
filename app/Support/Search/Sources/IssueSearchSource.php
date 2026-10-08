<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\Issue;
use App\Models\Organization;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;
use Illuminate\Database\Eloquent\Builder;

class IssueSearchSource implements SearchSource
{
    public function permission(): Permission
    {
        return Permission::IssuesView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        $number = ltrim(strtoupper(trim($term)), 'I-');

        return array_values(Issue::query()
            ->where(fn (Builder $query) => ctype_digit($number)
                ? $query->where('number', (int) $number)->orWhere(fn (Builder $q) => Contains::any($q, ['title'], $term))
                : Contains::any($query, ['title'], $term))
            ->orderByRaw("case when status in ('open', 'investigating') then 0 else 1 end")
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Issue $issue): SearchResult => new SearchResult(
                group: 'Issues',
                id: $issue->id,
                title: "{$issue->reference()} {$issue->title}",
                subtitle: "{$issue->severity->label()} · {$issue->status->label()}",
                url: route('issues.show', ['organization' => $organization->slug, 'issue' => $issue->id]),
                icon: 'alert-triangle',
            ))
            ->all());
    }
}
