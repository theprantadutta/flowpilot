<?php

namespace App\Workflows\Definition;

use App\Workflows\Fields\Field;
use App\Workflows\Triggers\Trigger;

/**
 * What a node's configuration is checked against: the workflow's trigger and
 * fields, the organization's members and currency, and the rest of the graph.
 */
final readonly class ValidationScope
{
    /**
     * Values every run has, available to messages.
     */
    public const array COMMON_VARIABLES = [
        ['path' => 'actor.name', 'label' => 'Started by (name)'],
        ['path' => 'workflow.name', 'label' => 'Workflow name'],
        ['path' => 'run.reference', 'label' => 'Run reference'],
        ['path' => 'run.url', 'label' => 'Link to the run'],
        ['path' => 'organization.name', 'label' => 'Organization name'],
    ];

    /**
     * Values runs about a record have, on top of the trigger's fields.
     */
    public const array SUBJECT_VARIABLES = [
        ['path' => 'subject.description', 'label' => 'Record description'],
        ['path' => 'subject.assignee_name', 'label' => 'Assignee name'],
        ['path' => 'subject.url', 'label' => 'Link to the record'],
    ];

    /**
     * What steps make available to the steps after them, by step type.
     */
    public const array STEP_VARIABLES = [
        'create_record' => [
            ['key' => 'reference', 'label' => 'reference'],
            ['key' => 'title', 'label' => 'title'],
            ['key' => 'url', 'label' => 'link'],
            ['key' => 'assignee', 'label' => 'assignee'],
        ],
        'assign' => [
            ['key' => 'assignee', 'label' => 'assignee'],
        ],
        'approval' => [
            ['key' => 'approval', 'label' => 'request reference'],
            ['key' => 'decided_by', 'label' => 'decided by'],
            ['key' => 'note', 'label' => 'decision note'],
        ],
    ];

    /**
     * @param  list<Field>  $fields
     * @param  list<int>  $memberIds  Active members of the organization.
     * @param  list<string>  $projectIds  The organization's projects.
     */
    public function __construct(
        public Trigger $trigger,
        public array $fields,
        public string $currency,
        public array $memberIds,
        public array $projectIds,
        public WorkflowDefinition $definition,
    ) {}

    public function hasSubject(): bool
    {
        return $this->trigger->subjectType() !== null;
    }

    public function subjectType(): ?string
    {
        return $this->trigger->subjectType();
    }

    /**
     * @return list<string>
     */
    public function personFields(): array
    {
        return array_values(array_map(
            fn (Field $field): string => $field->path,
            array_filter($this->fields, fn (Field $field): bool => $field->type === 'person'),
        ));
    }

    /**
     * Whether a message placeholder points at something runs of this workflow have.
     */
    public function knowsPath(string $path): bool
    {
        if (Field::find($this->fields, $path) !== null) {
            return true;
        }

        if (in_array($path, array_column(self::COMMON_VARIABLES, 'path'), true)) {
            return true;
        }

        if ($this->hasSubject() && in_array($path, array_column(self::SUBJECT_VARIABLES, 'path'), true)) {
            return true;
        }

        if (preg_match('/^steps\.([A-Za-z0-9_-]+)\.([a-z_]+)$/', $path, $match)) {
            $node = $this->definition->node($match[1]);

            return $node !== null
                && in_array($match[2], array_column(self::STEP_VARIABLES[$node['type']] ?? [], 'key'), true);
        }

        return false;
    }
}
