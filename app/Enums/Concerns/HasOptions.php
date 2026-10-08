<?php

namespace App\Enums\Concerns;

/**
 * Shared option list for string-backed enums that describe themselves with
 * label(), tone() and icon().
 *
 * @phpstan-require-implements \BackedEnum
 */
trait HasOptions
{
    /**
     * @return list<array{value: string, label: string, tone: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => [
            'value' => (string) $case->value,
            'label' => $case->label(),
            'tone' => $case->tone(),
            'icon' => $case->icon(),
        ], self::cases());
    }

    /**
     * @return array{value: string, label: string, tone: string, icon: string}
     */
    public function toOption(): array
    {
        return [
            'value' => (string) $this->value,
            'label' => $this->label(),
            'tone' => $this->tone(),
            'icon' => $this->icon(),
        ];
    }
}
