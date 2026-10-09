<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Approval;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Approval>
 */
class ApprovalFactory extends Factory
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
            'title' => fake()->randomElement(['Replacement conveyor belt', 'Safety boots for the night shift', 'Annual calibration contract', 'Forklift battery']),
            'description' => fake()->sentence(12),
            'status' => ApprovalStatus::Pending,
            'priority' => Priority::Medium,
            'approver_role' => Role::Finance,
            'due_at' => now()->addDays(2),
        ];
    }

    public function status(ApprovalStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'decided_at' => $status->isOpen() ? null : now(),
        ]);
    }

    public function amount(int $minorUnits, string $currency = 'USD'): static
    {
        return $this->state(fn (): array => ['amount' => $minorUnits, 'currency' => $currency]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => ['due_at' => now()->subHour()]);
    }
}
