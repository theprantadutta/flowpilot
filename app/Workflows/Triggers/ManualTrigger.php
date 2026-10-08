<?php

namespace App\Workflows\Triggers;

use App\Support\Money;
use App\Workflows\Fields\Field;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * Started by a person, who fills in the inputs the workflow asks for
 * (an amount, a date, a choice…).
 */
class ManualTrigger extends Trigger
{
    public const int MAX_INPUTS = 20;

    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public const array INPUT_TYPES = ['text', 'number', 'money', 'date', 'select', 'boolean', 'person'];

    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Started by a person';
    }

    public function description(): string
    {
        return 'Someone starts a run and fills in the details it needs.';
    }

    protected function ownFields(array $config): array
    {
        return array_map(fn (array $input): Field => new Field(
            path: 'input.'.$input['key'],
            label: $input['label'],
            type: $input['type'],
            options: $input['options'],
        ), $this->inputs($config));
    }

    /**
     * The inputs the trigger asks for, normalized.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<array{value: string, label: string}>}>
     */
    public function inputs(array $config): array
    {
        $inputs = [];

        foreach (is_array($config['inputs'] ?? null) ? $config['inputs'] : [] as $input) {
            if (! is_array($input) || ! is_string($input['key'] ?? null)) {
                continue;
            }

            $options = [];

            foreach (is_array($input['options'] ?? null) ? $input['options'] : [] as $option) {
                if (is_array($option) && is_scalar($option['value'] ?? null) && (string) $option['value'] !== '') {
                    $options[] = [
                        'value' => mb_substr((string) $option['value'], 0, 80),
                        'label' => mb_substr(is_scalar($option['label'] ?? null) ? (string) $option['label'] : (string) $option['value'], 0, 80),
                    ];
                }
            }

            $inputs[] = [
                'key' => $input['key'],
                'label' => is_string($input['label'] ?? null) && trim($input['label']) !== '' ? mb_substr(trim($input['label']), 0, 80) : $input['key'],
                'type' => in_array($input['type'] ?? null, self::INPUT_TYPES, true) ? (string) $input['type'] : 'text',
                'required' => (bool) ($input['required'] ?? false),
                'options' => $options,
            ];
        }

        return $inputs;
    }

    public function validate(array $config): array
    {
        $errors = [];
        $raw = is_array($config['inputs'] ?? null) ? $config['inputs'] : [];

        if (count($raw) > self::MAX_INPUTS) {
            $errors[] = 'A workflow can ask for at most '.self::MAX_INPUTS.' details.';
        }

        $seen = [];

        foreach ($raw as $input) {
            $key = is_array($input) && is_string($input['key'] ?? null) ? $input['key'] : '';

            if (! preg_match(self::KEY_PATTERN, $key)) {
                $errors[] = 'Each detail needs a key of lowercase letters, numbers and underscores, starting with a letter.';

                continue;
            }

            if (isset($seen[$key])) {
                $errors[] = "Two details use the key \"{$key}\".";
            }

            $seen[$key] = true;

            if (! in_array($input['type'] ?? 'text', self::INPUT_TYPES, true)) {
                $errors[] = "The detail \"{$key}\" has an unknown type.";
            }
        }

        foreach ($this->inputs($config) as $input) {
            if ($input['type'] === 'select' && $input['options'] === []) {
                $errors[] = "The choice \"{$input['label']}\" needs at least one option.";
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Check and convert what the person entered: money to minor units, numbers
     * to numbers, dates to Y-m-d, people to member ids.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $values
     * @param  list<int>  $memberIds
     * @return array{values: array<string, mixed>, errors: array<string, string>}
     */
    public function normalizeInput(array $config, array $values, string $currency, array $memberIds): array
    {
        $normalized = [];
        $errors = [];

        foreach ($this->inputs($config) as $input) {
            $raw = $values[$input['key']] ?? null;
            $isBlank = $raw === null || $raw === '' || $raw === [];

            if ($isBlank && $input['type'] !== 'boolean') {
                if ($input['required']) {
                    $errors[$input['key']] = "{$input['label']} is required.";
                }

                $normalized[$input['key']] = null;

                continue;
            }

            try {
                $normalized[$input['key']] = $this->convert($input, $raw, $currency, $memberIds);
            } catch (InvalidArgumentException $exception) {
                $errors[$input['key']] = $exception->getMessage();
            }
        }

        return ['values' => $normalized, 'errors' => $errors];
    }

    /**
     * @param  array{key: string, label: string, type: string, required: bool, options: list<array{value: string, label: string}>}  $input
     * @param  list<int>  $memberIds
     */
    private function convert(array $input, mixed $raw, string $currency, array $memberIds): mixed
    {
        $label = $input['label'];

        switch ($input['type']) {
            case 'number':
                if (! is_numeric($raw)) {
                    throw new InvalidArgumentException("{$label} must be a number.");
                }

                return str_contains((string) $raw, '.') ? (float) $raw : (int) $raw;

            case 'money':
                if (! is_scalar($raw)) {
                    throw new InvalidArgumentException("{$label} must be an amount.");
                }

                try {
                    return Money::toMinorUnits((string) $raw, $currency);
                } catch (InvalidArgumentException) {
                    throw new InvalidArgumentException("{$label} must be an amount, like 1250.00.");
                }

            case 'date':
                try {
                    return CarbonImmutable::createFromFormat('!Y-m-d', is_string($raw) ? $raw : '')?->toDateString()
                        ?? throw new InvalidArgumentException;
                } catch (Throwable) {
                    throw new InvalidArgumentException("{$label} must be a date.");
                }

            case 'boolean':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);

            case 'select':
                $values = array_column($input['options'], 'value');

                if (! is_scalar($raw) || ! in_array((string) $raw, $values, true)) {
                    throw new InvalidArgumentException("Choose one of the options for {$label}.");
                }

                return (string) $raw;

            case 'person':
                if (! is_numeric($raw) || ! in_array((int) $raw, $memberIds, true)) {
                    throw new InvalidArgumentException("Choose a member of the organization for {$label}.");
                }

                return (int) $raw;

            default:
                if (! is_scalar($raw)) {
                    throw new InvalidArgumentException("{$label} must be text.");
                }

                return mb_substr(trim((string) $raw), 0, 2000);
        }
    }
}
