<?php

namespace App\Models;

use App\Enums\InventoryUnit;
use App\Enums\StockStatus;
use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the organization keeps in stock.
 *
 * current_stock is the total across locations, kept in step with the stock
 * levels by the movement action inside the same transaction.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property string|null $category_id
 * @property string|null $supplier_id
 * @property string|null $default_location_id
 * @property InventoryUnit $unit
 * @property int $current_stock
 * @property int $minimum_stock
 * @property int $reorder_point
 * @property int $reorder_quantity
 * @property int|null $unit_cost_amount
 * @property string|null $currency
 * @property bool $is_active
 * @property CarbonImmutable|null $low_stock_at
 * @property CarbonImmutable|null $last_movement_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read InventoryCategory|null $category
 * @property-read Supplier|null $supplier
 * @property-read InventoryLocation|null $defaultLocation
 */
#[Fillable([
    'sku', 'name', 'description', 'category_id', 'supplier_id', 'default_location_id', 'unit', 'current_stock',
    'minimum_stock', 'reorder_point', 'reorder_quantity', 'unit_cost_amount', 'currency', 'is_active',
    'low_stock_at', 'last_movement_at',
])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'description' => null,
        'category_id' => null,
        'supplier_id' => null,
        'default_location_id' => null,
        'unit' => 'each',
        'current_stock' => 0,
        'minimum_stock' => 0,
        'reorder_point' => 0,
        'reorder_quantity' => 0,
        'unit_cost_amount' => null,
        'currency' => null,
        'is_active' => true,
        'low_stock_at' => null,
        'last_movement_at' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => InventoryUnit::class,
            'current_stock' => 'integer',
            'minimum_stock' => 'integer',
            'reorder_point' => 'integer',
            'reorder_quantity' => 'integer',
            'unit_cost_amount' => 'integer',
            'is_active' => 'boolean',
            'low_stock_at' => 'datetime',
            'last_movement_at' => 'datetime',
        ];
    }

    public function stockStatus(): StockStatus
    {
        return StockStatus::for($this->current_stock, $this->reorder_point);
    }

    /**
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
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
    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'default_location_id');
    }

    /**
     * @return HasMany<InventoryStockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Items at or below their reorder point.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLow(Builder $query): void
    {
        $query->whereColumn('current_stock', '<=', 'reorder_point');
    }
}
