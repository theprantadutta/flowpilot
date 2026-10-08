<?php

namespace App\Support\Search\Sources;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\Search\Contains;
use App\Support\Search\SearchResult;
use App\Support\Search\SearchSource;
use Illuminate\Database\Eloquent\Builder;

class MemberSearchSource implements SearchSource
{
    public function permission(): Permission
    {
        return Permission::MembersView;
    }

    public function search(Organization $organization, string $term, int $limit): array
    {
        return array_values($organization->memberships()
            ->with('user:id,name,email')
            ->whereHas('user', fn (Builder $query) => Contains::any($query, ['name', 'email'], $term))
            ->limit($limit)
            ->get()
            ->map(fn (OrganizationMembership $membership): SearchResult => new SearchResult(
                group: 'Members',
                id: $membership->id,
                title: $membership->user->name,
                subtitle: "{$membership->role->label()} · {$membership->user->email}",
                url: route('members.index', ['organization' => $organization->slug]),
                icon: 'user',
            ))
            ->all());
    }
}
