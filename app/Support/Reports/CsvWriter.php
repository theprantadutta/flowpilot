<?php

namespace App\Support\Reports;

use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Writes a report's table as CSV that spreadsheets open cleanly: UTF-8 with a
 * byte order mark, plain numbers, dates in the organization's timezone, and
 * text that cannot be mistaken for a formula.
 */
class CsvWriter
{
    /**
     * @param  resource  $stream
     * @param  list<ReportColumn>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     * @return int Rows written, not counting the header.
     */
    public function write($stream, array $columns, iterable $rows, string $timezone): int
    {
        fwrite($stream, "\u{FEFF}");
        fputcsv($stream, array_map(fn (ReportColumn $column): string => $this->text($column->label), $columns), escape: '');

        $count = 0;

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (ReportColumn $column): string => $this->cell($column, $row[$column->key] ?? null, $timezone), $columns), escape: '');
            $count++;
        }

        return $count;
    }

    public function cell(ReportColumn $column, mixed $value, string $timezone): string
    {
        if ($value === null) {
            return '';
        }

        return match ($column->format) {
            'link', 'status' => $this->text(is_array($value) ? (string) ($value['label'] ?? '') : (string) $value),
            'money' => is_array($value) && is_int($value['amount'] ?? null) && is_string($value['currency'] ?? null)
                ? Money::toDecimalString($value['amount'], $value['currency']).' '.$value['currency']
                : '',
            'datetime' => is_string($value) ? CarbonImmutable::parse($value)->setTimezone($timezone)->format('Y-m-d H:i') : '',
            'number', 'percent', 'hours', 'days', 'duration' => is_int($value) || is_float($value) ? (string) $value : '',
            default => $this->text(is_scalar($value) ? (string) $value : ''),
        };
    }

    /**
     * Spreadsheets run cells starting with = + - @ (or a tab or carriage
     * return before them) as formulas. A leading apostrophe keeps them text.
     */
    private function text(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
