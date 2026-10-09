<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\Approval;
use App\Models\Organization;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;
use Illuminate\Database\Eloquent\Builder;

class ApprovalSearchSource implements SearchSource
{
    public function permission(): Permission
    {
        return Permission::ApprovalsView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        $number = ltrim(strtoupper(trim($term)), 'A-');

        return array_values(Approval::query()
            ->where(fn (Builder $query) => ctype_digit($number)
                ? $query->where('number', (int) $number)->orWhere(fn (Builder $q) => Contains::any($q, ['title'], $term))
                : Contains::any($query, ['title'], $term))
            ->orderByRaw("case when status in ('pending', 'changes_requested') then 0 else 1 end")
            ->orderByDesc('number')
            ->limit($limit)
            ->get()
            ->map(fn (Approval $approval): SearchResult => new SearchResult(
                group: 'Approvals',
                id: $approval->id,
                title: "{$approval->reference()} {$approval->title}",
                subtitle: $approval->status->label(),
                url: route('approvals.show', ['organization' => $organization->slug, 'approval' => $approval->id]),
                icon: 'stamp',
            ))
            ->all());
    }
}
