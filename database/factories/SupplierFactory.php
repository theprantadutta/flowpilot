<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->randomElement(['Kestrel Industrial Supply', 'Harbour Fasteners', 'Atlas Safety Gear', 'Meridian Packaging', 'Northline Electrical', 'Sterling Hydraulics']).' '.fake()->unique()->numberBetween(1, 9999),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'lead_time_days' => fake()->numberBetween(2, 14),
        ];
    }
}
