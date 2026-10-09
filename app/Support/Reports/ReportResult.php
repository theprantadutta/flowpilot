<?php

namespace App\Support\Reports;

/**
 * The headline figures and charts of a report. The table is built
 * separately, row by row, so the same rows feed the page and the export.
 */
final class ReportResult
{
    /**
     * @var list<array{key: string, label: string, value: int|float|string|null, format: string, hint: string|null, tone: string|null}>
     */
    private array $tiles = [];

    /**
     * @var list<Chart>
     */
    private array $charts = [];

    /**
     * @param  string|null  $tone  success, warning or danger when the figure is a judgement; null for a plain count.
     */
    public function tile(string $key, string $label, int|float|string|null $value, string $format = 'number', ?string $hint = null, ?string $tone = null): self
    {
        $this->tiles[] = [
            'key' => $key,
            'label' => $label,
            'value' => is_float($value) ? round($value, 1) : $value,
            'format' => $format,
            'hint' => $hint,
            'tone' => $tone,
        ];

        return $this;
    }

    public function chart(Chart $chart): self
    {
        $this->charts[] = $chart;

        return $this;
    }

    /**
     * @return list<array{key: string, label: string, value: int|float|string|null, format: string, hint: string|null, tone: string|null}>
     */
    public function tiles(): array
    {
        return $this->tiles;
    }

    /**
     * @return array{tiles: list<array{key: string, label: string, value: int|float|string|null, format: string, hint: string|null, tone: string|null}>, charts: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'tiles' => $this->tiles,
            'charts' => array_map(fn (Chart $chart): array => $chart->toArray(), $this->charts),
        ];
    }
}
