<?php

namespace App\Workflows\Support;

use App\Models\Organization;
use App\Models\User;
use App\Support\Money;
use App\Workflows\Fields\Field;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Fills {{ placeholders }} in messages with values from a run, formatted for
 * people: money with its currency, dates in the organization's format,
 * members by name. Placeholders are paths, never expressions.
 */
class TemplateRenderer
{
    public const string PATTERN = '/\{\{\s*([a-zA-Z0-9_.]{1,120})\s*\}\}/';

    /**
     * @var array<int, string>
     */
    private array $names = [];

    /**
     * @param  array<string, mixed>  $context
     * @param  list<Field>  $fields
     */
    public function render(string $template, array $context, array $fields, Organization $organization, int $limit = 5000): string
    {
        $rendered = preg_replace_callback(self::PATTERN, function (array $match) use ($context, $fields, $organization): string {
            return $this->format(Arr::get($context, $match[1]), Field::find($fields, $match[1]), $organization);
        }, $template) ?? $template;

        return mb_substr($rendered, 0, $limit);
    }

    /**
     * The placeholder paths used in a template.
     *
     * @return list<string>
     */
    public static function paths(string $template): array
    {
        preg_match_all(self::PATTERN, $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function format(mixed $value, ?Field $field, Organization $organization): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $entry): string => $this->format($entry, $field, $organization), $value));
        }

        if (! is_scalar($value)) {
            return '';
        }

        return match ($field?->type) {
            'money' => $this->money($value, $organization->currency, $organization->locale),
            'date' => $this->date((string) $value, $organization->date_format),
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No',
            'person' => is_numeric($value) ? $this->name((int) $value) : (string) $value,
            'select' => $this->optionLabel((string) $value, $field),
            default => (string) $value,
        };
    }

    private function money(string|int|float|bool $value, string $currency, string $locale): string
    {
        return is_numeric($value) ? Money::format((int) $value, $currency, $locale) : (string) $value;
    }

    private function date(string $value, string $format): string
    {
        try {
            return CarbonImmutable::parse($value)->format($format);
        } catch (Throwable) {
            return $value;
        }
    }

    private function name(int $userId): string
    {
        return $this->names[$userId] ??= (string) (User::query()->whereKey($userId)->value('name') ?? 'a former member');
    }

    private function optionLabel(string $value, ?Field $field): string
    {
        foreach ($field === null ? [] : $field->options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }
}
