<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of the stock ledger. Never edited: a mistake is corrected with
 * another movement, so the history always adds up.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $number
 * @property string $inventory_item_id
 * @property MovementType $type
 * @property int $quantity
 * @property string|null $from_location_id
 * @property string|null $to_location_id
 * @property int $stock_after
 * @property int|null $unit_cost_amount
 * @property string|null $currency
 * @property string|null $reference
 * @property string|null $notes
 * @property string|null $purchase_request_id
 * @property int|null $performed_by
 * @property string|null $idempotency_key
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read InventoryItem $item
 * @property-read InventoryLocation|null $fromLocation
 * @property-read InventoryLocation|null $toLocation
 * @property-read User|null $performer
 * @property-read PurchaseRequest|null $purchaseRequest
 */
#[Fillable([
    'number', 'inventory_item_id', 'type', 'quantity', 'from_location_id', 'to_location_id', 'stock_after',
    'unit_cost_amount', 'currency', 'reference', 'notes', 'purchase_request_id', 'performed_by',
    'idempotency_key', 'occurred_at',
])]
class InventoryMovement extends Model
{
    use BelongsToOrganization, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'from_location_id' => null,
        'to_location_id' => null,
        'unit_cost_amount' => null,
        'currency' => null,
        'reference' => null,
        'notes' => null,
        'purchase_request_id' => null,
        'performed_by' => null,
        'idempotency_key' => null,
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Stock movements cannot be changed. Record a correcting movement instead.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'number' => 'integer',
            'quantity' => 'integer',
            'stock_after' => 'integer',
            'unit_cost_amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'M-'.$this->number;
    }

    /**
     * The change to the item's total stock: receipts add, issues take away,
     * transfers change nothing overall.
     */
    public function netChange(): int
    {
        return match ($this->type) {
            MovementType::Receipt => $this->quantity,
            MovementType::Issue => -$this->quantity,
            MovementType::Transfer => 0,
            MovementType::Adjustment => $this->to_location_id !== null ? $this->quantity : -$this->quantity,
        };
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }
}
