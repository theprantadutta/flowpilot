<?php

namespace App\Support\Reports;

/**
 * A column of a report's table, which is also a column of its export.
 *
 * Formats: text, number, percent (0–100), hours, days, money (minor units),
 * date ("Y-m-d"), datetime (ISO 8601), link ({label, url}) and status
 * (an enum option).
 */
final readonly class ReportColumn
{
    public function __construct(
        public string $key,
        public string $label,
        public string $format = 'text',
    ) {}

    public static function make(string $key, string $label, string $format = 'text'): self
    {
        return new self($key, $label, $format);
    }

    public function isNumeric(): bool
    {
        return in_array($this->format, ['number', 'percent', 'hours', 'days', 'duration', 'money'], true);
    }

    /**
     * @return array{key: string, label: string, format: string, numeric: bool}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'format' => $this->format, 'numeric' => $this->isNumeric()];
    }
}
