<?php

namespace App\Http\Resources;

use App\Models\InventoryItem;
use App\Models\InventoryStockLevel;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryItem
 */
class InventoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app(Tenancy::class)->current()->locale ?? 'en';

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'unit' => ['value' => $this->unit->value, 'label' => $this->unit->label()],
            'current_stock' => $this->current_stock,
            'stock_label' => $this->unit->quantity($this->current_stock),
            'minimum_stock' => $this->minimum_stock,
            'reorder_point' => $this->reorder_point,
            'reorder_quantity' => $this->reorder_quantity,
            'status' => $this->stockStatus()->toOption(),
            'unit_cost' => $this->unit_cost_amount !== null && $this->currency !== null ? [
                'minor' => $this->unit_cost_amount,
                'formatted' => Money::format($this->unit_cost_amount, $this->currency, $locale),
                'input' => Money::toDecimalString($this->unit_cost_amount, $this->currency),
            ] : null,
            'stock_value' => $this->unit_cost_amount !== null && $this->currency !== null
                ? Money::format($this->unit_cost_amount * $this->current_stock, $this->currency, $locale)
                : null,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'name' => $this->supplier->name] : null),
            'default_location' => $this->whenLoaded('defaultLocation', fn () => $this->defaultLocation ? ['id' => $this->defaultLocation->id, 'name' => $this->defaultLocation->name] : null),
            'stock_levels' => $this->whenLoaded('stockLevels', fn () => $this->stockLevels
                ->filter(fn (InventoryStockLevel $level): bool => $level->quantity > 0)
                ->sortByDesc('quantity')
                ->map(fn (InventoryStockLevel $level): array => [
                    'location' => ['id' => $level->inventory_location_id, 'name' => $level->location->name],
                    'quantity' => $level->quantity,
                    'label' => $this->unit->quantity($level->quantity),
                ])
                ->values()
                ->all()),
            'low_stock_at' => $this->low_stock_at?->toIso8601String(),
            'last_movement_at' => $this->last_movement_at?->toIso8601String(),
        ];
    }
}
