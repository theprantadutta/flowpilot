<?php

namespace App\Http\Resources;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $unit = $this->relationLoaded('item') ? $this->item->unit : null;
        $change = $this->netChange();

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'type' => $this->type->toOption(),
            'quantity' => $this->quantity,
            'change' => $change,
            'change_label' => match (true) {
                $change > 0 => '+'.($unit?->quantity($change) ?? (string) $change),
                $change < 0 => '−'.($unit?->quantity(-$change) ?? (string) -$change),
                default => ($unit?->quantity($this->quantity) ?? (string) $this->quantity).' moved',
            },
            'stock_after' => $this->stock_after,
            'stock_after_label' => $unit?->quantity($this->stock_after) ?? (string) $this->stock_after,
            'item' => $this->whenLoaded('item', fn () => ['id' => $this->item->id, 'sku' => $this->item->sku, 'name' => $this->item->name]),
            'from' => $this->whenLoaded('fromLocation', fn () => $this->fromLocation?->name),
            'to' => $this->whenLoaded('toLocation', fn () => $this->toLocation?->name),
            'performer' => $this->whenLoaded('performer', fn () => $this->performer ? (new UserSummaryResource($this->performer))->resolve($request) : null),
            'reference_note' => $this->reference,
            'notes' => $this->notes,
            'purchase_request_id' => $this->purchase_request_id,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
