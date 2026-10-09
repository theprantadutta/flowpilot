<?php

namespace App\Support\Marketing;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * A legal page (terms of service, privacy policy) written in Markdown under
 * resources/legal, with the operator's details filled in from configuration.
 */
final readonly class LegalDocument
{
    private const array DOCUMENTS = [
        'terms' => ['title' => 'Terms of service', 'updated' => '2026-10-09'],
        'privacy' => ['title' => 'Privacy policy', 'updated' => '2026-10-09'],
    ];

    private function __construct(
        public string $key,
        public string $title,
        public string $updated,
    ) {}

    public static function find(string $key): self
    {
        $document = self::DOCUMENTS[$key] ?? throw new \InvalidArgumentException("There is no legal document called [{$key}].");

        return new self($key, $document['title'], $document['updated']);
    }

    /**
     * The document as HTML, with a heading id for each section, and the
     * sections for a table of contents. The Markdown is ours, and any HTML
     * in it is stripped, so the result is safe to render as is.
     *
     * @return array{html: string, sections: list<array{id: string, title: string}>}
     */
    public function render(): array
    {
        $markdown = strtr(File::get(resource_path("legal/{$this->key}.md")), $this->replacements());
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $sections = [];

        $html = (string) preg_replace_callback('#<h2>(.+?)</h2>#', function (array $match) use (&$sections): string {
            $title = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5);
            $id = Str::slug($title);
            $sections[] = ['id' => $id, 'title' => $title];

            return "<h2 id=\"{$id}\">{$match[1]}</h2>";
        }, $html);

        return ['html' => $html, 'sections' => $sections];
    }

    /**
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $jurisdiction = config('flowpilot.legal.jurisdiction');
        $entity = (string) config('flowpilot.legal.entity');

        return [
            ':app_name' => (string) config('app.name', 'FlowPilot'),
            ':entity' => $entity,
            ':contact_email' => (string) config('flowpilot.legal.contact_email'),
            ':trial_days' => (string) (int) config('billing.trial_days', 14),
            ':ai_keep_days' => (string) (int) config('ai.brief.keep_days', 30),
            ':export_keep_days' => (string) (int) config('flowpilot.exports.keep_days', 7),
            ':jurisdiction_clause' => is_string($jurisdiction) && $jurisdiction !== ''
                ? "These terms are governed by the laws of {$jurisdiction}, and its courts settle any dispute about them."
                : "These terms are governed by the laws of the place where {$entity} is established.",
        ];
    }
}
