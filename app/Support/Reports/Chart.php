<?php

namespace App\Support\Reports;

/**
 * A chart in a report: the form, the categories along it and the series.
 *
 * Series colours are either categorical slots ("chart-1" to "chart-5") for
 * identity, or status roles ("success", "danger", "warning", "flow",
 * "neutral") when the colour means an outcome.
 */
final class Chart
{
    private ?string $description = null;

    private string $format = 'number';

    private bool $stacked = false;

    /**
     * @var list<string>
     */
    private array $labels = [];

    /**
     * @var list<array{key: string, label: string, color: string, values: list<int|float|null>}>
     */
    private array $series = [];

    /**
     * @param  'columns'|'line'|'bars'  $kind
     */
    private function __construct(
        private readonly string $key,
        private readonly string $title,
        private readonly string $kind,
    ) {}

    /**
     * Vertical columns along a timeline; stack them for parts of a whole.
     */
    public static function columns(string $key, string $title): self
    {
        return new self($key, $title, 'columns');
    }

    /**
     * Lines along a timeline, for rates and averages.
     */
    public static function line(string $key, string $title): self
    {
        return new self($key, $title, 'line');
    }

    /**
     * Horizontal bars comparing named categories.
     */
    public static function bars(string $key, string $title): self
    {
        return new self($key, $title, 'bars');
    }

    public function describe(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * How values read: number, percent, hours, money (minor units) or duration (seconds).
     */
    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function stacked(bool $stacked = true): self
    {
        $this->stacked = $stacked;

        return $this;
    }

    /**
     * @param  array<int, string>  $labels
     */
    public function labels(array $labels): self
    {
        $this->labels = array_values($labels);

        return $this;
    }

    /**
     * @param  array<int, int|float|null>  $values  One per label, in label order.
     */
    public function series(string $key, string $label, string $color, array $values): self
    {
        $this->series[] = [
            'key' => $key,
            'label' => $label,
            'color' => $color,
            'values' => array_values(array_map(fn (int|float|null $value): int|float|null => is_float($value) ? round($value, 2) : $value, $values)),
        ];

        return $this;
    }

    public function isEmpty(): bool
    {
        foreach ($this->series as $series) {
            foreach ($series['values'] as $value) {
                if ($value !== null && $value != 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array{key: string, title: string, description: string|null, kind: string, format: string, stacked: bool, labels: list<string>, series: list<array{key: string, label: string, color: string, values: list<int|float|null>}>, empty: bool}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'kind' => $this->kind,
            'format' => $this->format,
            'stacked' => $this->stacked,
            'labels' => $this->labels,
            'series' => $this->series,
            'empty' => $this->isEmpty(),
        ];
    }
}
