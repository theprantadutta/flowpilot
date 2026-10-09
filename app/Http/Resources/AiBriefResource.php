<?php

namespace App\Http\Resources;

use App\Models\AiBrief;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A brief ready to show. Links come from the facts the brief was written
 * from, never from the model's text.
 *
 * @mixin AiBrief
 */
class AiBriefResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $facts = $this->facts ?? [];
        $content = $this->content;
        $link = fn (string $id): ?array => isset($facts[$id]) ? ['label' => $facts[$id]['label'], 'url' => $facts[$id]['url']] : null;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'headline' => $content['headline'] ?? null,
            'items' => array_map(fn (array $item): array => [
                'title' => $item['title'],
                'detail' => $item['detail'],
                'severity' => $item['severity'],
                'links' => array_values(array_filter(array_map($link, $item['facts']))),
            ], $content['items'] ?? []),
            'actions' => array_values(array_filter(array_map(fn (array $action): ?array => ($target = $link($action['fact'])) !== null && $target['url'] !== null
                ? ['label' => $action['label'], 'url' => $target['url']]
                : null, $content['actions'] ?? []))),
            'used_fallback' => $this->used_fallback,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
