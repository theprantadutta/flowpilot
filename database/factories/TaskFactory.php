<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'title' => fake()->randomElement(['Confirm delivery window', 'Update safety checklist', 'Review supplier quote', 'Calibrate sensors', 'Draft handover notes']),
            'description' => fake()->sentence(10),
            'status' => TaskStatus::Todo,
            'priority' => Priority::Medium,
            'position' => self::$number * 1024,
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'completed_at' => $status->isDone() ? now() : null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => ['due_date' => now()->subDays(2)->toDateString()]);
    }
}
