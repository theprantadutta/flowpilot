<?php

use App\Workflows\Conditions\ConditionEvaluator;
use App\Workflows\Fields\Field;
use Tests\TestCase;

// Money precision comes from configuration, so these need the application.
uses(TestCase::class);

function conditionFields(): array
{
    return [
        new Field('input.amount', 'Amount', 'money'),
        new Field('input.quantity', 'Quantity', 'number'),
        new Field('input.needed_by', 'Needed by', 'date'),
        new Field('input.urgent', 'Urgent', 'boolean'),
        new Field('input.category', 'Category', 'select', [['value' => 'it', 'label' => 'IT'], ['value' => 'facilities', 'label' => 'Facilities']]),
        new Field('input.approver', 'Approver', 'person'),
        new Field('subject.title', 'Title', 'text'),
        new Field('subject.tags', 'Tags', 'text'),
    ];
}

function conditionPasses(array $rules, array $context, string $match = 'all'): bool
{
    return (new ConditionEvaluator)
        ->evaluate(['match' => $match, 'rules' => $rules], $context, conditionFields(), 'USD', '2026-10-08')
        ->passed;
}

test('money compares in minor units against amounts typed in major units', function (string $operator, string $value, bool $expected) {
    // $6,200.00 stored as minor units.
    expect(conditionPasses([['field' => 'input.amount', 'operator' => $operator, 'value' => $value]], ['input' => ['amount' => 620000]]))->toBe($expected);
})->with([
    'greater than 5,000' => ['greater_than', '5000', true],
    'greater than 6,200.00' => ['greater_than', '6200.00', false],
    'equals 6,200 with separators' => ['equals', '6,200.00', true],
    'less than 6,200.01' => ['less_than', '6200.01', true],
    'not equal to 6,200' => ['not_equals', '6200', false],
]);

test('numbers compare exactly, including decimals', function (string $operator, string $value, bool $expected) {
    expect(conditionPasses([['field' => 'input.quantity', 'operator' => $operator, 'value' => $value]], ['input' => ['quantity' => 0.3]]))->toBe($expected);
})->with([
    'equals 0.3' => ['equals', '0.3', true],
    'greater than 0.29' => ['greater_than', '0.29', true],
    'less than 0.3' => ['less_than', '0.3', false],
]);

test('dates compare by day and understand today', function () {
    $context = ['input' => ['needed_by' => '2026-10-10']];

    expect(conditionPasses([['field' => 'input.needed_by', 'operator' => 'greater_than', 'value' => 'today']], $context))->toBeTrue()
        ->and(conditionPasses([['field' => 'input.needed_by', 'operator' => 'less_than', 'value' => '2026-10-10']], $context))->toBeFalse()
        ->and(conditionPasses([['field' => 'input.needed_by', 'operator' => 'equals', 'value' => '2026-10-10']], $context))->toBeTrue();
});

test('text comparisons ignore case and surrounding spaces', function () {
    $context = ['subject' => ['title' => '  Conveyor STOPS under load ']];

    expect(conditionPasses([['field' => 'subject.title', 'operator' => 'contains', 'value' => 'stops']], $context))->toBeTrue()
        ->and(conditionPasses([['field' => 'subject.title', 'operator' => 'starts_with', 'value' => 'conveyor']], $context))->toBeTrue()
        ->and(conditionPasses([['field' => 'subject.title', 'operator' => 'equals', 'value' => 'conveyor stops under load']], $context))->toBeTrue();
});

test('lists match when any entry does', function () {
    $context = ['subject' => ['tags' => ['safety', 'line-2']]];

    expect(conditionPasses([['field' => 'subject.tags', 'operator' => 'equals', 'value' => 'Safety']], $context))->toBeTrue()
        ->and(conditionPasses([['field' => 'subject.tags', 'operator' => 'not_equals', 'value' => 'safety']], $context))->toBeFalse()
        ->and(conditionPasses([['field' => 'subject.tags', 'operator' => 'contains', 'value' => 'line']], $context))->toBeTrue();
});

