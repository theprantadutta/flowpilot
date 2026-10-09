<?php

namespace App\Support\Marketing;

/**
 * What search engines and link previews read about a public page. Rendered
 * into the HTML by the server, so it is there without running JavaScript.
 *
 * Pages without page meta are not indexed (see resources/views/app.blade.php).
 */
final readonly class PageMeta
{
    /**
     * @param  list<array<string, mixed>>  $schema  JSON-LD documents describing the page.
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $url,
        public array $schema = [],
    ) {}

    /**
     * @return array{title: string, description: string, url: string, image: string, image_alt: string, site_name: string, schema: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $name = (string) config('app.name', 'FlowPilot');

        return [
            'title' => "{$this->title} - {$name}",
            'description' => $this->description,
            'url' => $this->url,
            'image' => asset('brand/og-card.png'),
            'image_alt' => "{$name}: Move work forward. Automatically.",
            'site_name' => $name,
            'schema' => $this->schema,
        ];
    }
}
