<?php

namespace App\Workflows\Conditions;

/**
 * Whether a rule set matched, and how each rule compared, so a run can show
 * why it took the path it did.
 */
final readonly class ConditionResult
{
    /**
     * @param  list<array{field: string, operator: string, expected: string|null, actual: string|int|float|bool|null, passed: bool}>  $checks
     */
    public function __construct(
        public bool $passed,
        public array $checks,
    ) {}
}
