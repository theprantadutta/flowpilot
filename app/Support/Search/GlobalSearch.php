<?php

namespace App\Support\Search;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Support\Search\Sources\IssueSearchSource;
use App\Support\Search\Sources\MemberSearchSource;
use App\Support\Search\Sources\ProjectSearchSource;
use App\Support\Search\Sources\TaskSearchSource;

/**
 * Searches every source the member is allowed to see.
 */
class GlobalSearch
{
    /**
     * @var list<class-string<SearchSource>>
     */
    public const array SOURCES = [
        ProjectSearchSource::class,
        TaskSearchSource::class,
        IssueSearchSource::class,
        MemberSearchSource::class,
    ];

    public const int MIN_TERM_LENGTH = 2;

    public const int PER_SOURCE = 5;

    /**
     * @return list<SearchResult>
     */
    public function search(Organization $organization, OrganizationMembership $membership, string $term): array
    {
        $term = $this->normalise($term);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        $results = [];

        foreach (self::SOURCES as $sourceClass) {
            $source = app($sourceClass);

            if (! $membership->allows($source->permission())) {
                continue;
            }

            array_push($results, ...$source->search($organization, $term, self::PER_SOURCE));
        }

        return $results;
    }

    /**
     * Trim, collapse whitespace and cap the length. Sources match the term
     * literally through Contains, which escapes wildcards.
     */
    private function normalise(string $term): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $term)), 0, 80);
    }
}
