<?php

namespace App\Actions\Inventory;

use App\Models\InventoryItem;
use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Tenancy\Tenancy;

class UpdateInventoryItem
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * Change an item's details. Stock itself only changes through movements.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(InventoryItem $item, User $actor, array $attributes): InventoryItem
    {
        $before = $this->snapshot($item);

        $item->fill($attributes);

        if ($item->isDirty('unit_cost_amount')) {
            $item->currency = $item->unit_cost_amount !== null ? $this->tenancy->currentOrFail()->currency : null;
        }

        // A new reorder point may put the item back above it.
        if ($item->isDirty('reorder_point') && $item->current_stock > $item->reorder_point) {
            $item->low_stock_at = null;
        }

        $item->save();

        $changes = ActivityLogger::diff($before, $this->snapshot($item));

        if ($changes !== []) {
            $this->activity->log(
                isset($changes['is_active']) && $changes['is_active']['to'] === false ? 'inventory.item_archived' : 'inventory.item_updated',
                $item,
                ['name' => $item->name, 'sku' => $item->sku, 'changes' => $changes],
                actor: $actor,
            );
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(InventoryItem $item): array
    {
        return [
            'sku' => $item->sku,
            'name' => $item->name,
            'description' => $item->description,
            'category_id' => $item->category_id,
            'supplier_id' => $item->supplier_id,
            'default_location_id' => $item->default_location_id,
            'unit' => $item->unit->value,
            'minimum_stock' => $item->minimum_stock,
            'reorder_point' => $item->reorder_point,
            'reorder_quantity' => $item->reorder_quantity,
            'unit_cost_amount' => $item->unit_cost_amount,
            'is_active' => $item->is_active,
        ];
    }
}
