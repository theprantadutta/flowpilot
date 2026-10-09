<?php

namespace App\Workflows\Nodes;

use App\Actions\Issues\UpdateIssue;
use App\Actions\Tasks\UpdateTask;
use App\Enums\NodeType;
use App\Models\Issue;
use App\Models\Task;
use App\Workflows\Definition\ValidationScope;
use App\Workflows\Support\People;

/**
 * Assigns the record to a person. Assigning to a role picks the member of
 * that role with the least open work.
 *
 *   { assignee: { type: "role", role: "operations" } }
 */
class AssignHandler extends BaseHandler
{
    public function __construct(
        private readonly People $people,
        private readonly UpdateTask $updateTask,
        private readonly UpdateIssue $updateIssue,
    ) {}

    public function type(): NodeType
    {
        return NodeType::Assign;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        if (! $scope->subjectIsWork()) {
            return ['Only tasks and issues can be assigned. Use a trigger about a task or an issue.'];
        }

        if (! is_array($config['assignee'] ?? null)) {
            return ['Choose who to assign it to.'];
        }

        return People::validate([$config['assignee']], $scope->memberIds, $scope->personFields(), true, 'assignees');
    }

    public function execute(StepContext $step): StepResult
    {
        $subject = $step->subjectOrFail();

        if (! $subject instanceof Task && ! $subject instanceof Issue) {
            throw StepFailed::permanent('Only tasks and issues can be assigned.');
        }

        $assignee = $this->people->pickOne($step->organization, $step->config()['assignee'] ?? null, $step->context);

        if ($assignee === null) {
            throw StepFailed::permanent('Nobody could be assigned: no active member matches the choice.');
        }

        if ($subject->assignee_id !== $assignee->id) {
            $subject instanceof Task
                ? $this->updateTask->handle($subject, null, ['assignee_id' => $assignee->id], $step->run)
                : $this->updateIssue->handle($subject, null, ['assignee_id' => $assignee->id], $step->run);
        }

        return StepResult::complete('next', [
            'record' => $subject->reference(),
            'assignee' => $assignee->name,
        ]);
    }
}
