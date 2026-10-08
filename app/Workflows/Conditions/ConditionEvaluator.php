<?php

namespace App\Workflows\Conditions;

use App\Enums\ConditionOperator;
use App\Support\Money;
use App\Workflows\Fields\Field;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Decides whether a set of rules matches a run's context.
 *
 * Rules come from the builder as data ({field, operator, value}); they are
 * compared by the field's type, never evaluated as code.
 *
 *   { match: "all" | "any", rules: [{ field: "input.amount", operator: "greater_than", value: "5000" }] }
 */
class ConditionEvaluator
{
    public const int MAX_RULES = 20;

    /**
     * @param  array<string, mixed>  $config  The rule set.
     * @param  array<string, mixed>  $context  The run context the fields point into.
     * @param  list<Field>  $fields  What the run can read, for types.
     * @param  string  $currency  Currency of money fields and values.
     * @param  string  $today  The organization's current date (Y-m-d), for "today".
     */
    public function evaluate(array $config, array $context, array $fields, string $currency, string $today): ConditionResult
    {
        $matchAll = ($config['match'] ?? 'all') !== 'any';
        $checks = [];

        foreach (self::rules($config) as $rule) {
            $field = Field::find($fields, $rule['field']) ?? new Field($rule['field'], $rule['field'], 'text');
            $actual = Arr::get($context, $rule['field']);
            $operator = ConditionOperator::from($rule['operator']);

            $checks[] = [
                'field' => $field->label,
                'operator' => $operator->label(),
                'expected' => $operator->needsValue() ? $rule['value'] : null,
                'actual' => self::displayable($actual),
                'passed' => $this->check($operator, $field->type, $actual, $rule['value'], $currency, $today),
            ];
        }

        $passed = array_column($checks, 'passed');

        $result = match (true) {
            $passed === [] => true,
            $matchAll => ! in_array(false, $passed, true),
            default => in_array(true, $passed, true),
        };

        return new ConditionResult($result, $checks);
    }

    /**
     * The rules of a rule set, with unusable entries dropped.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{field: string, operator: string, value: string|null}>
     */
    public static function rules(array $config): array
    {
        $rules = [];

        foreach (is_array($config['rules'] ?? null) ? $config['rules'] : [] as $rule) {
            if (! is_array($rule) || ! is_string($rule['field'] ?? null) || ConditionOperator::tryFrom((string) ($rule['operator'] ?? '')) === null) {
                continue;
            }

            $value = $rule['value'] ?? null;

            $rules[] = [
                'field' => $rule['field'],
                'operator' => (string) $rule['operator'],
                'value' => is_scalar($value) ? mb_substr((string) $value, 0, 500) : null,
            ];
        }

        return $rules;
    }

    /**
     * Problems with a rule set, given the fields the workflow can read.
     *
     * @param  array<string, mixed>  $config
     * @param  list<Field>  $fields
     * @return list<string>
     */
    public static function validate(array $config, array $fields, string $currency): array
    {
        $raw = is_array($config['rules'] ?? null) ? $config['rules'] : [];
        $rules = self::rules($config);
        $errors = [];

        if ($raw === []) {
            return ['Add at least one rule.'];
        }

        if (count($raw) !== count($rules)) {
            $errors[] = 'Every rule needs a field and a comparison.';
        }

        if (count($rules) > self::MAX_RULES) {
            $errors[] = 'Use at most '.self::MAX_RULES.' rules.';
        }

        if (! in_array($config['match'] ?? 'all', ['all', 'any'], true)) {
            $errors[] = 'Choose whether all or any of the rules must match.';
        }

        foreach ($rules as $rule) {
            $field = Field::find($fields, $rule['field']);

            if ($field === null) {
                $errors[] = "The field \"{$rule['field']}\" is not available for this trigger.";

                continue;
            }

            $operator = ConditionOperator::from($rule['operator']);

            if (! in_array($operator, ConditionOperator::forFieldType($field->type), true)) {
                $errors[] = "\"{$field->label}\" cannot be compared with \"{$operator->label()}\".";

                continue;
            }

            if (! $operator->needsValue()) {
                continue;
            }

            $value = $rule['value'];

            if ($value === null || $value === '') {
                $errors[] = "Enter a value to compare \"{$field->label}\" with.";

                continue;
            }

            $problem = match ($field->type) {
                'number' => is_numeric($value) ? null : 'must be a number',
                'money' => self::isMoney($value, $currency) ? null : 'must be an amount, like 5000 or 5000.00',
                'date' => $value === 'today' || self::isDate($value) ? null : 'must be a date',
                'boolean' => in_array($value, ['true', 'false'], true) ? null : 'must be yes or no',
                'select' => in_array($value, array_column($field->options, 'value'), true) ? null : 'must be one of the options',
                'person' => ctype_digit($value) ? null : 'must be a member',
                default => null,
            };

            if ($problem !== null) {
                $errors[] = "The value for \"{$field->label}\" {$problem}.";
            }
        }

        return array_values(array_unique($errors));
    }

