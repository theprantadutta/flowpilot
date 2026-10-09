<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

class CreateInventoryItem
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly RecordMovement $recordMovement,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * Add an item, optionally with the stock already on the shelf. Opening
     * stock is booked as a receipt so the ledger explains every unit.
     *
     * @param  array<string, mixed>  $attributes  Validated item attributes.
     */
    public function handle(User $actor, array $attributes, int $openingStock = 0, ?string $openingLocationId = null): InventoryItem
    {
        return DB::transaction(function () use ($actor, $attributes, $openingStock, $openingLocationId): InventoryItem {
            $item = InventoryItem::query()->create([
                ...$attributes,
                'currency' => isset($attributes['unit_cost_amount']) ? $this->tenancy->currentOrFail()->currency : null,
                'current_stock' => 0,
            ]);

            $this->activity->log('inventory.item_created', $item, [
                'name' => $item->name,
                'sku' => $item->sku,
            ], actor: $actor);

            $location = $openingLocationId ?? $item->default_location_id;

            if ($openingStock > 0 && $location !== null) {
                $this->recordMovement->handle($item, $actor, MovementType::Receipt, [
                    'quantity' => $openingStock,
                    'location_id' => $location,
                    'reference' => 'Opening stock',
                ]);
            }

            return $item->refresh();
        });
    }
}
