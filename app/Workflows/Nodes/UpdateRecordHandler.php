<?php

namespace App\Workflows\Nodes;

use App\Actions\Issues\UpdateIssue;
use App\Actions\Tasks\UpdateTask;
use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\NodeType;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Task;
use App\Workflows\Definition\ValidationScope;

/**
 * Changes one field on the record that started the run.
 *
 *   { field: "status" | "priority" | "severity" | "due_in_days", value: "in_progress" }
 */
class UpdateRecordHandler extends BaseHandler
{
    public function __construct(
        private readonly UpdateTask $updateTask,
        private readonly UpdateIssue $updateIssue,
    ) {}

    public function type(): NodeType
    {
        return NodeType::UpdateRecord;
    }

    /**
     * The fields a workflow may change, by record type.
     *
     * @return array<string, array{label: string, options: list<array{value: string, label: string}>|null}>
     */
    public static function fieldsFor(?string $subjectType): array
    {
        $due = ['due_in_days' => ['label' => 'Due date (days from now)', 'options' => null]];

        return match ($subjectType) {
            'task' => [
                'status' => ['label' => 'Status', 'options' => self::options(TaskStatus::options())],
                'priority' => ['label' => 'Priority', 'options' => self::options(Priority::options())],
                ...$due,
            ],
            'issue' => [
                'status' => ['label' => 'Status', 'options' => self::options(IssueStatus::options())],
                'severity' => ['label' => 'Severity', 'options' => self::options(IssueSeverity::options())],
                ...$due,
            ],
            default => [],
        };
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        if (! $scope->hasSubject()) {
            return ['This trigger has no record to update. Use a trigger about a task or an issue.'];
        }

        $fields = self::fieldsFor($scope->subjectType());
        $field = is_string($config['field'] ?? null) ? $config['field'] : '';

        if (! isset($fields[$field])) {
            return ['Choose which field to change.'];
        }

        $value = $config['value'] ?? null;
        $options = $fields[$field]['options'];

        if ($options !== null) {
            return in_array($value, array_column($options, 'value'), true) ? [] : ["Choose the new {$fields[$field]['label']}."];
        }

        return is_numeric($value) && (int) $value >= 0 && (int) $value <= 365 ? [] : ['The due date must be between 0 and 365 days away.'];
    }

    public function execute(StepContext $step): StepResult
    {
        $config = $step->config();
        $subject = $step->subjectOrFail();
        $field = (string) ($config['field'] ?? '');
        $value = $config['value'] ?? null;

        $attributes = match ($field) {
            'due_in_days' => ['due_date' => now($step->organization->timezone)->addDays((int) $value)->toDateString()],
            'status' => ['status' => $subject instanceof Task ? TaskStatus::from((string) $value) : IssueStatus::from((string) $value)],
            'priority' => ['priority' => Priority::from((string) $value)],
            'severity' => ['severity' => IssueSeverity::from((string) $value)],
            default => throw StepFailed::permanent("The field \"{$field}\" cannot be changed by a workflow."),
        };

        if ($subject instanceof Task) {
            $this->updateTask->handle($subject, null, $attributes, $step->run);
        } elseif ($subject instanceof Issue) {
            $this->updateIssue->handle($subject, null, $attributes, $step->run);
        }

        $label = self::fieldsFor($step->run->subject_type)[$field]['label'] ?? $field;
        $display = collect(self::fieldsFor($step->run->subject_type)[$field]['options'] ?? [])->firstWhere('value', $value)['label'] ?? (string) $value;

        return StepResult::complete('next', [
            'record' => $subject->reference(),
            'field' => $label,
            'value' => $field === 'due_in_days' ? $attributes['due_date'] : $display,
        ]);
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $options): array
    {
        return array_map(fn (array $option): array => ['value' => $option['value'], 'label' => $option['label']], $options);
    }
}
