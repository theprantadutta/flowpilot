<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ProjectStatus;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '+1 week');

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Warehouse upgrade', 'Supplier onboarding', 'Quality audit', 'Line 3 retrofit', 'ERP migration']).' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(14),
            'status' => ProjectStatus::Active,
            'priority' => Priority::Medium,
            'start_date' => $start,
            'due_date' => (clone $start)->modify('+'.fake()->numberBetween(20, 120).' days'),
        ];
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
