<?php

namespace App\Actions\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Activity\ActivityLogger;

/**
 * Creates or edits the records items refer to: suppliers, locations and
 * categories. They are small enough to share one action.
 */
class SaveInventoryRecord
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @template TRecord of Supplier|InventoryLocation|InventoryCategory
     *
     * @param  TRecord  $record  A new (unsaved) or existing record.
     * @param  array<string, mixed>  $attributes  Validated attributes.
     * @return TRecord
     */
    public function handle(Supplier|InventoryLocation|InventoryCategory $record, User $actor, array $attributes): Supplier|InventoryLocation|InventoryCategory
    {
        $creating = ! $record->exists;
        $before = $record->only(array_keys($attributes));

        $record->fill($attributes)->save();

        $kind = match (true) {
            $record instanceof Supplier => 'supplier',
            $record instanceof InventoryLocation => 'location',
            default => 'category',
        };

        $changes = $creating ? [] : ActivityLogger::diff($before, $record->only(array_keys($attributes)));

        if ($creating || $changes !== []) {
            $this->activity->log("inventory.{$kind}_".($creating ? 'created' : 'updated'), $record, [
                'name' => $record->name,
                'changes' => $changes ?: null,
            ], actor: $actor);
        }

        return $record;
    }
}
