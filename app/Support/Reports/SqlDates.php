<?php

namespace App\Support\Reports;

use App\Enums\ReportBucket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Date arithmetic for report queries, written for the database in use.
 *
 * Timestamps are stored in UTC; reports group them by the organization's
 * calendar. PostgreSQL converts with real timezone rules. SQLite, which only
 * runs the test suite, shifts by the offset the timezone has at the start of
 * the range.
 *
 * The SQL is fixed text with one placeholder for the timezone (or the SQLite
 * shift); pass bindings() with every bucket() or localDate() fragment, and
 * group by the selected alias rather than repeating the expression.
 */
final class SqlDates
{
    private readonly string $driver;

    private readonly string $timezone;

    private readonly int $offsetMinutes;

    public function __construct(
        string $timezone,
        private readonly ReportBucket $bucket = ReportBucket::Day,
        ?CarbonImmutable $at = null,
        ?Connection $connection = null,
    ) {
        $this->driver = ($connection ?? DB::connection())->getDriverName();
        $this->timezone = in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
        $this->offsetMinutes = intdiv(($at ?? CarbonImmutable::now())->setTimezone($this->timezone)->getOffset(), 60);
    }

    public static function for(ReportQuery $query): self
    {
        return new self($query->timezone, $query->bucket, $query->from);
    }

    /**
     * The start of the bucket a timestamp falls in, as "Y-m-d" text. Takes
     * one binding.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public function bucket(string $column): string
    {
        if ($this->driver === 'pgsql') {
            $local = "(({$column} at time zone 'UTC') at time zone ?)";

            return match ($this->bucket) {
                ReportBucket::Day => "to_char({$local}, 'YYYY-MM-DD')",
                ReportBucket::Week => "to_char(date_trunc('week', {$local}), 'YYYY-MM-DD')",
                ReportBucket::Month => "to_char(date_trunc('month', {$local}), 'YYYY-MM-DD')",
            };
        }

        return match ($this->bucket) {
            ReportBucket::Day => "date({$column}, ?)",
            // "weekday 0" moves forward to Sunday; six days back is that week's Monday.
            ReportBucket::Week => "date({$column}, ?, 'weekday 0', '-6 days')",
            ReportBucket::Month => "date({$column}, ?, 'start of month')",
        };
    }

    /**
     * The calendar date of a timestamp in the organization's timezone,
     * comparable with date(). Takes one binding.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public function localDate(string $column): string
    {
        return $this->driver === 'pgsql'
            ? "cast((({$column} at time zone 'UTC') at time zone ?) as date)"
            : "date({$column}, ?)";
    }

    /**
     * The binding each bucket() or localDate() fragment takes.
     *
     * @return list<string>
     */
    public function bindings(): array
    {
        return [$this->driver === 'pgsql' ? $this->timezone : sprintf('%+d minutes', $this->offsetMinutes)];
    }

    /**
     * A date column, comparable with localDate().
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    public function date(string $column): string
    {
        return $this->driver === 'pgsql' ? $column : "date({$column})";
    }

    /**
     * Seconds from one timestamp column to another.
     *
     * @param  literal-string  $start
     * @param  literal-string  $end
     * @return literal-string
     */
    public function secondsBetween(string $start, string $end): string
    {
        return $this->driver === 'pgsql'
            ? "extract(epoch from ({$end} - {$start}))"
            : "((julianday({$end}) - julianday({$start})) * 86400.0)";
    }
}
