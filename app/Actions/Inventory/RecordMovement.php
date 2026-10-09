<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Notifications\InventoryLowNotification;
use App\Support\Activity\ActivityLogger;
use App\Support\Inventory\InventoryManagers;
use App\Support\Tenancy\OrganizationSequence;
use App\Support\Tenancy\Tenancy;
use App\Workflows\Engine\WorkflowTriggers;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The only way stock changes. Receipts add to a location, issues take from
 * one, transfers move between two, and counts set a location to what was
 * counted. Rows are locked while changing, stock never goes below zero, and
 * the same submission recorded twice produces one movement.
 *
 * When an item drops to its reorder point, inventory managers are told and
 * workflows listening for low stock start.
 */
class RecordMovement
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly OrganizationSequence $sequence,
        private readonly WorkflowTriggers $triggers,
        private readonly InventoryManagers $managers,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * @param  array{
     *     quantity?: int,
     *     counted?: int,
     *     location_id?: string,
     *     from_location_id?: string,
     *     to_location_id?: string,
     *     unit_cost_amount?: int|null,
     *     reference?: string|null,
     *     notes?: string|null,
     *     purchase_request_id?: string|null,
     *     idempotency_key?: string|null,
     * }  $data
     *
     * @throws ValidationException When there is not enough stock, or a count changes nothing.
     */
    public function handle(InventoryItem $item, ?User $actor, MovementType $type, array $data, ?WorkflowRun $automation = null): InventoryMovement
    {
        $key = $data['idempotency_key'] ?? null;

        if ($key !== null && ($existing = $this->existing($key))) {
            return $existing;
        }

        try {
            [$movement, $droppedLow] = DB::transaction(fn (): array => $this->record($item, $actor, $type, $data, $automation));
        } catch (UniqueConstraintViolationException $exception) {
            if ($key !== null && ($existing = $this->existing($key))) {
                return $existing;
            }

            throw $exception;
        }

        $item->refresh();

        if ($droppedLow) {
            $this->triggers->fire('inventory.low_stock', $item, $actor, $automation, occurrence: $movement->id);

            $recipients = $this->managers->for($this->tenancy->currentOrFail());

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new InventoryLowNotification($item));
            }
        }

        return $movement;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: InventoryMovement, 1: bool}
     */
    private function record(InventoryItem $item, ?User $actor, MovementType $type, array $data, ?WorkflowRun $automation): array
    {
        /** @var InventoryItem $locked */
        $locked = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
        $before = $locked->current_stock;

        [$from, $to, $quantity] = $this->plan($locked, $type, $data);

        if ($from !== null) {
            $level = $this->level($locked, $from);

            if ($level->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$locked->unit->quantity($level->quantity)} at this location.",
                ]);
            }

            $level->forceFill(['quantity' => $level->quantity - $quantity])->save();
        }

        if ($to !== null) {
            $level = $this->level($locked, $to);
            $level->forceFill(['quantity' => $level->quantity + $quantity])->save();
        }

        $after = (int) InventoryStockLevel::query()->where('inventory_item_id', $locked->id)->sum('quantity');

        $movement = InventoryMovement::query()->create([
            'number' => $this->sequence->next('inventory_movements'),
            'inventory_item_id' => $locked->id,
            'type' => $type,
            'quantity' => $quantity,
            'from_location_id' => $from,
            'to_location_id' => $to,
            'stock_after' => $after,
            'unit_cost_amount' => $type === MovementType::Receipt ? ($data['unit_cost_amount'] ?? $locked->unit_cost_amount) : null,
            'currency' => $type === MovementType::Receipt ? $this->tenancy->currentOrFail()->currency : null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'purchase_request_id' => $data['purchase_request_id'] ?? null,
            'performed_by' => $actor?->id,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'occurred_at' => now(),
        ]);

        // Dropping to the reorder point is news once; climbing back above it resets that.
        $droppedLow = $after <= $locked->reorder_point && $locked->low_stock_at === null && $after < $before;

        $locked->forceFill([
            'current_stock' => $after,
            'last_movement_at' => now(),
            'low_stock_at' => match (true) {
                $droppedLow => now(),
                $after > $locked->reorder_point => null,
                default => $locked->low_stock_at,
            },
        ])->save();

        $this->activity->log('inventory.'.$type->value, $locked, [
            'name' => $locked->name,
            'reference' => $movement->reference(),
            'sku' => $locked->sku,
            'quantity' => $locked->unit->quantity($quantity),
            'stock_after' => $locked->unit->quantity($after),
            ...ActivityLogger::automation($automation),
        ], actor: $actor, actorType: $automation ? 'workflow' : 'user');

        return [$movement, $droppedLow];
    }

    /**
     * Which location loses stock, which gains it, and by how much.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string|null, 1: string|null, 2: int}
     */
    private function plan(InventoryItem $item, MovementType $type, array $data): array
    {
        $location = fn (string $key): string => $this->locationId($data[$key] ?? null, $key);

        return match ($type) {
            MovementType::Receipt => [null, $location('location_id'), $this->positive($data['quantity'] ?? null)],
            MovementType::Issue => [$location('location_id'), null, $this->positive($data['quantity'] ?? null)],
            MovementType::Transfer => $this->transfer($location('from_location_id'), $location('to_location_id'), $this->positive($data['quantity'] ?? null)),
            MovementType::Adjustment => $this->count($item, $location('location_id'), $data['counted'] ?? null),
        };
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function transfer(string $from, string $to, int $quantity): array
    {
        if ($from === $to) {
            throw ValidationException::withMessages(['to_location_id' => 'Choose a different location to move the stock to.']);
        }

        return [$from, $to, $quantity];
    }

    /**
     * A stock count sets the location to what was counted.
     *
     * @return array{0: string|null, 1: string|null, 2: int}
     */
    private function count(InventoryItem $item, string $location, mixed $counted): array
    {
        if (! is_int($counted) || $counted < 0) {
            throw ValidationException::withMessages(['counted' => 'Enter how many you counted.']);
        }

        $current = $this->level($item, $location)->quantity;
        $difference = $counted - $current;

        if ($difference === 0) {
            throw ValidationException::withMessages(['counted' => 'That matches what is recorded, so there is nothing to change.']);
        }

        return $difference > 0 ? [null, $location, $difference] : [$location, null, -$difference];
    }

    private function positive(mixed $quantity): int
    {
        if (! is_int($quantity) || $quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Enter a quantity of at least 1.']);
        }

        return $quantity;
    }

    private function locationId(mixed $id, string $field): string
    {
        if (! is_string($id) || ! InventoryLocation::query()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([$field => 'Choose a location from this organization.']);
        }

        return $id;
    }

    /**
     * The stock level row for an item at a location, created empty if new, locked for the transaction.
     */
    private function level(InventoryItem $item, string $location): InventoryStockLevel
    {
        InventoryStockLevel::query()->firstOrCreate(
            ['inventory_item_id' => $item->id, 'inventory_location_id' => $location],
            ['quantity' => 0],
        );

        /** @var InventoryStockLevel */
        return InventoryStockLevel::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $location)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function existing(string $key): ?InventoryMovement
    {
        return InventoryMovement::query()->where('idempotency_key', $key)->first();
    }
}