test('select values must match exactly', function () {
    expect(conditionPasses([['field' => 'input.category', 'operator' => 'equals', 'value' => 'it']], ['input' => ['category' => 'it']]))->toBeTrue()
        ->and(conditionPasses([['field' => 'input.category', 'operator' => 'equals', 'value' => 'IT']], ['input' => ['category' => 'it']]))->toBeFalse();
});

test('booleans and people compare by value', function () {
    $context = ['input' => ['urgent' => true, 'approver' => 12]];

    expect(conditionPasses([['field' => 'input.urgent', 'operator' => 'equals', 'value' => 'true']], $context))->toBeTrue()
        ->and(conditionPasses([['field' => 'input.approver', 'operator' => 'equals', 'value' => '12']], $context))->toBeTrue()
        ->and(conditionPasses([['field' => 'input.approver', 'operator' => 'not_equals', 'value' => '7']], $context))->toBeTrue();
});

test('empty checks treat null, blank text and empty lists as empty', function (mixed $value, bool $empty) {
    $context = ['subject' => ['title' => $value]];

    expect(conditionPasses([['field' => 'subject.title', 'operator' => 'is_empty', 'value' => null]], $context))->toBe($empty)
        ->and(conditionPasses([['field' => 'subject.title', 'operator' => 'is_not_empty', 'value' => null]], $context))->toBe(! $empty);
})->with([
    'null' => [null, true],
    'blank text' => ['   ', true],
    'empty list' => [[], true],
    'zero' => [0, false],
    'text' => ['Pump', false],
]);

test('missing values only satisfy "is not"', function () {
    expect(conditionPasses([['field' => 'input.amount', 'operator' => 'greater_than', 'value' => '0']], ['input' => []]))->toBeFalse()
        ->and(conditionPasses([['field' => 'input.amount', 'operator' => 'not_equals', 'value' => '0']], ['input' => []]))->toBeTrue();
});

test('all needs every rule and any needs one', function () {
    $context = ['input' => ['amount' => 620000, 'category' => 'facilities']];
    $rules = [
        ['field' => 'input.amount', 'operator' => 'greater_than', 'value' => '5000'],
        ['field' => 'input.category', 'operator' => 'equals', 'value' => 'it'],
    ];

    expect(conditionPasses($rules, $context, 'all'))->toBeFalse()
        ->and(conditionPasses($rules, $context, 'any'))->toBeTrue();
});

test('results explain each comparison, showing amounts as money', function () {
    $result = (new ConditionEvaluator)->evaluate(
        ['match' => 'all', 'rules' => [['field' => 'input.amount', 'operator' => 'greater_than', 'value' => '5000']]],
        ['input' => ['amount' => 620000]],
        conditionFields(),
        'USD',
        '2026-10-08',
    );

    expect($result->checks)->toBe([[
        'field' => 'Amount',
        'operator' => 'is greater than',
        'expected' => '$5,000.00',
        'actual' => '$6,200.00',
        'passed' => true,
    ]]);
});

test('validation reports unknown fields, wrong comparisons and bad values', function () {
    $errors = ConditionEvaluator::validate(['match' => 'all', 'rules' => [
        ['field' => 'input.nope', 'operator' => 'equals', 'value' => 'x'],
        ['field' => 'input.category', 'operator' => 'greater_than', 'value' => 'it'],
        ['field' => 'input.amount', 'operator' => 'greater_than', 'value' => 'lots'],
        ['field' => 'input.needed_by', 'operator' => 'equals', 'value' => '2026-02-30'],
        ['field' => 'input.category', 'operator' => 'equals', 'value' => 'kitchen'],
    ]], conditionFields(), 'USD');

    expect($errors)->toBe([
        'The field "input.nope" is not available for this trigger.',
        '"Category" cannot be compared with "is greater than".',
        'The value for "Amount" must be an amount, like 5000 or 5000.00.',
        'The value for "Needed by" must be a date.',
        'The value for "Category" must be one of the options.',
    ]);
});

test('validation needs at least one rule', function () {
    expect(ConditionEvaluator::validate(['match' => 'all', 'rules' => []], conditionFields(), 'USD'))->toBe(['Add at least one rule.']);
});
