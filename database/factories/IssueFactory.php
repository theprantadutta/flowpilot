<?php

namespace Database\Factories;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    private static int $number = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'number' => ++self::$number,
            'title' => fake()->randomElement(['Conveyor stops under load', 'Label printer misaligned', 'Forklift battery not charging', 'Wrong part numbers on invoice']),
            'description' => fake()->sentence(12),
            'severity' => IssueSeverity::Medium,
            'status' => IssueStatus::Open,
        ];
    }

    public function severity(IssueSeverity $severity): static
    {
        return $this->state(fn (): array => ['severity' => $severity]);
    }
}
