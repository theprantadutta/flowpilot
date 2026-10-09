<?php

namespace Database\Factories;

use App\Enums\AiBriefStatus;
use App\Models\AiBrief;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiBrief>
 */
class AiBriefFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'status' => AiBriefStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => AiBriefStatus::Completed,
            'content' => ['headline' => 'Nothing needs your attention right now.', 'items' => [], 'actions' => []],
            'facts' => [],
            'provider' => 'freeway',
            'model' => 'paid:premium',
            'completed_at' => now(),
        ]);
    }
}
