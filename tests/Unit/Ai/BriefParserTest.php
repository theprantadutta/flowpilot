<?php

use App\Support\Ai\Brief\BriefParser;
use App\Support\Ai\Brief\InvalidBrief;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return list<array{id: string, area: string, priority: int, label: string, url: string|null, data: array<string, mixed>}>
 */
function briefFacts(): array
{
    return [
        ['id' => 'task:T-4', 'area' => 'tasks', 'priority' => 1, 'label' => 'T-4 Calibrate sensors', 'url' => 'https://app.test/t/4', 'data' => ['days_late' => 2]],
        ['id' => 'item:GLV-100', 'area' => 'inventory', 'priority' => 2, 'label' => 'GLV-100 Gloves', 'url' => 'https://app.test/i/1', 'data' => []],
    ];
}

it('reads a brief wrapped in a code fence and keeps only known facts', function () {
    $answer = <<<'TEXT'
        Here is the brief:
        ```json
        {"headline": "2 items need your attention.",
         "items": [
           {"title": "**Calibrate** the sensors", "detail": "T-4 is <b>2 days</b> late.", "severity": "high", "facts": ["task:T-4", "task:T-999"]},
           {"title": "Made up", "detail": "Not in the facts.", "severity": "low", "facts": ["project:nope"]}
         ],
         "actions": [{"label": "Open T-4", "fact": "task:T-4"}, {"label": "Delete everything", "fact": "admin:all"}]}
        ```
        TEXT;

    $brief = (new BriefParser)->parse($answer, briefFacts());

    expect($brief['headline'])->toBe('2 items need your attention.')
        ->and($brief['items'])->toHaveCount(1)
        ->and($brief['items'][0])->toBe(['title' => 'Calibrate the sensors', 'detail' => 'T-4 is 2 days late.', 'severity' => 'high', 'facts' => ['task:T-4']])
        ->and($brief['actions'])->toBe([['label' => 'Open T-4', 'fact' => 'task:T-4']]);
});

it('refuses answers that are not a brief', function (string $answer) {
    (new BriefParser)->parse($answer, briefFacts());
})->throws(InvalidBrief::class)->with([
    'plain text' => 'You have two things to do today.',
    'broken json' => '{"headline": "Hi", "items": [',
    'a list' => '[{"headline": "Hi"}]',
    'wrong severity' => '{"headline": "Hi", "items": [{"title": "A", "detail": "B", "severity": "critical", "facts": ["task:T-4"]}]}',
    'no real facts' => '{"headline": "Hi", "items": [{"title": "A", "detail": "B", "severity": "high", "facts": ["task:T-1"]}]}',
]);

it('cuts long text to size', function () {
    $answer = json_encode(['headline' => str_repeat('a', 290), 'items' => [['title' => str_repeat('b', 290), 'detail' => 'ok', 'severity' => 'low', 'facts' => ['item:GLV-100']]]]);

    $brief = (new BriefParser)->parse((string) $answer, briefFacts());

    expect(mb_strlen($brief['headline']))->toBeLessThanOrEqual(163)
        ->and(mb_strlen($brief['items'][0]['title']))->toBeLessThanOrEqual(143);
});
