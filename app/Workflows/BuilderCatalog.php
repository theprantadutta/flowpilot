<?php

namespace App\Workflows;

use App\Enums\ConditionOperator;
use App\Enums\IssueSeverity;
use App\Enums\NodeType;
use App\Enums\Priority;
use App\Enums\Role;
use App\Support\FormOptions;
use App\Workflows\Definition\ValidationScope;
use App\Workflows\Fields\Field;
use App\Workflows\Nodes\ActionHandler;
use App\Workflows\Nodes\DelayHandler;
use App\Workflows\Nodes\UpdateRecordHandler;
use App\Workflows\Triggers\ManualTrigger;
use App\Workflows\Triggers\TriggerRegistry;

/**
 * Everything the visual builder offers: step types, triggers and their
 * fields, comparisons, people, projects and the values steps can use.
 */
class BuilderCatalog
{
    public function __construct(
        private readonly TriggerRegistry $triggers,
        private readonly FormOptions $options,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodeTypes' => array_values(array_filter(NodeType::catalog(), fn (array $type): bool => $type['value'] !== NodeType::Trigger->value)),
            'triggers' => $this->triggers->options(),
            'operators' => ConditionOperator::options(),
            'operatorsByType' => collect(Field::TYPES)->mapWithKeys(fn (string $type): array => [
                $type => array_map(fn (ConditionOperator $operator): string => $operator->value, ConditionOperator::forFieldType($type)),
            ])->all(),
            'inputTypes' => [
                ['value' => 'text', 'label' => 'Text'],
                ['value' => 'number', 'label' => 'Number'],
                ['value' => 'money', 'label' => 'Amount'],
                ['value' => 'date', 'label' => 'Date'],
                ['value' => 'select', 'label' => 'Choice'],
                ['value' => 'boolean', 'label' => 'Yes or no'],
                ['value' => 'person', 'label' => 'Member'],
            ],
            'maxInputs' => ManualTrigger::MAX_INPUTS,
            'roles' => array_map(fn (array $role): array => ['value' => $role['value'], 'label' => $role['label']], Role::options()),
            'members' => $this->options->members(),
            'projects' => $this->options->projects(),
            'priorities' => Field::optionsFrom(Priority::options()),
            'severities' => Field::optionsFrom(IssueSeverity::options()),
            'updateFields' => [
                'task' => $this->fields('task'),
                'issue' => $this->fields('issue'),
            ],
            'actions' => collect(ActionHandler::ACTIONS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'delayUnits' => array_map(fn (string $unit): array => ['value' => $unit, 'label' => ucfirst($unit)], array_keys(DelayHandler::UNITS)),
            'variables' => [
                'common' => ValidationScope::COMMON_VARIABLES,
                'subject' => ValidationScope::SUBJECT_VARIABLES,
                'steps' => ValidationScope::STEP_VARIABLES,
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string, options: list<array{value: string, label: string}>|null}>
     */
    private function fields(string $subject): array
    {
        $fields = [];

        foreach (UpdateRecordHandler::fieldsFor($subject) as $value => $field) {
            $fields[] = ['value' => $value, 'label' => $field['label'], 'options' => $field['options']];
        }

        return $fields;
    }
}
