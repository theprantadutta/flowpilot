<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'plan' => Plan::Business,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ];
    }
}
