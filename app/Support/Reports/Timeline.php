<?php

namespace App\Support\Reports;

use App\Enums\ReportBucket;
use Carbon\CarbonImmutable;

/**
 * The buckets a report's range is cut into, matching SqlDates::bucket().
 */
final class Timeline
{
    /**
     * Bucket starts as "Y-m-d", oldest first.
     *
     * @return list<string>
     */
    public static function keys(ReportQuery $query): array
    {
        $keys = [];
        $cursor = self::startOf($query->from, $query->bucket);
        $end = $query->to->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->toDateString();
            $cursor = match ($query->bucket) {
                ReportBucket::Day => $cursor->addDay(),
                ReportBucket::Week => $cursor->addWeek(),
                ReportBucket::Month => $cursor->addMonthNoOverflow(),
            };
        }

        return $keys;
    }

    /**
     * Axis labels, one per key: "Oct 6", or "Oct 2026" for months.
     *
     * @return list<string>
     */
    public static function labels(ReportQuery $query): array
    {
        return array_map(
            fn (string $key): string => CarbonImmutable::createFromFormat('!Y-m-d', $key, $query->timezone)?->format($query->bucket === ReportBucket::Month ? 'M Y' : 'M j') ?? $key,
            self::keys($query),
        );
    }

    /**
     * Values for every bucket, with gaps filled.
     *
     * @param  array<string, int|float>  $values  Keyed by bucket start.
     * @return list<int|float|null>
     */
    public static function fill(ReportQuery $query, array $values, int|float|null $empty = 0): array
    {
        return array_map(fn (string $key): int|float|null => $values[$key] ?? $empty, self::keys($query));
    }

    private static function startOf(CarbonImmutable $date, ReportBucket $bucket): CarbonImmutable
    {
        return match ($bucket) {
            ReportBucket::Day => $date->startOfDay(),
            ReportBucket::Week => $date->startOfWeek(CarbonImmutable::MONDAY),
            ReportBucket::Month => $date->startOfMonth(),
        };
    }
}
