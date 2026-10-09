<?php

namespace App\Support\Reports;

use App\Enums\ReportBucket;
use Carbon\CarbonImmutable;

/**
 * What a report is asked for: a date range in the organization's timezone,
 * how to cut it up, how to group the table, and the report's own filters.
 */
final readonly class ReportQuery
{
    /**
     * Preset ranges, in the order the picker lists them.
     *
     * @var array<string, string>
     */
    public const array PRESETS = [
        'last_7_days' => 'Last 7 days',
        'last_30_days' => 'Last 30 days',
        'last_90_days' => 'Last 90 days',
        'last_12_months' => 'Last 12 months',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'custom' => 'Custom range',
    ];

    /**
     * The longest custom range, so a report never scans years of history.
     */
    public const int MAXIMUM_DAYS = 731;

    /**
     * @param  CarbonImmutable  $from  Start of the first day, in the organization's timezone.
     * @param  CarbonImmutable  $to  End of the last day, in the organization's timezone.
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public string $range,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ReportBucket $bucket,
        public ?string $group,
        public array $filters,
        public string $timezone,
    ) {}

    /**
     * Build from a preset or a custom range. Dates are "Y-m-d" in the
     * organization's timezone and are assumed valid (see ReportParameters).
     *
     * @param  array<string, string>  $filters
     */
    public static function make(
        string $timezone,
        string $range = 'last_30_days',
        ?string $from = null,
        ?string $to = null,
        ?ReportBucket $bucket = null,
        ?string $group = null,
        array $filters = [],
        ?CarbonImmutable $now = null,
    ): self {
        $today = ($now ?? CarbonImmutable::now())->setTimezone($timezone)->startOfDay();

        [$start, $end] = match ($range) {
            'last_7_days' => [$today->subDays(6), $today],
            'last_90_days' => [$today->subDays(89), $today],
            'last_12_months' => [$today->subMonthsNoOverflow(12)->addDay(), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'custom' => [
                CarbonImmutable::createFromFormat('Y-m-d', (string) $from, $timezone)?->startOfDay() ?? $today->subDays(29),
                CarbonImmutable::createFromFormat('Y-m-d', (string) $to, $timezone)?->startOfDay() ?? $today,
            ],
            default => [$today->subDays(29), $today],
        };

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        if ($start->diffInDays($end) >= self::MAXIMUM_DAYS) {
            $start = $end->subDays(self::MAXIMUM_DAYS - 1);
        }

        $days = (int) $start->diffInDays($end) + 1;
        $bucket = $bucket !== null && $days <= $bucket->maximumDays() ? $bucket : ReportBucket::forDays($days);

        return new self(
            range: array_key_exists($range, self::PRESETS) ? $range : 'last_30_days',
            from: $start->startOfDay(),
            to: $end->endOfDay(),
            bucket: $bucket,
            group: $group,
            filters: $filters,
            timezone: $timezone,
        );
    }

    /**
     * The same query with other values for some of its parts.
     *
     * @param  array<string, string>|null  $filters
     */
    public function with(?string $group = null, ?array $filters = null): self
    {
        return new self($this->range, $this->from, $this->to, $this->bucket, $group ?? $this->group, $filters ?? $this->filters, $this->timezone);
    }

    public function filter(string $key): ?string
    {
        $value = $this->filters[$key] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * The range as UTC instants, for comparing with stored timestamps.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function between(): array
    {
        return [$this->from->utc(), $this->to->utc()];
    }

    /**
     * The range as calendar dates, for comparing with date columns.
     *
     * @return array{0: string, 1: string}
     */
    public function dates(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }

    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * "Oct 1 – Oct 31, 2026", for titles, exports and notifications.
     */
    public function label(): string
    {
        $sameYear = $this->from->year === $this->to->year;

        return $this->from->format($sameYear ? 'M j' : 'M j, Y').' – '.$this->to->format('M j, Y');
    }

    /**
     * What is stored with an export so the job can rebuild the query.
     *
     * @return array{range: string, from: string, to: string, bucket: string, group: string|null, filters: array<string, string>}
     */
    public function toArray(): array
    {
        return [
            'range' => $this->range,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'bucket' => $this->bucket->value,
            'group' => $this->group,
            'filters' => $this->filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters  As produced by toArray().
     */
    public static function fromArray(array $parameters, string $timezone): self
    {
        /** @var array<string, string> $filters */
        $filters = is_array($parameters['filters'] ?? null) ? $parameters['filters'] : [];

        return self::make(
            timezone: $timezone,
            range: 'custom',
            from: is_string($parameters['from'] ?? null) ? $parameters['from'] : null,
            to: is_string($parameters['to'] ?? null) ? $parameters['to'] : null,
            bucket: ReportBucket::tryFrom((string) ($parameters['bucket'] ?? '')),
            group: is_string($parameters['group'] ?? null) ? $parameters['group'] : null,
            filters: $filters,
        )->withRange(is_string($parameters['range'] ?? null) ? $parameters['range'] : 'custom');
    }

    private function withRange(string $range): self
    {
        return new self(array_key_exists($range, self::PRESETS) ? $range : 'custom', $this->from, $this->to, $this->bucket, $this->group, $this->filters, $this->timezone);
    }
}
