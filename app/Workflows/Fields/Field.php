<?php

namespace App\Workflows\Fields;

/**
 * Something a workflow can read: a trigger input, or a property of the record
 * that started it. Conditions compare fields; messages can include them.
 */
final readonly class Field
{
    public const array TYPES = ['text', 'number', 'money', 'select', 'date', 'boolean', 'person'];

    /**
     * @param  string  $path  Where the value lives in the run context, e.g. "subject.amount".
     * @param  list<array{value: string, label: string}>  $options  For select fields.
     */
    public function __construct(
        public string $path,
        public string $label,
        public string $type,
        public array $options = [],
    ) {}

    /**
     * @return array{path: string, label: string, type: string, options: list<array{value: string, label: string}>}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
        ];
    }

    /**
     * Value/label pairs from an enum's option list.
     *
     * @param  list<array{value: string, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    public static function optionsFrom(array $options): array
    {
        return array_map(fn (array $option): array => ['value' => $option['value'], 'label' => $option['label']], $options);
    }

    /**
     * @param  list<self>  $fields
     */
    public static function find(array $fields, string $path): ?self
    {
        foreach ($fields as $field) {
            if ($field->path === $path) {
                return $field;
            }
        }

        return null;
    }
}
