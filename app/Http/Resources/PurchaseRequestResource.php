<?php

namespace App\Http\Resources;

use App\Models\PurchaseRequest;
use App\Support\Money;
use App\Support\Tenancy\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseRequest
 */
class PurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app(Tenancy::class)->current()->locale ?? 'en';

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'summary' => $this->summary(),
            'status' => $this->status->toOption(),
            'is_open' => $this->status->isOpen(),
            'item_name' => $this->item_name,
            'item' => $this->whenLoaded('item', fn () => $this->item ? ['id' => $this->item->id, 'sku' => $this->item->sku, 'name' => $this->item->name, 'stock_label' => $this->item->unit->quantity($this->item->current_stock)] : null),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'name' => $this->supplier->name] : null),
            'deliver_to' => $this->whenLoaded('deliverTo', fn () => $this->deliverTo ? ['id' => $this->deliverTo->id, 'name' => $this->deliverTo->name] : null),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester ? (new UserSummaryResource($this->requester))->resolve($request) : null),
            'decider' => $this->whenLoaded('decider', fn () => $this->decider ? (new UserSummaryResource($this->decider))->resolve($request) : null),
            'quantity' => $this->quantity,
            'received_quantity' => $this->received_quantity,
            'unit_cost' => Money::format($this->unit_cost_amount, $this->currency, $locale),
            'total' => Money::format($this->total_amount, $this->currency, $locale),
            'needed_by' => $this->needed_by?->toDateString(),
            'reason' => $this->reason,
            'supplier_reference' => $this->supplier_reference,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'ordered_at' => $this->ordered_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
