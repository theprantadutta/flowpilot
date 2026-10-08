<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Workflows\Definition\ValidationScope;

/**
 * The first step of every run: records what started it.
 */
class TriggerHandler extends BaseHandler
{
    public function type(): NodeType
    {
        return NodeType::Trigger;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        return $scope->trigger->validate($config);
    }

    public function execute(StepContext $step): StepResult
    {
        return StepResult::complete('next', array_filter([
            'trigger' => $step->trigger->label(),
            'started_by' => data_get($step->context, 'actor.name'),
            'record' => $step->run->subject_label,
        ], fn (mixed $value): bool => $value !== null));
    }
}
