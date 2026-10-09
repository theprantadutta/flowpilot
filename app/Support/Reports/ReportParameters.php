<?php

namespace App\Support\Reports;

use App\Enums\ReportBucket;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonImmutable;

/**
 * Turns the report's URL parameters into a ReportQuery. Anything that is not
 * a known range, grouping or filter value is dropped rather than trusted.
 */
class ReportParameters
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function query(Report $report, array $input): ReportQuery
    {
        $range = is_string($input['range'] ?? null) && array_key_exists($input['range'], ReportQuery::PRESETS) ? $input['range'] : 'last_30_days';
        $from = $this->date($input['from'] ?? null);
        $to = $this->date($input['to'] ?? null);

        if ($range === 'custom' && ($from === null || $to === null)) {
            $range = 'last_30_days';
        }

        $groups = $report->groups();
        $group = is_string($input['group'] ?? null) && array_key_exists($input['group'], $groups) ? $input['group'] : $report->defaultGroup();

        $filters = [];

        foreach ($report->filters() as $filter) {
            $value = $input[$filter->key] ?? null;

            if (is_string($value) && $filter->accepts($value)) {
                $filters[$filter->key] = $value;
            }
        }

        return ReportQuery::make(
            timezone: $this->tenancy->currentOrFail()->timezone,
            range: $range,
            from: $from,
            to: $to,
            bucket: is_string($input['bucket'] ?? null) ? ReportBucket::tryFrom($input['bucket']) : null,
            group: $group,
            filters: $filters,
        );
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $value : null;
    }
}
