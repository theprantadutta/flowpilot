<?php

namespace Database\Factories;

use App\Enums\InventoryUnit;
use App\Models\InventoryItem;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Items start with no stock: stock only arrives through movements.
 *
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'name' => fake()->randomElement(['Nitrile gloves (box of 100)', 'M10 hex bolts', 'Hydraulic oil 20 l', 'Safety glasses', 'Pallet wrap roll', 'Conveyor belt splice kit']),
            'unit' => InventoryUnit::Each,
            'minimum_stock' => 5,
            'reorder_point' => 10,
            'reorder_quantity' => 50,
            'unit_cost_amount' => 1250,
            'currency' => 'USD',
        ];
    }
}
