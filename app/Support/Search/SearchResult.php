<?php

namespace App\Support\Search;

/**
 * One row in the command palette's search results.
 */
final readonly class SearchResult
{
    public function __construct(
        public string $group,
        public string $id,
        public string $title,
        public ?string $subtitle,
        public string $url,
        public string $icon,
    ) {}

    /**
     * @return array{group: string, id: string, title: string, subtitle: string|null, url: string, icon: string}
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}
