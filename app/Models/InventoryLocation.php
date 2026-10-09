<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\InventoryLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place stock is kept: a warehouse, a store room, a line-side rack.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'code', 'description', 'is_active'])]
class InventoryLocation extends Model
{
    /** @use HasFactory<InventoryLocationFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'code' => null,
        'description' => null,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<InventoryStockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class);
    }
}
