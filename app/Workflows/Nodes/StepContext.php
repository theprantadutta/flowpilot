<?php

namespace App\Workflows\Nodes;

use App\Models\Issue;
use App\Models\Organization;
use App\Models\Task;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Workflows\Definition\WorkflowDefinition;
use App\Workflows\Fields\Field;
use App\Workflows\Support\TemplateRenderer;
use App\Workflows\Triggers\Trigger;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Everything a step handler needs while it runs.
 */
final class StepContext
{
    private Task|Issue|false|null $subject = false;

    /**
     * @param  array{id: string, type: string, position: array{x: float|int, y: float|int}, data: array{label: string, config: array<string, mixed>}}  $node
     * @param  array<string, mixed>  $context  The run context: input, subject snapshot, earlier step outputs.
     * @param  list<Field>  $fields
     */
    public function __construct(
        public readonly Organization $organization,
        public readonly WorkflowRun $run,
        public readonly WorkflowStepRun $step,
        public readonly array $node,
        public readonly WorkflowDefinition $definition,
        public readonly Trigger $trigger,
        public readonly array $context,
        public readonly array $fields,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->node['data']['config'];
    }

    public function label(): string
    {
        return $this->node['data']['label'];
    }

    public function render(string $template, int $limit = 5000): string
    {
        return trim($this->renderer->render($template, $this->context, $this->fields, $this->organization, $limit));
    }

    /**
     * The organization's date today, for comparisons and due dates.
     */
    public function today(): string
    {
        return now($this->organization->timezone)->toDateString();
    }

    /**
     * The live record the run is about, or null when it has none or it was deleted.
     */
    public function subject(): Task|Issue|null
    {
        if ($this->subject !== false) {
            return $this->subject;
        }

        $class = $this->run->subject_type ? Relation::getMorphedModel($this->run->subject_type) : null;

        $subject = match ($class) {
            Task::class => Task::query()->find($this->run->subject_id),
            Issue::class => Issue::query()->find($this->run->subject_id),
            default => null,
        };

        return $this->subject = $subject;
    }

    /**
     * The live record, failing the step when it is gone.
     */
    public function subjectOrFail(): Task|Issue
    {
        return $this->subject() ?? throw StepFailed::permanent('The record this run is about has been deleted.');
    }

    public function workflowName(): string
    {
        return (string) data_get($this->context, 'workflow.name', 'A workflow');
    }
}