    private function check(ConditionOperator $operator, string $type, mixed $actual, ?string $expected, string $currency, string $today): bool
    {
        if ($operator === ConditionOperator::IsEmpty) {
            return self::isEmpty($actual);
        }

        if ($operator === ConditionOperator::IsNotEmpty) {
            return ! self::isEmpty($actual);
        }

        if ($expected === null || self::isEmpty($actual)) {
            // Nothing to compare: only "is not" can be true.
            return $operator === ConditionOperator::NotEquals && $expected !== null;
        }

        return match ($type) {
            'number', 'money', 'person' => $this->compareNumbers($operator, $actual, $expected, $type, $currency),
            'date' => $this->compareDates($operator, $actual, $expected === 'today' ? $today : $expected),
            'boolean' => $this->compareText($operator, self::booleanString($actual), $expected),
            'select' => $this->compareText($operator, is_scalar($actual) ? (string) $actual : '', $expected, caseSensitive: true),
            default => is_array($actual)
                ? $this->compareList($operator, $actual, $expected)
                : $this->compareText($operator, is_scalar($actual) ? (string) $actual : '', $expected),
        };
    }

    private function compareNumbers(ConditionOperator $operator, mixed $actual, string $expected, string $type, string $currency): bool
    {
        if (! is_numeric($actual)) {
            return $operator === ConditionOperator::NotEquals;
        }

        try {
            $expectedNumber = $type === 'money' ? Money::toMinorUnits($expected, $currency) : $expected;
        } catch (InvalidArgumentException) {
            return false;
        }

        if (! is_numeric($expectedNumber)) {
            return false;
        }

        // Exact decimal comparison, so 0.1 + 0.2 style surprises cannot change a path.
        $comparison = bccomp(self::decimal($actual), self::decimal($expectedNumber), 6);

        return match ($operator) {
            ConditionOperator::Equals => $comparison === 0,
            ConditionOperator::NotEquals => $comparison !== 0,
            ConditionOperator::GreaterThan => $comparison === 1,
            ConditionOperator::LessThan => $comparison === -1,
            default => false,
        };
    }

    private function compareDates(ConditionOperator $operator, mixed $actual, string $expected): bool
    {
        if (! is_string($actual) || ! self::isDate(substr($actual, 0, 10)) || ! self::isDate($expected)) {
            return $operator === ConditionOperator::NotEquals;
        }

        $comparison = strcmp(substr($actual, 0, 10), $expected) <=> 0;

        return match ($operator) {
            ConditionOperator::Equals => $comparison === 0,
            ConditionOperator::NotEquals => $comparison !== 0,
            ConditionOperator::GreaterThan => $comparison === 1,
            ConditionOperator::LessThan => $comparison === -1,
            default => false,
        };
    }

    private function compareText(ConditionOperator $operator, string $actual, string $expected, bool $caseSensitive = false): bool
    {
        if (! $caseSensitive) {
            $actual = mb_strtolower(trim($actual));
            $expected = mb_strtolower(trim($expected));
        }

        return match ($operator) {
            ConditionOperator::Equals => $actual === $expected,
            ConditionOperator::NotEquals => $actual !== $expected,
            ConditionOperator::Contains => str_contains($actual, $expected),
            ConditionOperator::StartsWith => str_starts_with($actual, $expected),
            ConditionOperator::GreaterThan => strcmp($actual, $expected) > 0,
            ConditionOperator::LessThan => strcmp($actual, $expected) < 0,
            default => false,
        };
    }

    /**
     * Lists (tags) match when any entry does; "is not" holds when none is equal.
     *
     * @param  array<mixed>  $actual
     */
    private function compareList(ConditionOperator $operator, array $actual, string $expected): bool
    {
        $entries = array_map(fn (mixed $entry): string => is_scalar($entry) ? (string) $entry : '', $actual);

        if ($operator === ConditionOperator::NotEquals) {
            foreach ($entries as $entry) {
                if ($this->compareText(ConditionOperator::Equals, $entry, $expected)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($entries as $entry) {
            if ($this->compareText($operator, $entry, $expected)) {
                return true;
            }
        }

        return false;
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || (is_string($value) && trim($value) === '');
    }

    private static function booleanString(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    /**
     * A plain decimal string bcmath accepts ("1250", "-3.5"), from a number or
     * numeric text in any notation.
     *
     * @return numeric-string
     */
    private static function decimal(mixed $value): string
    {
        $string = is_float($value) ? number_format($value, 6, '.', '') : trim(is_scalar($value) ? (string) $value : '');

        if (preg_match('/^-?\d+(\.\d+)?$/', $string) !== 1) {
            $string = is_numeric($string) ? number_format((float) $string, 6, '.', '') : '0';
        }

        return is_numeric($string) ? $string : '0';
    }

    private static function isDate(string $value): bool
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->toDateString() === $value;
    }

    private static function isMoney(string $value, string $currency): bool
    {
        try {
            Money::toMinorUnits($value, $currency);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function displayable(mixed $value): string|int|float|bool|null
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $entry): string => is_scalar($entry) ? (string) $entry : '', $value));
        }

        return is_scalar($value) || $value === null ? $value : null;
    }
}
