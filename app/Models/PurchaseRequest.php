<?php

namespace App\Models;

use App\Enums\PurchaseRequestStatus;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A request to buy something, approved through a workflow, then ordered and
 * received into stock.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $number
 * @property PurchaseRequestStatus $status
 * @property int|null $requester_id
 * @property string|null $inventory_item_id
 * @property string $item_name
 * @property string|null $supplier_id
 * @property string|null $deliver_to_location_id
 * @property int $quantity
 * @property int $unit_cost_amount
 * @property int $total_amount
 * @property string $currency
 * @property CarbonImmutable|null $needed_by
 * @property string|null $reason
 * @property string|null $supplier_reference
 * @property int $received_quantity
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $ordered_at
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $idempotency_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $requester
 * @property-read InventoryItem|null $item
 * @property-read Supplier|null $supplier
 * @property-read InventoryLocation|null $deliverTo
 * @property-read User|null $decider
 */
#[Fillable([
    'number', 'status', 'requester_id', 'inventory_item_id', 'item_name', 'supplier_id', 'deliver_to_location_id',
    'quantity', 'unit_cost_amount', 'total_amount', 'currency', 'needed_by', 'reason', 'supplier_reference',
    'received_quantity', 'decided_by', 'decided_at', 'ordered_at', 'received_at', 'cancelled_at', 'idempotency_key',
])]
class PurchaseRequest extends Model
{
    /** @use HasFactory<PurchaseRequestFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'submitted',
        'requester_id' => null,
        'inventory_item_id' => null,
        'supplier_id' => null,
        'deliver_to_location_id' => null,
        'needed_by' => null,
        'reason' => null,
        'supplier_reference' => null,
        'received_quantity' => 0,
        'decided_by' => null,
        'decided_at' => null,
        'ordered_at' => null,
        'received_at' => null,
        'cancelled_at' => null,
        'idempotency_key' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'number' => 'integer',
            'quantity' => 'integer',
            'unit_cost_amount' => 'integer',
            'total_amount' => 'integer',
            'received_quantity' => 'integer',
            'needed_by' => 'date',
            'decided_at' => 'datetime',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'PR-'.$this->number;
    }

    /**
     * "24 × Nitrile gloves (box of 100)".
     */
    public function summary(): string
    {
        return "{$this->quantity} × {$this->item_name}";
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<InventoryLocation, $this>
     */
    public function deliverTo(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'deliver_to_location_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
