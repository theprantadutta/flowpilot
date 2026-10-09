<?php

namespace Database\Factories;

use App\Models\InventoryLocation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['Main warehouse', 'Line 2 store', 'Maintenance cage', 'Goods in', 'Dispatch']).' '.fake()->unique()->numberBetween(1, 9999),
            'code' => strtoupper(fake()->bothify('LOC-##')),
        ];
    }
}
