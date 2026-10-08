<?php

namespace App\Workflows\Nodes;

use App\Enums\NodeType;
use App\Workflows\Definition\ValidationScope;
use Carbon\CarbonImmutable;

/**
 * Waits for a set time. The run is parked and picked up again by the
 * scheduler once the time has passed.
 *
 *   { amount: 2, unit: "days" }
 */
class DelayHandler extends BaseHandler
{
    public const array UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    public const int MAX_MINUTES = 90 * 1440;

    public function type(): NodeType
    {
        return NodeType::Delay;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        $amount = $config['amount'] ?? null;
        $unit = $config['unit'] ?? null;

        if (! is_int($amount) && ! (is_string($amount) && ctype_digit($amount))) {
            return ['Enter how long to wait.'];
        }

        if (! is_string($unit) || ! isset(self::UNITS[$unit])) {
            return ['Choose minutes, hours or days.'];
        }

        $minutes = (int) $amount * self::UNITS[$unit];

        return match (true) {
            $minutes < 1 => ['Wait at least one minute.'],
            $minutes > self::MAX_MINUTES => ['Waits can be at most 90 days.'],
            default => [],
        };
    }

    public function execute(StepContext $step): StepResult
    {
        if ($step->step->resume_at !== null) {
            return StepResult::complete('next', ['waited_until' => $step->step->resume_at->toIso8601String()]);
        }

        $config = $step->config();
        $minutes = (int) ($config['amount'] ?? 0) * (self::UNITS[(string) ($config['unit'] ?? 'minutes')] ?? 1);
        $until = CarbonImmutable::now()->addMinutes(max(1, min($minutes, self::MAX_MINUTES)));

        return StepResult::wait(['until' => $until->toIso8601String()], resumeAt: $until);
    }
}
