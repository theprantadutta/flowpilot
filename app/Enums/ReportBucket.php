<?php

namespace App\Enums;

/**
 * How a report's date range is cut up for charts.
 */
enum ReportBucket: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'By day',
            self::Week => 'By week',
            self::Month => 'By month',
        };
    }

    /**
     * A sensible default: days for a month, weeks for a quarter, months beyond.
     */
    public static function forDays(int $days): self
    {
        return match (true) {
            $days <= 31 => self::Day,
            $days <= 120 => self::Week,
            default => self::Month,
        };
    }

    /**
     * The most buckets a chart may have, so columns stay readable.
     */
    public function maximumDays(): int
    {
        return match ($this) {
            self::Day => 92,
            self::Week => 400,
            self::Month => 1100,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $bucket): array => ['value' => $bucket->value, 'label' => $bucket->label()], self::cases());
    }
}
