<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestStatus;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    private static int $number = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 40);
        $unitCost = fake()->numberBetween(500, 50000);

        return [
            'organization_id' => Organization::factory(),
            'number' => ++self::$number,
            'status' => PurchaseRequestStatus::Submitted,
            'item_name' => fake()->randomElement(['Replacement drive belt', 'Label printer ribbons', 'Torque wrench']),
            'quantity' => $quantity,
            'unit_cost_amount' => $unitCost,
            'total_amount' => $quantity * $unitCost,
            'currency' => 'USD',
        ];
    }

    public function status(PurchaseRequestStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
