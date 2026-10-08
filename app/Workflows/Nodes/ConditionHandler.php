<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Workflows\Conditions\ConditionEvaluator;
use App\Workflows\Definition\ValidationScope;

/**
 * Goes down the "Yes" path when the rules match and "No" when they do not.
 */
class ConditionHandler extends BaseHandler
{
    public function __construct(private readonly ConditionEvaluator $evaluator) {}

    public function type(): NodeType
    {
        return NodeType::Condition;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        return ConditionEvaluator::validate($config, $scope->fields, $scope->currency);
    }

    public function execute(StepContext $step): StepResult
    {
        $result = $this->evaluator->evaluate(
            $step->config(),
            $step->context,
            $step->fields,
            $step->organization->currency,
            $step->today(),
        );

        return StepResult::complete($result->passed ? 'true' : 'false', [
            'matched' => $result->passed,
            'checks' => $result->checks,
        ]);
    }
}
