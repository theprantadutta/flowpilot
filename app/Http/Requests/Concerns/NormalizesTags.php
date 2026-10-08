<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;

/**
 * Tags are short lowercase labels: trimmed, de-duplicated, at most ten.
 */
trait NormalizesTags
{
    /**
     * @return array<string, list<string>>
     */
    protected function tagRules(): array
    {
        return [
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30', 'regex:/^[\pL\pN][\pL\pN \-_.&\/]*$/u'],
        ];
    }

    protected function normalizeTags(): void
    {
        if (! $this->has('tags')) {
            return;
        }

        $tags = $this->input('tags');

        if (! is_array($tags)) {
            return;
        }

        $this->merge([
            'tags' => array_values(array_unique(array_filter(array_map(
                fn (mixed $tag): string => is_string($tag) ? Str::of($tag)->squish()->lower()->toString() : '',
                $tags,
            )))),
        ]);
    }
}
