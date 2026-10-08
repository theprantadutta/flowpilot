<?php

namespace App\Support\Search;

use App\Enums\Permission;
use App\Models\Organization;

/**
 * Something global search can look through, such as members or projects.
 *
 * Sources run inside the current organization, so tenant-owned models are
 * already scoped; a source only has to match the term.
 */
interface SearchSource
{
    /**
     * The permission a member needs before this source is searched at all.
     */
    public function permission(): Permission;

    /**
     * @return list<SearchResult>
     */
    public function search(Organization $organization, string $term, int $limit): array;
}
