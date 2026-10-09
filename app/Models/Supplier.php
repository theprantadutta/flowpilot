<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property int|null $lead_time_days
 * @property string|null $notes
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'contact_name', 'email', 'phone', 'website', 'lead_time_days', 'notes', 'is_active'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use BelongsToOrganization, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'contact_name' => null,
        'email' => null,
        'phone' => null,
        'website' => null,
        'lead_time_days' => null,
        'notes' => null,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_time_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<InventoryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
