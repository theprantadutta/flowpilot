<?php

namespace App\Workflows\Nodes;

use Carbon\CarbonImmutable;

/**
 * What a step did: finished and chose a path, or is waiting for time to pass
 * or for someone to act.
 */
final readonly class StepResult
{
    /**
     * @param  array<string, mixed>  $output
     */
    private function __construct(
        public bool $finished,
        public string $outcome,
        public array $output,
        public ?CarbonImmutable $resumeAt = null,
        public ?string $waitingOnType = null,
        public ?string $waitingOnId = null,
    ) {}

    /**
     * @param  string  $outcome  The handle to leave through (next, true, false, a branch case…).
     * @param  array<string, mixed>  $output  Shown on the run and readable by later steps.
     */
    public static function complete(string $outcome = 'next', array $output = []): self
    {
        return new self(true, $outcome, $output);
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public static function wait(array $output = [], ?CarbonImmutable $resumeAt = null, ?string $waitingOnType = null, ?string $waitingOnId = null): self
    {
        return new self(false, '', $output, $resumeAt, $waitingOnType, $waitingOnId);
    }
}
