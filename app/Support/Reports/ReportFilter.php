<?php

namespace App\Support\Reports;

/**
 * A dimension a report can be narrowed by, e.g. one project or one assignee.
 * Only values from the options are accepted, so an id from another
 * organization never reaches a query.
 */
final readonly class ReportFilter
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $anyLabel,
        public array $options,
    ) {}

    public function accepts(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        foreach ($this->options as $option) {
            if ($option['value'] === $value) {
                return true;
            }
        }

        return false;
    }

    public function labelFor(string $value): ?string
    {
        foreach ($this->options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, any_label: string, options: list<array{value: string, label: string}>}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'any_label' => $this->anyLabel, 'options' => $this->options];
    }
}
