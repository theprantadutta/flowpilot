<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Workflows\Conditions\ConditionEvaluator;
use App\Workflows\Definition\ValidationScope;

/**
 * Picks the first case whose rules match, or "Otherwise".
 *
 *   { cases: [{ id: "large", label: "Large orders", match: "all", rules: [...] }, …] }
 */
class BranchHandler extends BaseHandler
{
    public const int MAX_CASES = 10;

    public function __construct(private readonly ConditionEvaluator $evaluator) {}

    public function type(): NodeType
    {
        return NodeType::Branch;
    }

    public function handles(array $config): array
    {
        return [...array_column(self::cases($config), 'id'), 'otherwise'];
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        $raw = is_array($config['cases'] ?? null) ? $config['cases'] : [];
        $cases = self::cases($config);
        $errors = [];

        if ($cases === []) {
            return ['Add at least one case.'];
        }

        if (count($raw) !== count($cases)) {
            $errors[] = 'Every case needs an id and a name.';
        }

        if (count($cases) > self::MAX_CASES) {
            $errors[] = 'Use at most '.self::MAX_CASES.' cases.';
        }

        $ids = array_column($cases, 'id');

        if (count($ids) !== count(array_unique($ids)) || in_array('otherwise', $ids, true)) {
            $errors[] = 'Each case needs its own id.';
        }

        foreach ($cases as $case) {
            foreach (ConditionEvaluator::validate($case, $scope->fields, $scope->currency) as $error) {
                $errors[] = "{$case['label']}: {$error}";
            }
        }

        return array_values(array_unique($errors));
    }

    public function execute(StepContext $step): StepResult
    {
        foreach (self::cases($step->config()) as $case) {
            $result = $this->evaluator->evaluate($case, $step->context, $step->fields, $step->organization->currency, $step->today());

            if ($result->passed) {
                return StepResult::complete($case['id'], ['case' => $case['label'], 'checks' => $result->checks]);
            }
        }

        return StepResult::complete('otherwise', ['case' => 'Otherwise']);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{id: string, label: string, match: string, rules: array<mixed>}>
     */
    public static function cases(array $config): array
    {
        $cases = [];

        foreach (is_array($config['cases'] ?? null) ? $config['cases'] : [] as $case) {
            if (! is_array($case) || ! is_string($case['id'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $case['id'])) {
                continue;
            }

            $label = is_string($case['label'] ?? null) ? trim($case['label']) : '';

            if ($label === '') {
                continue;
            }

            $cases[] = [
                'id' => $case['id'],
                'label' => mb_substr($label, 0, 60),
                'match' => ($case['match'] ?? 'all') === 'any' ? 'any' : 'all',
                'rules' => is_array($case['rules'] ?? null) ? $case['rules'] : [],
            ];
        }

        return $cases;
    }
}
