<?php

namespace App\Support\Ai\Brief;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JsonException;

/**
 * Turns the model's answer into a brief, or refuses it. The shape is checked
 * field by field, text is cut to length and stripped of markup, and every
 * cited fact must be one that was given, so links can only ever point at
 * records the member was allowed to see.
 *
 * @phpstan-import-type Fact from BriefFacts
 *
 * @phpstan-type BriefContent array{headline: string, items: list<array{title: string, detail: string, severity: string, facts: list<string>}>, actions: list<array{label: string, fact: string}>}
 */
class BriefParser
{
    /**
     * @param  list<Fact>  $facts
     * @return BriefContent
     *
     * @throws InvalidBrief
     */
    public function parse(string $answer, array $facts): array
    {
        $data = $this->decode($answer);
        $known = array_column($facts, 'id');

        $validator = Validator::make($data, [
            'headline' => ['required', 'string', 'max:300'],
            'items' => ['present', 'array', 'max:8'],
            'items.*.title' => ['required', 'string', 'max:300'],
            'items.*.detail' => ['required', 'string', 'max:1000'],
            'items.*.severity' => ['required', 'in:high,medium,low'],
            'items.*.facts' => ['required', 'array', 'min:1'],
            'items.*.facts.*' => ['string'],
            'actions' => ['sometimes', 'array', 'max:8'],
            'actions.*.label' => ['required', 'string', 'max:120'],
            'actions.*.fact' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidBrief('The answer did not have the expected shape: '.$validator->errors()->first());
        }

        /** @var array{headline: string, items: list<array{title: string, detail: string, severity: string, facts: list<string>}>, actions?: list<array{label: string, fact: string}>} $data */
        $items = [];

        foreach ($data['items'] as $item) {
            $cited = array_values(array_unique(array_intersect($item['facts'], $known)));

            if ($cited === []) {
                continue;
            }

            $items[] = [
                'title' => $this->clean($item['title'], 140),
                'detail' => $this->clean($item['detail'], 400),
                'severity' => $item['severity'],
                'facts' => $cited,
            ];
        }

        if ($items === [] && $facts !== []) {
            throw new InvalidBrief('The answer did not point at any of the facts it was given.');
        }

        $actions = [];

        foreach ($data['actions'] ?? [] as $action) {
            if (in_array($action['fact'], $known, true) && count($actions) < 4) {
                $actions[] = ['label' => $this->clean($action['label'], 60), 'fact' => $action['fact']];
            }
        }

        return [
            'headline' => $this->clean($data['headline'], 160),
            'items' => array_slice($items, 0, 5),
            'actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $answer): array
    {
        $text = trim($answer);

        // Models sometimes wrap JSON in a code fence or add a sentence around it.
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new InvalidBrief('The answer was not JSON.');
        }

        try {
            $data = json_decode(substr($text, $start, $end - $start + 1), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidBrief('The answer was not valid JSON.');
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidBrief('The answer was not a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function clean(string $text, int $limit): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags(str_replace(['**', '__', '`'], '', $text))));

        return Str::limit($plain, $limit);
    }
}
