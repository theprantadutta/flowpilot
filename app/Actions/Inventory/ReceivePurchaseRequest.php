<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\PurchaseRequestUpdatedNotification;
use App\Support\Activity\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goods arrived. For a stocked item the delivery is booked into stock as a
 * receipt linked to the request; deliveries can arrive in parts.
 */
class ReceivePurchaseRequest
{
    public function __construct(
        private readonly RecordMovement $recordMovement,
        private readonly ActivityLogger $activity,
    ) {}

    public function handle(PurchaseRequest $request, User $actor, int $quantity, ?string $locationId, ?string $idempotencyKey = null): PurchaseRequest
    {
        $request = DB::transaction(function () use ($request, $actor, $quantity, $locationId, $idempotencyKey): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = PurchaseRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::Ordered], true)) {
                throw ValidationException::withMessages(['quantity' => "{$locked->reference()} is {$locked->status->label()}, so nothing can be received against it."]);
            }

            $outstanding = $locked->quantity - $locked->received_quantity;

            if ($quantity < 1 || $quantity > $outstanding) {
                throw ValidationException::withMessages(['quantity' => "Receive between 1 and {$outstanding}."]);
            }

            if ($locked->inventory_item_id !== null) {
                $item = $locked->item;

                if ($item === null) {
                    throw ValidationException::withMessages(['quantity' => 'The stocked item on this request has been deleted.']);
                }

                $this->recordMovement->handle($item, $actor, MovementType::Receipt, [
                    'quantity' => $quantity,
                    'location_id' => $locationId ?? $locked->deliver_to_location_id ?? $item->default_location_id,
                    'unit_cost_amount' => $locked->unit_cost_amount,
                    'reference' => $locked->reference(),
                    'purchase_request_id' => $locked->id,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }

            $received = $locked->received_quantity + $quantity;
            $complete = $received >= $locked->quantity;

            $locked->forceFill([
                'received_quantity' => $received,
                'status' => $complete ? PurchaseRequestStatus::Received : PurchaseRequestStatus::Ordered,
                'ordered_at' => $locked->ordered_at ?? now(),
                'received_at' => $complete ? now() : null,
            ])->save();

            $this->activity->log($complete ? 'purchase_request.received' : 'purchase_request.partly_received', $locked, [
                'reference' => $locked->reference(),
                'title' => $locked->summary(),
                'quantity' => $quantity,
            ], actor: $actor);

            return $locked;
        });

        if ($request->status === PurchaseRequestStatus::Received && $request->requester_id !== null && $request->requester_id !== $actor->id) {
            $request->requester?->notify(new PurchaseRequestUpdatedNotification($request, $actor->name));
        }

        return $request;
    }
}
