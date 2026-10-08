<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Workflows\Definition\ValidationScope;

/**
 * Finishes the run, optionally with a short summary of how it ended.
 *
 *   { summary: "Escalated to the operations lead" }
 */
class EndHandler extends BaseHandler
{
    public function type(): NodeType
    {
        return NodeType::End;
    }

    public function handles(array $config): array
    {
        return [];
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        $summary = self::text($config, 'summary');

        return mb_strlen($summary) > 300
            ? ['Keep the summary under 300 characters.']
            : self::templateErrors($summary, $scope, 'summary');
    }

    public function execute(StepContext $step): StepResult
    {
        $summary = $step->render(self::text($step->config(), 'summary'), 300);

        return StepResult::complete('end', $summary !== '' ? ['summary' => $summary] : []);
    }
}
