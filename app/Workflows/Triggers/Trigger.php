<?php

namespace App\Workflows\Triggers;

use App\Workflows\Fields\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Something that starts a workflow: a person pressing "Start", or an event on
 * a record. A trigger describes the fields a run can read and takes the
 * snapshot of its record that the run works from.
 */
abstract class Trigger
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /**
     * Morph alias of the record this trigger is about (task, issue…), or null
     * when runs are started by hand without one.
     */
    public function subjectType(): ?string
    {
        return null;
    }

    /**
     * Fields specific to this trigger, before the ones every run has.
     *
     * @param  array<string, mixed>  $config  The trigger node's configuration.
     * @return list<Field>
     */
    abstract protected function ownFields(array $config): array;

    /**
     * Everything a condition can test and a message can include.
     *
     * @param  array<string, mixed>  $config
     * @return list<Field>
     */
    public function fields(array $config = []): array
    {
        return [
            ...$this->ownFields($config),
            new Field('actor.id', 'Started by', 'person'),
        ];
    }

    /**
     * Problems with the trigger node's configuration.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validate(array $config): array
    {
        return [];
    }

    /**
     * The record's values at the moment the run started. Runs read this
     * snapshot, so later edits to the record do not change a run's path.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Model $subject): array
    {
        return [];
    }

    public function subjectLabel(Model $subject): ?string
    {
        return null;
    }

    /**
     * @return array{value: string, label: string, description: string, subject: string|null, fields: list<array{path: string, label: string, type: string, options: list<array{value: string, label: string}>}>}
     */
    public function toOption(): array
    {
        return [
            'value' => $this->key(),
            'label' => $this->label(),
            'description' => $this->description(),
            'subject' => $this->subjectType(),
            'fields' => array_map(fn (Field $field): array => $field->toArray(), $this->fields()),
        ];
    }
}
